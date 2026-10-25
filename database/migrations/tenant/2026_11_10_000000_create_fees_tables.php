<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fees: the installment schedule and fee groups of an academic year, which
     * classes use which group, concessions, one invoice per student and
     * installment, payments and how each payment is applied to invoices.
     * Money is whole paise. Invoices and payments are never deleted: a
     * correction is a new row (refunds come later).
     */
    public function up(): void
    {
        Schema::create('fee_installments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->smallInteger('position');
            $table->string('label', 60);
            $table->date('due_on');
            $table->date('ends_on');
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'position']);
        });

        Schema::create('fee_groups', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->string('name', 40);
            // [{"name": "Tuition fee", "amount_paise": 840000}, ...] per installment
            $table->jsonb('heads');
            $table->timestampsTz();

            $table->index('academic_year_id');
        });

        DB::statement('create unique index fee_groups_name_unique on fee_groups (academic_year_id, lower(name))');

        Schema::create('fee_group_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('school_class_id')->constrained('school_classes')->restrictOnDelete();
            $table->foreignId('fee_group_id')->constrained('fee_groups')->restrictOnDelete();
            $table->timestampsTz();

            $table->unique(['academic_year_id', 'school_class_id']);
            $table->index('fee_group_id');
            $table->index('school_class_id');
        });

        Schema::create('fee_concessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 60);
            $table->string('kind', 12)->default('standard');
            $table->string('value_type', 10);
            // whole percent, or paise for a fixed amount
            $table->bigInteger('value');
            $table->string('applies_to_head', 60);
            $table->boolean('needs_approval')->default(false);
            $table->timestampsTz();
        });

        DB::statement("alter table fee_concessions add constraint fee_concessions_kind_check check (kind in ('standard','sibling'))");
        DB::statement("alter table fee_concessions add constraint fee_concessions_type_check check (value_type in ('percent','flat'))");
        DB::statement("alter table fee_concessions add constraint fee_concessions_value_check check (value > 0 and (value_type <> 'percent' or value <= 100))");
        DB::statement('create unique index fee_concessions_name_unique on fee_concessions (lower(name))');
        DB::statement("create unique index fee_concessions_one_sibling on fee_concessions (kind) where kind = 'sibling'");

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->restrictOnDelete();
            $table->foreignId('installment_id')->constrained('fee_installments')->restrictOnDelete();
            $table->foreignId('fee_group_id')->constrained('fee_groups')->restrictOnDelete();
            $table->date('due_on');
            // The fee heads as billed, so later fee changes never rewrite an invoice.
            $table->jsonb('heads');
            $table->bigInteger('gross_paise');
            $table->bigInteger('concession_paise')->default(0);
            $table->string('concession_note')->nullable();
            $table->bigInteger('net_paise');
            $table->bigInteger('paid_paise')->default(0);
            $table->date('last_reminded_on')->nullable();
            $table->timestampsTz();

            $table->unique(['student_id', 'installment_id']);
            $table->index('due_on');
            $table->index('installment_id');
            $table->index('fee_group_id');
            $table->index(['academic_year_id', 'due_on']);
        });

        DB::statement('alter table fee_invoices add constraint fee_invoices_amounts_check check (gross_paise >= 0 and concession_paise >= 0 and net_paise >= 0 and paid_paise >= 0 and paid_paise <= net_paise)');

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->string('mode', 20);
            $table->string('status', 10);
            $table->string('channel', 10);
            $table->bigInteger('amount_paise');
            $table->timestampTz('paid_at');
            $table->string('reference', 60)->nullable();
            $table->string('gateway', 40)->nullable();
            $table->string('receipt_number', 40)->nullable()->unique();
            $table->foreignId('collected_by_staff_id')->nullable()->constrained('staff')->restrictOnDelete();
            $table->string('received_from', 120)->nullable();
            $table->string('note', 300)->nullable();
            $table->string('failure_reason', 200)->nullable();
            $table->jsonb('details')->nullable();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->boolean('recovered_on_recheck')->default(false);
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('link_sent_at')->nullable();
            $table->timestampsTz();

            $table->index('student_id');
            $table->index(['status', 'paid_at']);
            $table->index('paid_at');
            $table->index('collected_by_staff_id');
        });

        DB::statement("alter table fee_payments add constraint fee_payments_mode_check check (mode in ('cash','cheque','upi','card','net_banking','bank_transfer'))");
        DB::statement("alter table fee_payments add constraint fee_payments_status_check check (status in ('pending','success','failed'))");
        DB::statement("alter table fee_payments add constraint fee_payments_channel_check check (channel in ('counter','online'))");
        DB::statement('alter table fee_payments add constraint fee_payments_amount_check check (amount_paise > 0)');

        Schema::create('fee_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_payment_id')->constrained('fee_payments')->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->constrained('fee_invoices')->restrictOnDelete();
            $table->bigInteger('amount_paise');
            $table->timestampsTz();

            $table->unique(['fee_payment_id', 'fee_invoice_id']);
            $table->index('fee_invoice_id');
        });

        DB::statement('alter table fee_payment_allocations add constraint fee_payment_allocations_amount_check check (amount_paise > 0)');
    }
};
