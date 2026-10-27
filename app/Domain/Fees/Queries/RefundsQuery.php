<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Data\FeeOptionData;
use App\Domain\Fees\Data\RefundCandidateData;
use App\Domain\Fees\Data\RefundCandidatePaymentData;
use App\Domain\Fees\Data\RefundRowData;
use App\Domain\Fees\Data\RefundsScreenData;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\Enums\RefundMethod;
use App\Domain\Fees\Enums\RefundStatus;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeeRefund;
use App\Domain\Fees\RefundLimits;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * The Refunds screen: every refund of the students the person's `fees.refund`
 * covers (the top-bar campus narrows it), with counters and a student search
 * for raising a new request.
 */
class RefundsQuery
{
    public const TABS = ['pending', 'approved', 'rejected', 'all'];

    private const ROWS = 100;

    private const CANDIDATES = 8;

    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly RefundLimits $limits,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    public function screen(Identity $actor, string $tab, string $search, ?string $campusUlid): RefundsScreenData
    {
        $tab = in_array($tab, self::TABS, true) ? $tab : 'pending';
        $methods = array_map(fn (RefundMethod $method) => new FeeOptionData($method->value, $method->label()), RefundMethod::cases());
        $limit = $this->settings->refundPrincipalLimitPaise();

        if ($this->base($actor, $campusUlid) === null) {
            return new RefundsScreenData([], $tab, 0, 0, 0, 0, 0, $limit, $methods, $search, []);
        }

        $totals = $this->base($actor, $campusUlid)
            ->toBase()->cloneWithout(['columns'])
            ->selectRaw('fee_refunds.status, count(*) as total, coalesce(sum(fee_refunds.amount_paise), 0) as paise')
            ->groupBy('fee_refunds.status')->get()->keyBy('status');

        $query = $this->base($actor, $campusUlid);

        if ($tab !== 'all') {
            $query->where('fee_refunds.status', $tab);
        }

        $refunds = $query
            ->addSelect(['st.first_name as r_first', 'st.last_name as r_last', 'st.admission_number as r_admission', 's.name as r_section', 'sc.name as r_class'])
            ->orderByDesc('fee_refunds.requested_on')->orderByDesc('fee_refunds.id')
            ->limit(self::ROWS)->get();

        $payments = FeePayment::query()->whereIn('id', $refunds->pluck('fee_payment_id')->filter()->all())->get()->keyBy('id');

        $rows = $refunds->map(function (FeeRefund $refund) use ($payments, $limit) {
            $payment = $payments->get($refund->fee_payment_id);

            return new RefundRowData(
                ulid: $refund->ulid,
                requested_on: $refund->requested_on,
                source: $refund->source,
                student_name: trim($refund->getAttribute('r_first').' '.$refund->getAttribute('r_last')),
                section_label: $refund->getAttribute('r_class').'-'.$refund->getAttribute('r_section'),
                admission_number: (string) $refund->getAttribute('r_admission'),
                reason: $refund->reason,
                receipt_number: $payment?->receipt_number,
                amount_paise: $refund->amount_paise,
                status: $refund->status,
                method: $refund->method,
                reference: $refund->reference,
                decided_on: $refund->decided_at?->timezone((string) config('shiksha.timezone'))->format('Y-m-d'),
                reject_reason: $refund->reject_reason,
                needs_principal: $refund->amount_paise > $limit,
                original_label: $payment === null ? RefundMethod::Original->label() : 'Original '.$payment->mode->label(),
            );
        })->all();

        $pending = $totals->get(RefundStatus::Pending->value);
        $approved = $totals->get(RefundStatus::Approved->value);
        $rejected = $totals->get(RefundStatus::Rejected->value);

        return new RefundsScreenData(
            rows: array_values($rows),
            tab: $tab,
            pending_count: (int) ($pending->total ?? 0),
            pending_paise: (int) ($pending->paise ?? 0),
            approved_count: (int) ($approved->total ?? 0),
            approved_paise: (int) ($approved->paise ?? 0),
            rejected_count: (int) ($rejected->total ?? 0),
            limit_paise: $limit,
            methods: $methods,
            search: $search,
            candidates: mb_strlen(trim($search)) >= 2 ? $this->candidates($actor, trim($search), $campusUlid) : [],
        );
    }

    /**
     * @return Builder<FeeRefund>|null
     */
    private function base(Identity $actor, ?string $campusUlid): ?Builder
    {
        $query = $this->visibility->refunds($actor, 'fees.refund');

        if ($query !== null && $campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        return $query;
    }

    /**
     * Students the person may refund who have a confirmed payment, matching the search.
     *
     * @return list<RefundCandidateData>
     */
    private function candidates(Identity $actor, string $search, ?string $campusUlid): array
    {
        $query = $this->visibility->payments($actor, 'fees.refund');

        if ($query === null) {
            return [];
        }

        if ($campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';

        $students = $query
            ->where('fee_payments.status', PaymentStatus::Success->value)
            ->where(function ($where) use ($like) {
                $where->whereRaw("lower(st.first_name || ' ' || st.last_name) like ?", [$like])
                    ->orWhereRaw('lower(st.admission_number) like ?', [$like]);
            })
            ->toBase()->cloneWithout(['columns'])
            ->select('st.id', 'st.ulid', 'st.first_name', 'st.last_name', 'st.admission_number', 's.name as section', 'sc.name as class')
            ->distinct()->orderBy('st.first_name')->orderBy('st.id')->limit(self::CANDIDATES)->get();

        $result = [];

        foreach ($students as $student) {
            $payments = FeePayment::query()
                ->where('student_id', $student->id)->where('status', PaymentStatus::Success->value)
                ->orderByDesc('paid_at')->orderByDesc('id')->limit(10)->get();

            $result[] = new RefundCandidateData(
                ulid: (string) $student->ulid,
                name: trim($student->first_name.' '.$student->last_name),
                section_label: $student->class.'-'.$student->section,
                admission_number: (string) $student->admission_number,
                refundable_paise: $this->limits->forStudent((int) $student->id),
                payments: array_values($payments->map(fn (FeePayment $payment) => new RefundCandidatePaymentData(
                    ulid: $payment->ulid,
                    receipt_number: $payment->receipt_number,
                    mode_label: $payment->mode->label(),
                    paid_on: $payment->paid_at->timezone((string) config('shiksha.timezone'))->format('Y-m-d'),
                    amount_paise: $payment->amount_paise,
                    refundable_paise: $this->limits->forPayment($payment),
                ))->all()),
            );
        }

        return $result;
    }
}
