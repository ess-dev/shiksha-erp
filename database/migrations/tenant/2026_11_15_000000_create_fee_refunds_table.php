<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refund requests and what became of them. A refund is never deleted or
     * edited after the decision: it is approved (money paid back or credited
     * to later installments) or rejected with a reason.
     */
    public function up(): void
    {
        Schema::create('fee_refunds', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('fee_payment_id')->nullable()->constrained('fee_payments')->restrictOnDelete();
            $table->bigInteger('amount_paise');
            $table->string('reason', 300);
            $table->string('source', 10);
            $table->foreignId('requested_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->date('requested_on');
            $table->string('status', 10)->default('pending');
            $table->foreignId('decided_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->string('method', 20)->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('reject_reason', 300)->nullable();
            $table->boolean('principal_signed_off')->default(false);
            $table->jsonb('applied_to')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index('fee_payment_id');
            $table->index(['status', 'requested_on']);
            $table->index('requested_by_staff_id');
            $table->index('decided_by_staff_id');
        });

        DB::statement('alter table fee_refunds add constraint fee_refunds_amount_check check (amount_paise > 0)');
        DB::statement("alter table fee_refunds add constraint fee_refunds_source_check check (source in ('parent','accounts','system'))");
        DB::statement("alter table fee_refunds add constraint fee_refunds_status_check check (status in ('pending','approved','rejected'))");
        DB::statement("alter table fee_refunds add constraint fee_refunds_method_check check (method is null or method in ('original','bank_transfer','cheque','adjustment'))");
        // One automatic request per payment: confirming it twice can never ask for the same money twice.
        DB::statement("create unique index fee_refunds_system_payment_unique on fee_refunds (fee_payment_id) where source = 'system'");
    }
};
