<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Homework a teacher sets for one section and subject, its attachments, and
     * each student's submission. Assignments are user-generated content, so they
     * soft-delete (the approved short list). A submission is one row per student
     * and assignment: uploaded work (`submitted_on`, with files), or notebook work
     * the teacher marked checked (`checked_on`), plus optional feedback. Files live
     * on the tenant disk under the school's prefix.
     */
    public function up(): void
    {
        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('title', 80);
            $table->string('instructions', 400)->nullable();
            $table->date('assigned_on');
            $table->date('due_on');
            $table->string('submission_type', 20);
            $table->foreignId('created_by_staff_id')->constrained('staff')->restrictOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['section_id', 'assigned_on']);
            $table->index('academic_year_id');
            $table->index('subject_id');
            $table->index('created_by_staff_id');
        });

        DB::statement("ALTER TABLE homework_assignments ADD CONSTRAINT homework_assignments_type_check CHECK (submission_type IN ('notebook', 'upload'))");
        DB::statement('ALTER TABLE homework_assignments ADD CONSTRAINT homework_assignments_due_check CHECK (due_on >= assigned_on)');

        Schema::create('homework_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('homework_assignment_id')->constrained('homework_assignments')->restrictOnDelete();
            $table->string('file_path');
            $table->string('file_name', 200);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->index('homework_assignment_id');
        });

        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('homework_assignment_id')->constrained('homework_assignments')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('submitted_on')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('submitted_by_guardian_id')->nullable()->constrained('guardians')->restrictOnDelete();
            $table->date('checked_on')->nullable();
            $table->foreignId('checked_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('feedback', 160)->nullable();
            $table->foreignId('feedback_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('feedback_at')->nullable();
            $table->timestampsTz();

            $table->unique(['homework_assignment_id', 'student_id']);
            $table->index('student_id');
            $table->index('submitted_by_guardian_id');
            $table->index('checked_by_staff_id');
            $table->index('feedback_by_staff_id');
        });

        DB::statement('ALTER TABLE homework_submissions ADD CONSTRAINT homework_submissions_done_check CHECK (submitted_on IS NOT NULL OR checked_on IS NOT NULL)');

        Schema::create('homework_submission_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('homework_submission_id')->constrained('homework_submissions')->restrictOnDelete();
            $table->string('file_path');
            $table->string('file_name', 200);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->unsignedSmallInteger('position');
            $table->timestampsTz();

            $table->index('homework_submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submission_files');
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework_files');
        Schema::dropIfExists('homework_assignments');
    }
};
