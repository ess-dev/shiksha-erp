<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What parents read under "Subjects": the textbook, chapter list and
     * assessment of a subject in a class for one academic year (shared by all
     * its sections), the progress and note of one section, and a private note
     * for one student. Chapters and ticks are JSONB lists (a snapshot of that
     * year, not rows of their own); a tick is the position of a chapter.
     */
    public function up(): void
    {
        Schema::create('subject_syllabi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('book', 80)->nullable();
            $table->jsonb('chapters')->default('[]');
            $table->string('assessment', 120)->nullable();
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'school_class_id', 'subject_id']);
            $table->index('school_class_id');
            $table->index('subject_id');
            $table->index('updated_by_staff_id');
        });

        Schema::create('section_subject_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->jsonb('done_chapters')->default('[]');
            $table->smallInteger('current_chapter')->nullable();
            $table->smallInteger('periods_per_week')->nullable();
            $table->string('note', 300)->nullable();
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['section_id', 'subject_id']);
            $table->index('subject_id');
            $table->index('updated_by_staff_id');
        });

        DB::statement('ALTER TABLE section_subject_progress ADD CONSTRAINT section_subject_progress_current_check CHECK (current_chapter IS NULL OR current_chapter >= 0)');
        DB::statement('ALTER TABLE section_subject_progress ADD CONSTRAINT section_subject_progress_periods_check CHECK (periods_per_week IS NULL OR (periods_per_week BETWEEN 1 AND 12))');

        Schema::create('student_subject_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('note', 240);
            $table->foreignId('updated_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'student_id', 'subject_id']);
            $table->index('student_id');
            $table->index('subject_id');
            $table->index('updated_by_staff_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subject_notes');
        Schema::dropIfExists('section_subject_progress');
        Schema::dropIfExists('subject_syllabi');
    }
};
