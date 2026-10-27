<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Enums\RefundSource;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeePaymentAllocation;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns money the bank has confirmed into paid installments. Runs inside an
 * Action's transaction. The installment rows are locked while their balances
 * move, so two confirmations cannot both fill the same balance. If an
 * installment was paid another way in the meantime, the part that cannot be
 * applied is kept as `details.excess_paise` and raised as a pending refund
 * request (source: system) rather than over-crediting the student.
 */
class PaymentSettlement
{
    public function __construct(private readonly ReceiptNumbers $receipts) {}

    /**
     * @return array{applied_paise: int, excess_paise: int}
     */
    public function confirm(FeePayment $payment, string $yearStartsOn, bool $recoveredOnRecheck = false): array
    {
        $allocations = FeePaymentAllocation::query()->where('fee_payment_id', $payment->id)->orderBy('id')->get();
        $invoices = FeeInvoice::query()
            ->whereIn('id', $allocations->pluck('fee_invoice_id')->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $applied = Money::zero();

        foreach ($allocations as $allocation) {
            $invoice = $invoices[$allocation->fee_invoice_id];
            $take = Money::paise($allocation->amount_paise)->atMost(Money::paise($invoice->balancePaise()));

            if ($take->isZero()) {
                $allocation->delete();

                continue;
            }

            $invoice->paid_paise += $take->paise;
            $invoice->save();

            if ($take->paise !== $allocation->amount_paise) {
                $allocation->amount_paise = $take->paise;
                $allocation->save();
            }

            $applied = $applied->plus($take);
        }

        $excess = Money::paise($payment->amount_paise)->minus($applied);
        $details = $payment->details ?? [];

        if ($excess->isPositive()) {
            $details['excess_paise'] = $excess->paise;
        }

        if ($excess->isPositive()) {
            FeeRefund::query()->firstOrCreate(
                ['fee_payment_id' => $payment->id, 'source' => RefundSource::System],
                [
                    'ulid' => strtolower((string) Str::ulid()),
                    'student_id' => $payment->student_id,
                    'amount_paise' => $excess->paise,
                    'reason' => 'Money captured for an installment that was already paid (duplicate payment)',
                    'requested_on' => CalendarDate::today(),
                    'status' => RefundStatus::Pending,
                ],
            );
        }

        $payment->forceFill([
            'status' => PaymentStatus::Success,
            'receipt_number' => $applied->isPositive() ? $this->receipts->next($yearStartsOn) : null,
            'settled_at' => now(),
            'failure_reason' => null,
            'recovered_on_recheck' => $recoveredOnRecheck,
            'details' => $details === [] ? null : $details,
        ])->save();

        return ['applied_paise' => $applied->paise, 'excess_paise' => $excess->paise];
    }

    /** Applies money in your own database; kept here so Actions share one set of table names. */
    public static function connectionName(): string
    {
        return (string) config('multitenancy.tenant_database_connection_name');
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return list<int> ids of the payments, not yet settled, that touch any of these invoices
     */
    public function pendingPaymentIdsFor(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        return array_values(array_map(intval(...), DB::connection(self::connectionName())->table('fee_payments as p')
            ->join('fee_payment_allocations as a', 'a.fee_payment_id', '=', 'p.id')
            ->where('p.status', PaymentStatus::Pending->value)
            ->whereIn('a.fee_invoice_id', $invoiceIds)
            ->distinct()
            ->pluck('p.id')
            ->all()));
    }
}
