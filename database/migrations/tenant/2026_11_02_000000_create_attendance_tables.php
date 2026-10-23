<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily attendance: one register per section per school day, one mark per
     * student on it, the school's holiday list, and the class teacher of a
     * section. Calendar dates use `date` and are never converted through UTC.
     * Registers and marks are corrected in place with a reason and an audit
     * event, never deleted. The new `sections` column is nullable, so the
     * previous app version keeps working while this rolls out.
     */
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('class_teacher_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
        });

        Schema::create('attendance_registers', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->date('attendance_on');
            $table->foreignId('saved_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('first_saved_at');
            $table->timestampTz('last_saved_at');
            $table->string('last_reason', 200)->nullable();
            $table->timestampsTz();

            $table->unique(['section_id', 'attendance_on']);
            $table->index('attendance_on');
            $table->index('saved_by_staff_id');
        });

        Schema::create('attendance_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_register_id')->constrained('attendance_registers')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('status');
            $table->timestampsTz();

            $table->unique(['attendance_register_id', 'student_id']);
            $table->index('student_id');
        });

        DB::statement("ALTER TABLE attendance_marks ADD CONSTRAINT attendance_marks_status_check CHECK (status IN ('present', 'absent', 'late'))");

        Schema::create('school_holidays', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->date('holiday_on')->unique();
            $table->string('name', 100);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holidays');
        Schema::dropIfExists('attendance_marks');
        Schema::dropIfExists('attendance_registers');

        Schema::table('sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_teacher_staff_id');
        });
    }
};
