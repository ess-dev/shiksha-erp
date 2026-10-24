<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unit tests and exams: the exam itself, the classes and campuses it
     * applies to (a flag means "every one, including ones added later") and
     * its date sheet. Marks arrive with the marks-entry slice.
     */
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 60);
            $table->string('type', 20);
            $table->unsignedSmallInteger('max_marks');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('scheduled');
            $table->text('instructions')->nullable();
            $table->boolean('applies_to_all_classes')->default(true);
            $table->boolean('applies_to_all_campuses')->default(true);
            $table->boolean('show_on_calendar')->default(true);
            $table->foreignId('created_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->index(['academic_year_id', 'status']);
            $table->index('starts_on');
            $table->index('created_by_staff_id');
        });

        DB::statement("alter table exams add constraint exams_type_check check (type in ('unit_test','periodic_test','half_yearly','annual','practical','other'))");
        DB::statement("alter table exams add constraint exams_status_check check (status in ('scheduled','marking','published','cancelled'))");
        DB::statement('alter table exams add constraint exams_max_marks_check check (max_marks between 1 and 200)');
        DB::statement('alter table exams add constraint exams_dates_check check (ends_on >= starts_on)');
        // One live exam per name in a year; cancelled ones may share a name.
        DB::statement("create unique index exams_live_name_unique on exams (academic_year_id, lower(name)) where status <> 'cancelled'");

        Schema::create('exam_school_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();

            $table->unique(['exam_id', 'school_class_id']);
            $table->index('school_class_id');
        });

        Schema::create('campus_exam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('campus_id')->constrained('campuses')->restrictOnDelete();

            $table->unique(['exam_id', 'campus_id']);
            $table->index('campus_id');
        });

        Schema::create('exam_date_sheet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->date('held_on');
            $table->time('begins_at')->nullable();

            $table->unique(['exam_id', 'subject_id']);
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_date_sheet_entries');
        Schema::dropIfExists('campus_exam');
        Schema::dropIfExists('exam_school_class');
        Schema::dropIfExists('exams');
    }
};
