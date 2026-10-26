<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave a guardian applies for on behalf of a child. A request is never
     * deleted: it moves through pending, recommended (long leave the class
     * teacher passed to the coordinator), approved, rejected or cancelled, and
     * keeps who did what and when. `needs_coordinator` is decided when the
     * request is made, from the school's limit at that time. The optional
     * medical certificate or letter is one file on the tenant disk.
     */
    public function up(): void
    {
        Schema::create('student_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('applied_by_guardian_id')->constrained('guardians')->restrictOnDelete();
            $table->date('from_on');
            $table->date('to_on');
            $table->unsignedSmallInteger('school_days');
            $table->string('reason_type', 30);
            $table->string('reason', 300);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 200)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->string('status', 20);
            $table->boolean('needs_coordinator')->default(false);
            $table->timestampTz('applied_at');
            $table->foreignId('recommended_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('recommended_at')->nullable();
            $table->string('recommendation_note', 200)->nullable();
            $table->foreignId('decided_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_note', 200)->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['student_id', 'from_on']);
            $table->index(['section_id', 'status']);
            $table->index('academic_year_id');
            $table->index('applied_by_guardian_id');
            $table->index('recommended_by_staff_id');
            $table->index('decided_by_staff_id');
        });

        DB::statement("ALTER TABLE student_leave_requests ADD CONSTRAINT student_leave_requests_status_check CHECK (status IN ('pending', 'recommended', 'approved', 'rejected', 'cancelled'))");
        DB::statement("ALTER TABLE student_leave_requests ADD CONSTRAINT student_leave_requests_reason_type_check CHECK (reason_type IN ('sick', 'family_function', 'family_emergency', 'travel', 'religious', 'other'))");
        DB::statement('ALTER TABLE student_leave_requests ADD CONSTRAINT student_leave_requests_dates_check CHECK (to_on >= from_on AND school_days >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('student_leave_requests');
    }
};
