<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Fees\Data\PaymentDetailData;
use App\Domain\Fees\Data\PaymentLineData;
use App\Domain\Fees\Data\ReceiptData;
use App\Domain\Fees\Enums\PaymentChannel;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Gateways\PaymentGatewayManager;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Shared\AmountInWords;
use App\Domain\Shared\Money;
use App\Tenancy\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns stored payments into what screens show: the lines (which student's
 * installment got how much), the "Payment details" dialog and the printable
 * receipt. Shared by Payments, Collect fee and the parent's Fees page.
 * Applies no access rule: callers pass payments they already scoped.
 */
class PaymentViewQuery
{
    public function __construct(
        private readonly StaffRefQuery $staff,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * @param  Collection<int, FeePayment>  $payments
     * @return array<int, array{lines: list<PaymentLineData>, still_owed: bool, for_label: string, detail: PaymentDetailData, receipt: ReceiptData|null}> keyed by payment id
     */
    public function build(Collection $payments): array
    {
        if ($payments->isEmpty()) {
            return [];
        }

        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('fee_payment_allocations as a')
            ->join('fee_invoices as i', 'i.id', '=', 'a.fee_invoice_id')
            ->join('fee_installments as inst', 'inst.id', '=', 'i.installment_id')
            ->join('students as st', 'st.id', '=', 'i.student_id')
            ->join('enrollments as e', function ($join) {
                $join->whereRaw('e.id = (select en.id from enrollments en where en.student_id = i.student_id and en.academic_year_id = i.academic_year_id order by en.is_current desc, en.id desc limit 1)');
            })
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->join('academic_years as ay', 'ay.id', '=', 'i.academic_year_id')
            ->whereIn('a.fee_payment_id', $payments->pluck('id')->all())
            ->orderBy('inst.position')->orderBy('a.id')
            ->get([
                'a.fee_payment_id', 'a.amount_paise', 'i.net_paise', 'i.paid_paise', 'inst.label as installment', 'st.first_name', 'st.last_name',
                'sc.name as class_name', 's.name as section_name', 'ay.name as year_name', 'ay.is_current', 'c.name as campus_name', 'c.address as campus_address',
            ])
            ->groupBy('fee_payment_id');

        $collectors = $this->staff->namesByKey(array_values($payments->pluck('collected_by_staff_id')->filter()->map(fn ($id) => (int) $id)->all()));
        $gatewayLabel = $this->gateways->current()?->label();
        $school = Tenant::current()->name;
        $views = [];

        foreach ($payments as $payment) {
            $mine = $rows[$payment->id] ?? collect();
            $lines = array_values($mine->map(fn ($row) => new PaymentLineData(
                trim($row->first_name.' '.$row->last_name),
                $row->class_name.'-'.$row->section_name,
                (string) $row->installment,
                (int) $row->amount_paise,
                (int) $row->paid_paise >= (int) $row->net_paise,
            ))->all());

            $stillOwed = $mine->contains(fn ($row) => (int) $row->paid_paise < (int) $row->net_paise);
            $first = $mine->first();
            $forLabel = implode(', ', array_values(array_unique(array_map(
                fn (PaymentLineData $line) => $line->student_name.' · '.$line->installment_label,
                $lines,
            ))));
            $collector = $payment->collected_by_staff_id === null ? null : ($collectors[$payment->collected_by_staff_id] ?? null);
            $excess = (int) (($payment->details ?? [])['excess_paise'] ?? 0);
            $cheque = $payment->mode === PaymentMode::Cheque
                ? implode(' · ', array_filter([
                    ($payment->details['cheque_number'] ?? null) === null ? null : 'No. '.$payment->details['cheque_number'],
                    $payment->details['bank'] ?? null,
                    $payment->details['cheque_date'] ?? null,
                ]))
                : null;

            $detail = new PaymentDetailData(
                payment_id: $payment->ulid,
                status: $payment->status,
                mode_label: $payment->mode->label(),
                is_counter: $payment->channel === PaymentChannel::Counter,
                gateway: $payment->channel === PaymentChannel::Counter ? 'Counter' : ($payment->gateway ?? $gatewayLabel),
                reference: $payment->reference,
                receipt_number: $payment->receipt_number,
                paid_at: $payment->paid_at->toIso8601String(),
                collected_by: $collector,
                note: $payment->note,
                failure_reason: $payment->failure_reason,
                cheque_details: $cheque === '' ? null : $cheque,
                excess_paise: $excess,
                lines: $lines,
            );

            $receipt = null;

            if ($payment->status === PaymentStatus::Success && $payment->receipt_number !== null && $first !== null) {
                $total = array_sum(array_map(fn (PaymentLineData $line) => $line->amount_paise, $lines));

                $receipt = new ReceiptData(
                    receipt_number: $payment->receipt_number,
                    school_name: $school,
                    school_address: $first->campus_address === null || $first->campus_address === '' ? null : (string) $first->campus_address,
                    paid_at: $payment->paid_at->toIso8601String(),
                    received_from: $payment->received_from,
                    mode_label: $payment->mode->label(),
                    is_counter: $payment->channel === PaymentChannel::Counter,
                    reference: $payment->reference,
                    collected_by: $collector,
                    academic_year: (string) $first->year_name,
                    lines: $lines,
                    total_paise: $total,
                    amount_in_words: AmountInWords::rupees($total),
                    is_archived: ! (bool) $first->is_current,
                );
            }

            $views[$payment->id] = [
                'lines' => $lines,
                'still_owed' => $stillOwed,
                'for_label' => $forLabel,
                'detail' => $detail,
                'receipt' => $receipt,
            ];
        }

        return $views;
    }

    /**
     * Words that explain a payment's status next to its badge.
     *
     * @param  array{still_owed: bool}  $view
     */
    public function statusNote(FeePayment $payment, array $view): string
    {
        $excess = (int) (($payment->details ?? [])['excess_paise'] ?? 0);

        return match ($payment->status) {
            PaymentStatus::Pending => $payment->mode === PaymentMode::Cheque ? 'Awaiting clearance' : 'Awaiting gateway confirmation',
            PaymentStatus::Failed => $view['still_owed']
                ? (string) $payment->failure_reason
                : 'Paid separately later',
            PaymentStatus::Success => match (true) {
                $excess > 0 => Money::paise($excess)->format().' could not be applied (already paid) and is due back',
                $payment->recovered_on_recheck => 'Bank captured it on re-check',
                $payment->mode === PaymentMode::Cheque && $payment->settled_at !== null => 'Cheque cleared',
                $payment->channel === PaymentChannel::Online && $payment->settled_at !== null => 'Confirmed by the gateway',
                default => '',
            },
        };
    }
}
