<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks for one student in one subject of one exam, and the state of each
     * (exam, section, subject) sheet: published to parents or not, and the
     * last reopening. Marks are stored as whole half-marks (12.5 is 25) so no
     * fractional type is needed; an absent student has no marks and
     * `is_absent`. A blank mark is no row. `exams.grade_scale` keeps the
     * school's grading scale as it was when the exam's first marks were
     * published, so later changes to the scale do not regrade them.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->jsonb('grade_scale')->nullable();
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->unsignedSmallInteger('half_marks')->nullable();
            $table->boolean('is_absent')->default(false);
            $table->foreignId('entered_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['exam_id', 'student_id', 'subject_id']);
            $table->index(['exam_id', 'section_id', 'subject_id']);
            $table->index('student_id');
            $table->index('subject_id');
            $table->index('section_id');
            $table->index('entered_by_staff_id');
        });

        DB::statement('ALTER TABLE exam_marks ADD CONSTRAINT exam_marks_value_check CHECK ((is_absent AND half_marks IS NULL) OR (NOT is_absent AND half_marks IS NOT NULL AND half_marks <= 400))');

        Schema::create('exam_mark_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('reopened_at')->nullable();
            $table->foreignId('reopened_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('reopen_reason', 200)->nullable();
            $table->timestampsTz();

            $table->unique(['exam_id', 'section_id', 'subject_id']);
            $table->index('section_id');
            $table->index('subject_id');
            $table->index('published_by_staff_id');
            $table->index('reopened_by_staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_mark_sheets');
        Schema::dropIfExists('exam_marks');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('grade_scale');
        });
    }
};
