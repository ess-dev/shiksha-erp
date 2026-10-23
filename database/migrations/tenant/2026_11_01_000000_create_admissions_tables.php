<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The admissions pipeline: applications, their stage history and the
     * documents collected before enrolment. New tables only, so it is safe on
     * existing school databases. Applications are never deleted: a closed
     * application stays as a record (decision "rejected", is_closed).
     */
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender');
            // Collected at the Application stage, required before "Documents verified".
            $table->date('born_on')->nullable();
            $table->string('blood_group')->nullable();
            $table->foreignId('house_id')->nullable()->constrained('houses')->restrictOnDelete();
            $table->text('address')->nullable();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('parent_name');
            $table->string('parent_relation');
            // E.164
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('source');
            $table->text('notes')->nullable();
            $table->string('stage')->default('enquiry');
            $table->string('decision')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->foreignId('student_id')->nullable()->unique()->constrained('students')->restrictOnDelete();
            $table->date('enquired_on');
            $table->date('enrolled_on')->nullable();
            $table->ulid('created_by_identity_ulid')->nullable();
            $table->timestampsTz();

            $table->index('stage');
            $table->index('campus_id');
            $table->index('school_class_id');
            $table->index('phone');
        });

        DB::statement("ALTER TABLE admission_applications ADD CONSTRAINT admission_applications_gender_check CHECK (gender IN ('male', 'female', 'other'))");
        DB::statement("ALTER TABLE admission_applications ADD CONSTRAINT admission_applications_blood_group_check CHECK (blood_group IS NULL OR blood_group IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'unknown'))");
        DB::statement("ALTER TABLE admission_applications ADD CONSTRAINT admission_applications_stage_check CHECK (stage IN ('enquiry', 'application', 'verified', 'decision', 'enrolled'))");
        DB::statement("ALTER TABLE admission_applications ADD CONSTRAINT admission_applications_decision_check CHECK (decision IS NULL OR decision IN ('accepted', 'waitlisted', 'rejected'))");
        DB::statement("ALTER TABLE admission_applications ADD CONSTRAINT admission_applications_source_check CHECK (source IN ('walk_in', 'website', 'referral', 'facebook_ad', 'newspaper'))");

        // Append-only stage history shown in the application's History list.
        Schema::create('admission_application_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->string('stage');
            $table->string('note')->nullable();
            $table->date('happened_on');
            $table->ulid('by_identity_ulid')->nullable();
            $table->timestampsTz();

            $table->index('admission_application_id');
        });

        // One row per document kind that was ticked or uploaded. Files live on the tenant disk;
        // on enrolment they are copied to the student's record.
        Schema::create('admission_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_application_id')->constrained('admission_applications')->restrictOnDelete();
            $table->string('kind');
            $table->boolean('is_received')->default(false);
            $table->string('original_name')->nullable();
            $table->string('path')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->date('uploaded_on')->nullable();
            $table->ulid('uploaded_by_identity_ulid')->nullable();
            $table->timestampsTz();

            $table->unique(['admission_application_id', 'kind']);
        });

        DB::statement("ALTER TABLE admission_documents ADD CONSTRAINT admission_documents_kind_check CHECK (kind IN ('birth_certificate', 'aadhaar', 'transfer_certificate', 'photo'))");
        DB::statement('ALTER TABLE admission_documents ADD CONSTRAINT admission_documents_size_check CHECK (size_bytes IS NULL OR size_bytes >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_documents');
        Schema::dropIfExists('admission_application_events');
        Schema::dropIfExists('admission_applications');
    }
};
