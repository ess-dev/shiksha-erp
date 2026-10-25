<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Data\FeeHeadData;
use App\Domain\Fees\Data\ParentFeesData;
use App\Domain\Fees\Data\ParentInstallmentData;
use App\Domain\Fees\Data\ParentPaymentData;
use App\Domain\Fees\Data\ReceiptData;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Shared\CalendarDate;
use Illuminate\Support\Facades\DB;

/**
 * A child's fees for one academic session, for the parent portal. The caller
 * has already worked out which child belongs to the signed-in guardian and
 * which session is viewed; this only reads that child's own rows.
 */
class ParentFeesQuery
{
    public function __construct(private readonly PaymentViewQuery $views) {}

    /**
     * One confirmed payment's receipt, only if it is this child's payment.
     */
    public function receipt(int $studentKey, string $paymentUlid): ?ReceiptData
    {
        $payment = FeePayment::query()->where('student_id', $studentKey)->where('ulid', $paymentUlid)->first();

        return $payment === null ? null : ($this->views->build(collect([$payment]))[$payment->id]['receipt'] ?? null);
    }

    public function get(int $studentKey, int $yearKey, string $sessionName, bool $isCurrent, string $childName, string $firstName, string $sectionLabel): ParentFeesData
    {
        $today = CalendarDate::today();
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $invoices = FeeInvoice::query()
            ->join('fee_installments as inst', 'inst.id', '=', 'fee_invoices.installment_id')
            ->where('fee_invoices.student_id', $studentKey)->where('fee_invoices.academic_year_id', $yearKey)
            ->orderBy('inst.position')
            ->get(['fee_invoices.*', 'inst.label as r_label']);

        if ($invoices->isEmpty()) {
            return new ParentFeesData($sessionName, $isCurrent, $childName, $firstName, $sectionLabel, $today, false, [], [], [], 0, 0, 0);
        }

        $invoiceIds = $invoices->pluck('id')->all();
        $zone = (string) config('shiksha.timezone');

        $lastPaid = $connection->table('fee_payment_allocations as a')
            ->join('fee_payments as p', 'p.id', '=', 'a.fee_payment_id')
            ->where('p.status', PaymentStatus::Success->value)->whereIn('a.fee_invoice_id', $invoiceIds)
            ->selectRaw('a.fee_invoice_id, max((p.paid_at at time zone ?)::date) as paid_on', [$zone])
            ->groupBy('a.fee_invoice_id')
            ->pluck('paid_on', 'fee_invoice_id');

        $pending = $connection->table('fee_payment_allocations as a')
            ->join('fee_payments as p', 'p.id', '=', 'a.fee_payment_id')
            ->where('p.status', PaymentStatus::Pending->value)->whereIn('a.fee_invoice_id', $invoiceIds)
            ->pluck('a.fee_invoice_id')->flip();

        $payments = FeePayment::query()
            ->whereIn('id', $connection->table('fee_payment_allocations')->whereIn('fee_invoice_id', $invoiceIds)->select('fee_payment_id'))
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->get();

        $views = $this->views->build($payments);

        $toParentPayment = function (FeePayment $payment) use ($views, $firstName) {
            $view = $views[$payment->id];
            $labels = array_values(array_unique(array_map(
                fn ($line) => trim(explode('(', $line->installment_label)[0]),
                $view['lines'],
            )));

            return new ParentPaymentData(
                ulid: $payment->ulid,
                amount_paise: $payment->amount_paise,
                paid_at: $payment->paid_at->toIso8601String(),
                mode_label: $payment->mode->label(),
                status: $payment->status,
                receipt_number: $payment->receipt_number,
                for_label: $firstName.' · '.implode(', ', $labels),
                note: $payment->status === PaymentStatus::Failed ? $payment->failure_reason : $payment->note,
                receipt: $view['receipt'],
            );
        };

        $installments = $invoices->map(fn (FeeInvoice $invoice) => new ParentInstallmentData(
            ulid: $invoice->ulid,
            label: (string) $invoice->getAttribute('r_label'),
            due_on: $invoice->due_on,
            status: $invoice->status($today),
            net_paise: $invoice->net_paise,
            paid_paise: $invoice->paid_paise,
            balance_paise: $invoice->balancePaise(),
            paid_on: $invoice->balancePaise() === 0 && isset($lastPaid[$invoice->id]) ? substr((string) $lastPaid[$invoice->id], 0, 10) : null,
            heads: array_map(fn (array $head) => new FeeHeadData($head['name'], (int) $head['amount_paise']), $invoice->heads),
            concession_paise: $invoice->concession_paise,
            concession_note: $invoice->concession_note,
            payment_pending: $pending->has($invoice->id),
        ))->all();

        $shown = $payments->filter(fn (FeePayment $payment) => $payment->status !== PaymentStatus::Failed)->values();
        $alerts = $isCurrent
            ? $payments->filter(fn (FeePayment $payment) => $payment->status === PaymentStatus::Failed && $views[$payment->id]['still_owed'])->values()
            : collect();

        return new ParentFeesData(
            session_name: $sessionName,
            is_current_session: $isCurrent,
            child_name: $childName,
            child_first_name: $firstName,
            section_label: $sectionLabel,
            today: $today,
            has_fees: true,
            installments: array_values($installments),
            payments: array_values($shown->map($toParentPayment)->all()),
            failed_alerts: array_values($alerts->map($toParentPayment)->all()),
            total_paid_paise: (int) $invoices->sum('paid_paise'),
            total_due_paise: (int) $invoices->sum(fn (FeeInvoice $invoice) => $invoice->balancePaise()),
            total_billed_paise: (int) $invoices->sum('net_paise'),
        );
    }
}
