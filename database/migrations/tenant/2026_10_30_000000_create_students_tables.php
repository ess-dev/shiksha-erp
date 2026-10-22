<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Students, guardians, enrolments, houses, sibling links, documents, plus
     * the school's settings and number sequences (admission and TC numbers).
     * New tables only, so it is safe on existing school databases. Lifecycle is
     * a status field; nothing here is deleted when a student leaves.
     */
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name')->unique();
            $table->string('status')->default('active');
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE houses ADD CONSTRAINT houses_status_check CHECK (status IN ('active', 'inactive'))");

        // Settings the school can change (illustrative — verify keys): TC and admission-number prefixes.
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->jsonb('value');
            $table->timestampsTz();
        });

        // One row per numbered series ("admission:2026"); the Action that issues a number locks the row.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->bigInteger('last_value')->default(0);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE number_sequences ADD CONSTRAINT number_sequences_value_check CHECK (last_value >= 0)');

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            // E.164. One phone is one guardian record (locked 2026-10-23): a second child with the same
            // phone is linked to the existing guardian, otherwise the phone has to change.
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->string('occupation')->nullable();
            // Set when the guardian accepts the invite (portal or mobile app). Parent sign-in is not built yet.
            $table->ulid('identity_ulid')->nullable()->unique();
            $table->timestampsTz();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('admission_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender');
            $table->date('born_on');
            $table->string('blood_group');
            // Null means "Not assigned" (a school with no houses, or a student not yet placed).
            $table->foreignId('house_id')->nullable()->constrained('houses')->restrictOnDelete();
            $table->text('address');
            $table->string('status')->default('enrolled');
            $table->date('admitted_on');
            $table->date('left_on')->nullable();
            $table->string('leave_reason')->nullable();
            $table->string('tc_number')->nullable()->unique();
            $table->timestampsTz();

            $table->index('status');
            $table->index('house_id');
            $table->index(['last_name', 'first_name']);
        });

        DB::statement("ALTER TABLE students ADD CONSTRAINT students_gender_check CHECK (gender IN ('male', 'female', 'other'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_blood_group_check CHECK (blood_group IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'))");
        DB::statement("ALTER TABLE students ADD CONSTRAINT students_status_check CHECK (status IN ('enrolled', 'left', 'transferred', 'alumni'))");

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('relation');
            $table->boolean('is_primary')->default(false);
            $table->boolean('has_portal_access')->default(true);
            $table->timestampsTz();

            $table->unique(['guardian_id', 'student_id']);
            $table->index('student_id');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->smallInteger('roll_number')->nullable();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->string('result')->default('in_progress');
            $table->string('note')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('section_id');
        });

        DB::statement("ALTER TABLE enrollments ADD CONSTRAINT enrollments_result_check CHECK (result IN ('in_progress', 'promoted', 'moved', 'left'))");
        DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_roll_check CHECK (roll_number IS NULL OR roll_number > 0)');
        DB::statement('CREATE UNIQUE INDEX enrollments_one_current ON enrollments (student_id) WHERE is_current');
        DB::statement('CREATE UNIQUE INDEX enrollments_roll_unique ON enrollments (section_id, roll_number) WHERE is_current AND roll_number IS NOT NULL');

        // A staff-made link between two students whose guardians differ. The lower id is always first.
        Schema::create('student_sibling_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('sibling_id')->constrained('students')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['student_id', 'sibling_id']);
            $table->index('sibling_id');
        });

        DB::statement('ALTER TABLE student_sibling_links ADD CONSTRAINT student_sibling_links_order_check CHECK (student_id < sibling_id)');

        Schema::create('student_documents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('kind');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type');
            $table->bigInteger('size_bytes');
            $table->string('source')->default('school');
            // Staff are referenced by identity ulid, as everywhere else outside the Access module.
            $table->ulid('uploaded_by_identity_ulid')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->ulid('verified_by_identity_ulid')->nullable();
            // A newer document of the same kind replaces this one; the old row and file are kept, never deleted.
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
        });

        DB::statement("ALTER TABLE student_documents ADD CONSTRAINT student_documents_kind_check CHECK (kind IN ('birth_certificate', 'aadhaar', 'transfer_certificate', 'photo', 'medical', 'category_certificate', 'other'))");
        DB::statement("ALTER TABLE student_documents ADD CONSTRAINT student_documents_source_check CHECK (source IN ('school', 'admission', 'parent'))");
        DB::statement('ALTER TABLE student_documents ADD CONSTRAINT student_documents_size_check CHECK (size_bytes >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_documents');
        Schema::dropIfExists('student_sibling_links');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('students');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('houses');
    }
};
