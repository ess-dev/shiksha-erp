<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Actions\SettleChequePayment;
use App\Domain\Fees\Data\FeeOptionData;
use App\Domain\Fees\Data\GatewayInfoData;
use App\Domain\Fees\Data\PaymentRowData;
use App\Domain\Fees\Data\PaymentsScreenData;
use App\Domain\Fees\Data\PaymentsSummaryData;
use App\Domain\Fees\Enums\PaymentChannel;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Gateways\PaymentGatewayManager;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\PaymentFilters;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Payments and reconciliation screen: every payment of the students the
 * person's `fees.view` covers, with the things that need action first.
 */
class PaymentsQuery
{
    public const PER_PAGE = 20;

    /** Failed, and an installment it was for is still unpaid. */
    private const UNRESOLVED_FAILED = "fee_payments.status = 'failed' and exists (select 1 from fee_payment_allocations fa join fee_invoices fi on fi.id = fa.fee_invoice_id where fa.fee_payment_id = fee_payments.id and fi.paid_paise < fi.net_paise)";

    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly PaymentViewQuery $views,
        private readonly PaymentGatewayManager $gateways,
        private readonly AcademicSessionsQuery $years,
    ) {}

    public function screen(Identity $actor, PaymentFilters $filters, int $page): PaymentsScreenData
    {
        $base = $this->visibility->payments($actor, 'fees.view');
        $gateway = $this->gateways->current();
        $gatewayInfo = $gateway === null ? null : new GatewayInfoData($gateway->label(), $gateway->isSimulated());
        $modes = array_map(fn (PaymentMode $mode) => new FeeOptionData($mode->value, $mode->label()), PaymentMode::cases());
        $today = CalendarDate::today();

        if ($base === null) {
            return new PaymentsScreenData([], new PaymentsSummaryData(0, 0, 0, 0, 0, 0, 0), 0, 0, 0, 1, 1, $modes, $gatewayInfo, SettleChequePayment::BOUNCE_REASONS, $today, false);
        }

        $refunds = $this->visibility->refunds($actor, 'fees.refund')?->where('fee_refunds.status', RefundStatus::Pending->value);

        if ($refunds !== null && $filters->campusUlid !== null) {
            $refunds->where('c.ulid', $filters->campusUlid);
        }

        $summary = $this->summary($this->visibility->payments($actor, 'fees.view') ?? $base, $filters->campusUlid, $refunds === null ? 0 : $refunds->toBase()->cloneWithout(['columns'])->count());
        $query = $this->filtered($base, $filters);

        $total = (clone $query)->toBase()->cloneWithout(['columns', 'orders'])->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $lastPage));

        $payments = $query
            ->orderByRaw('case when fee_payments.status = \'pending\' then 0 when '.self::UNRESOLVED_FAILED.' then 1 else 2 end')
            ->orderByDesc('fee_payments.paid_at')->orderByDesc('fee_payments.id')
            ->addSelect(['st.first_name as r_first', 'st.last_name as r_last', 's.name as r_section', 'sc.name as r_class'])
            ->forPage($page, self::PER_PAGE)
            ->get();

        $views = $this->views->build($payments);
        $studentIds = array_values($payments->pluck('student_id')->unique()->map(fn ($id) => (int) $id)->all());
        $reconcile = $this->visibility->allowsMany($actor, $studentIds, 'fees.reconcile');
        $remind = $this->visibility->allowsMany($actor, $studentIds, 'fees.remind');

        $rows = $payments->map(function (FeePayment $payment) use ($views, $reconcile, $remind) {
            $view = $views[$payment->id];
            $online = $payment->channel === PaymentChannel::Online;
            $mayReconcile = $reconcile[$payment->student_id] ?? false;
            $unresolvedFailed = $payment->status === PaymentStatus::Failed && $view['still_owed'];

            return new PaymentRowData(
                ulid: $payment->ulid,
                paid_at: $payment->paid_at->toIso8601String(),
                student_name: trim($payment->getAttribute('r_first').' '.$payment->getAttribute('r_last')),
                section_label: $payment->getAttribute('r_class').'-'.$payment->getAttribute('r_section'),
                receipt_number: $payment->receipt_number,
                mode_label: $payment->mode->label(),
                is_counter: ! $online,
                reference: $payment->reference,
                amount_paise: $payment->amount_paise,
                status: $payment->status,
                status_note: $this->views->statusNote($payment, $view),
                can_check: $mayReconcile && $online && $payment->status === PaymentStatus::Pending,
                can_clear_cheque: $mayReconcile && $payment->mode === PaymentMode::Cheque && $payment->status === PaymentStatus::Pending,
                can_recheck: $mayReconcile && $online && $unresolvedFailed,
                can_send_link: ($remind[$payment->student_id] ?? false) && $online && $unresolvedFailed,
                link_sent: $payment->link_sent_at !== null,
                receipt: $view['receipt'],
                detail: $view['detail'],
            );
        })->all();

        return new PaymentsScreenData(
            rows: array_values($rows),
            summary: $summary,
            total: $total,
            from: $total === 0 ? 0 : ($page - 1) * self::PER_PAGE + 1,
            to: $total === 0 ? 0 : ($page - 1) * self::PER_PAGE + count($rows),
            current_page: $page,
            last_page: $lastPage,
            modes: $modes,
            gateway: $gatewayInfo,
            bounce_reasons: SettleChequePayment::BOUNCE_REASONS,
            today: $today,
            can_export: true,
        );
    }

    /**
     * Every payment the filters match, as CSV lines (header first).
     *
     * @return list<list<string>>
     */
    public function exportRows(Identity $actor, PaymentFilters $filters): array
    {
        $rows = [['Date', 'Time', 'Payment ID', 'Student', 'Class', 'Mode', 'Channel', 'Reference', 'Amount (₹)', 'Status', 'Receipt', 'Note']];
        $base = $this->visibility->payments($actor, 'fees.view');

        if ($base === null) {
            return $rows;
        }

        $payments = $this->filtered($base, $filters)
            ->addSelect(['st.first_name as r_first', 'st.last_name as r_last', 's.name as r_section', 'sc.name as r_class'])
            ->orderByDesc('fee_payments.paid_at')->orderByDesc('fee_payments.id')
            ->get();

        $views = $this->views->build($payments);
        $zone = new \DateTimeZone((string) config('shiksha.timezone'));

        foreach ($payments as $payment) {
            $local = $payment->paid_at->toDateTimeImmutable()->setTimezone($zone);

            $rows[] = [
                $local->format('Y-m-d'),
                $local->format('H:i'),
                $payment->ulid,
                trim($payment->getAttribute('r_first').' '.$payment->getAttribute('r_last')),
                $payment->getAttribute('r_class').'-'.$payment->getAttribute('r_section'),
                $payment->mode->label(),
                $payment->channel === PaymentChannel::Counter ? 'Counter' : 'Online',
                (string) $payment->reference,
                Money::plain($payment->amount_paise),
                $payment->status->label(),
                (string) $payment->receipt_number,
                $this->views->statusNote($payment, $views[$payment->id]),
            ];
        }

        return $rows;
    }

    /**
     * @param  Builder<FeePayment>  $query
     * @return Builder<FeePayment>
     */
    private function filtered(Builder $query, PaymentFilters $filters): Builder
    {
        if ($filters->campusUlid !== null) {
            $query->where('c.ulid', $filters->campusUlid);
        }

        if (in_array($filters->status, ['pending', 'failed', 'success'], true)) {
            $query->where('fee_payments.status', $filters->status);
        }

        if (PaymentMode::tryFrom($filters->mode) !== null) {
            $query->where('fee_payments.mode', $filters->mode);
        }

        $zone = (string) config('shiksha.timezone');

        if ($filters->from !== null) {
            $query->whereRaw('(fee_payments.paid_at at time zone ?)::date >= ?', [$zone, $filters->from]);
        }

        if ($filters->to !== null) {
            $query->whereRaw('(fee_payments.paid_at at time zone ?)::date <= ?', [$zone, $filters->to]);
        }

        $search = mb_strtolower(trim($filters->search));

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

            $query->where(function (Builder $where) use ($like) {
                $where->whereRaw("lower(st.first_name || ' ' || st.last_name) like ?", [$like])
                    ->orWhereRaw('lower(st.admission_number) like ?', [$like])
                    ->orWhereRaw("lower(coalesce(fee_payments.receipt_number, '')) like ?", [$like])
                    ->orWhereRaw("lower(coalesce(fee_payments.reference, '')) like ?", [$like])
                    ->orWhereRaw('lower(fee_payments.ulid) like ?', [$like]);
            });
        }

        return $query;
    }

    /**
     * @param  Builder<FeePayment>  $query
     */
    private function summary(Builder $query, ?string $campusUlid, int $openRefunds): PaymentsSummaryData
    {
        if ($campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        $year = $this->years->find();
        $zone = (string) config('shiksha.timezone');

        $row = $query->toBase()->cloneWithout(['columns', 'orders'])->selectRaw(
            "coalesce(sum(case when fee_payments.status = 'success' and (fee_payments.paid_at at time zone ?)::date between ? and ? then fee_payments.amount_paise - coalesce((fee_payments.details->>'excess_paise')::bigint, 0) else 0 end), 0) as successful_paise, "
            ."count(case when fee_payments.status = 'success' and (fee_payments.paid_at at time zone ?)::date between ? and ? then 1 end) as successful_count, "
            ."count(case when fee_payments.status = 'pending' then 1 end) as pending_count, "
            ."coalesce(sum(case when fee_payments.status = 'pending' then fee_payments.amount_paise else 0 end), 0) as pending_paise, "
            .'count(case when '.self::UNRESOLVED_FAILED.' then 1 end) as failed_count, '
            .'coalesce(sum(case when '.self::UNRESOLVED_FAILED.' then fee_payments.amount_paise else 0 end), 0) as failed_paise',
            [$zone, $year['starts_on'] ?? '0001-01-01', $year['ends_on'] ?? '9999-12-31', $zone, $year['starts_on'] ?? '0001-01-01', $year['ends_on'] ?? '9999-12-31'],
        )->first();

        return new PaymentsSummaryData(
            successful_paise: (int) ($row->successful_paise ?? 0),
            successful_count: (int) ($row->successful_count ?? 0),
            pending_count: (int) ($row->pending_count ?? 0),
            pending_paise: (int) ($row->pending_paise ?? 0),
            failed_count: (int) ($row->failed_count ?? 0),
            failed_paise: (int) ($row->failed_paise ?? 0),
            open_refunds_count: $openRefunds,
        );
    }
}
