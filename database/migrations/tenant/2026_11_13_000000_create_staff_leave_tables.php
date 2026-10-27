<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Leave a member of staff asks for. A request is never deleted: it moves
     * through pending, approved, rejected or cancelled and keeps who decided
     * and when. `half_days` counts half-days (2 per school day, 1 for a half
     * day) so no fractions are stored. `section_id` is the class the applicant
     * leads at the time of the request; it decides who may approve and which
     * register needs cover. On approval a class teacher's leave names the
     * staff member who may mark that register on the leave dates
     * (`cover_staff_id`); the access is worked out from this row, so
     * cancelling or rejecting it ends the cover at once.
     */
    public function up(): void
    {
        Schema::create('staff_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->string('leave_type', 20);
            $table->date('from_on');
            $table->date('to_on');
            $table->unsignedSmallInteger('half_days');
            $table->string('reason', 300);
            $table->string('suggested_cover', 80)->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 200)->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->string('status', 20);
            $table->timestampTz('applied_at');
            $table->foreignId('decided_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('decision_note', 200)->nullable();
            $table->foreignId('cover_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['staff_id', 'from_on']);
            $table->index(['status', 'from_on']);
            $table->index(['cover_staff_id', 'status']);
            $table->index('academic_year_id');
            $table->index('section_id');
            $table->index('decided_by_staff_id');
        });

        DB::statement("ALTER TABLE staff_leave_requests ADD CONSTRAINT staff_leave_requests_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");
        DB::statement("ALTER TABLE staff_leave_requests ADD CONSTRAINT staff_leave_requests_type_check CHECK (leave_type IN ('casual', 'sick', 'earned', 'duty'))");
        DB::statement('ALTER TABLE staff_leave_requests ADD CONSTRAINT staff_leave_requests_dates_check CHECK (to_on >= from_on AND half_days >= 1)');
        DB::statement('ALTER TABLE staff_leave_requests ADD CONSTRAINT staff_leave_requests_half_day_check CHECK (half_days <> 1 OR from_on = to_on)');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_leave_requests');
    }
};
