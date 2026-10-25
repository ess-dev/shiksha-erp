<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Data\CollectInstallmentData;
use App\Domain\Fees\Data\CollectScreenData;
use App\Domain\Fees\Data\CollectSearchResultData;
use App\Domain\Fees\Data\CollectStudentData;
use App\Domain\Fees\Data\FeeOptionData;
use App\Domain\Fees\Enums\InvoiceStatus;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The Collect fee screen: find a student the person may collect for
 * (`fees.collect`), and list what that student still owes this year.
 */
class CollectFeeQuery
{
    private const RESULTS = 8;

    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
    ) {}

    public function screen(Identity $actor, string $search, ?string $studentUlid, ?string $campusUlid): CollectScreenData
    {
        $year = $this->years->find();
        $today = CalendarDate::today();
        $modes = array_values(array_map(
            fn (PaymentMode $mode) => new FeeOptionData($mode->value, $mode->label()),
            array_filter(PaymentMode::cases(), fn (PaymentMode $mode) => $mode->isCounterMode()),
        ));

        if ($year === null) {
            return new CollectScreenData($search, [], null, $modes, $today, false);
        }

        return new CollectScreenData(
            search: $search,
            results: mb_strlen(trim($search)) >= 2 ? $this->search($actor, trim($search), $year['key'], $campusUlid, $today) : [],
            student: $studentUlid === null ? null : $this->student($actor, $studentUlid, $year['key'], $today),
            modes: $modes,
            today: $today,
            has_schedule: FeeInstallment::query()->where('academic_year_id', $year['key'])->exists(),
        );
    }

    /**
     * @return list<CollectSearchResultData>
     */
    private function search(Identity $actor, string $search, int $yearKey, ?string $campusUlid, string $today): array
    {
        $query = $this->visibility->invoices($actor, 'fees.collect');

        if ($query === null) {
            return [];
        }

        $query->where('fee_invoices.academic_year_id', $yearKey)->where('st.status', 'enrolled');

        if ($campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
        $classLike = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower(preg_replace('/^class\s*/i', '', $search) ?? $search)).'%';

        $query->where(function ($where) use ($like, $classLike) {
            $where->whereRaw("lower(st.first_name || ' ' || st.last_name) like ?", [$like])
                ->orWhereRaw('lower(st.admission_number) like ?', [$like])
                ->orWhereRaw("lower(sc.name || '-' || s.name) like ?", [$classLike]);
        });

        $horizon = (new DateTimeImmutable($today))->modify('+'.InvoiceStatus::DUE_SOON_DAYS.' days')->format('Y-m-d');

        $rows = $query->toBase()->cloneWithout(['columns', 'orders'])
            ->groupBy('st.id', 'st.ulid', 'st.first_name', 'st.last_name', 'st.admission_number', 'sc.name', 's.name', 'c.name')
            ->orderBy('st.first_name')->orderBy('st.last_name')
            ->limit(self::RESULTS)
            ->select(['st.ulid', 'st.first_name', 'st.last_name', 'st.admission_number', 'sc.name as class_name', 's.name as section_name', 'c.name as campus_name'])
            ->selectRaw('coalesce(sum(case when fee_invoices.due_on <= ? then fee_invoices.net_paise - fee_invoices.paid_paise else 0 end), 0) as due_paise', [$horizon])
            ->get();

        return array_values($rows->map(fn ($row) => new CollectSearchResultData(
            (string) $row->ulid,
            trim($row->first_name.' '.$row->last_name),
            (string) $row->admission_number,
            $row->class_name.'-'.$row->section_name,
            (string) $row->campus_name,
            (int) $row->due_paise,
        ))->all());
    }

    private function student(Identity $actor, string $ulid, int $yearKey, string $today): ?CollectStudentData
    {
        $query = $this->visibility->invoices($actor, 'fees.collect');

        if ($query === null) {
            return null;
        }

        $invoices = $query->where('fee_invoices.academic_year_id', $yearKey)->where('st.ulid', $ulid)->where('st.status', 'enrolled')
            ->whereColumn('fee_invoices.paid_paise', '<', 'fee_invoices.net_paise')
            ->join('fee_installments as i', 'i.id', '=', 'fee_invoices.installment_id')
            ->addSelect(['st.id as r_student_id', 'st.first_name as r_first', 'st.last_name as r_last', 'st.admission_number as r_admission',
                'sc.name as r_class', 's.name as r_section', 'c.name as r_campus', 'i.label as r_label'])
            ->orderBy('fee_invoices.due_on')->orderBy('fee_invoices.id')
            ->get();

        if ($invoices->isEmpty()) {
            // A student with nothing due is still shown (with "No dues"), as long as they are in reach.
            return $this->studentWithoutDues($actor, $ulid, $yearKey);
        }

        $first = $invoices->first();
        $pending = $this->pendingNotes(array_values($invoices->pluck('id')->map(fn ($id) => (int) $id)->all()));
        $guardian = $this->guardian((int) $first->getAttribute('r_student_id'));
        $horizon = (new DateTimeImmutable($today))->modify('+'.InvoiceStatus::DUE_SOON_DAYS.' days')->format('Y-m-d');

        return new CollectStudentData(
            ulid: $ulid,
            name: trim($first->getAttribute('r_first').' '.$first->getAttribute('r_last')),
            admission_number: (string) $first->getAttribute('r_admission'),
            section_label: $first->getAttribute('r_class').'-'.$first->getAttribute('r_section'),
            campus_name: (string) $first->getAttribute('r_campus'),
            guardian_name: $guardian['name'] ?? null,
            guardian_relation: $guardian['relation'] ?? null,
            due_paise: (int) $invoices->filter(fn (FeeInvoice $invoice) => $invoice->due_on <= $horizon)->sum(fn (FeeInvoice $invoice) => $invoice->balancePaise()),
            installments: array_values($invoices->map(fn (FeeInvoice $invoice) => new CollectInstallmentData(
                ulid: $invoice->ulid,
                label: (string) $invoice->getAttribute('r_label'),
                due_on: $invoice->due_on,
                status: $invoice->status($today),
                net_paise: $invoice->net_paise,
                paid_paise: $invoice->paid_paise,
                balance_paise: $invoice->balancePaise(),
                concession_note: $invoice->concession_note,
                pending_note: $pending[$invoice->id] ?? null,
            ))->all()),
        );
    }

    private function studentWithoutDues(Identity $actor, string $ulid, int $yearKey): ?CollectStudentData
    {
        $query = $this->visibility->invoices($actor, 'fees.collect');

        $row = $query?->where('fee_invoices.academic_year_id', $yearKey)->where('st.ulid', $ulid)->where('st.status', 'enrolled')
            ->toBase()->cloneWithout(['columns', 'orders'])
            ->first(['st.id as student_id', 'st.first_name', 'st.last_name', 'st.admission_number', 'sc.name as class_name', 's.name as section_name', 'c.name as campus_name']);

        if ($row === null) {
            return null;
        }

        $guardian = $this->guardian((int) $row->student_id);

        return new CollectStudentData(
            ulid: $ulid,
            name: trim($row->first_name.' '.$row->last_name),
            admission_number: (string) $row->admission_number,
            section_label: $row->class_name.'-'.$row->section_name,
            campus_name: (string) $row->campus_name,
            guardian_name: $guardian['name'] ?? null,
            guardian_relation: $guardian['relation'] ?? null,
            due_paise: 0,
            installments: [],
        );
    }

    /**
     * "Net banking payment of ₹15,200 on 14 Oct is still pending", by invoice.
     *
     * @param  list<int>  $invoiceIds
     * @return array<int, string>
     */
    private function pendingNotes(array $invoiceIds): array
    {
        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('fee_payment_allocations as a')
            ->join('fee_payments as p', 'p.id', '=', 'a.fee_payment_id')
            ->where('p.status', PaymentStatus::Pending->value)
            ->whereIn('a.fee_invoice_id', $invoiceIds)
            ->get(['a.fee_invoice_id', 'p.mode', 'p.amount_paise', 'p.paid_at']);

        $notes = [];

        foreach ($rows as $row) {
            $notes[(int) $row->fee_invoice_id] = PaymentMode::from($row->mode)->label().' payment of '.Money::paise((int) $row->amount_paise)->format().' is still pending. Check before collecting again.';
        }

        return $notes;
    }

    /**
     * @return array{name: string, relation: string}|null
     */
    private function guardian(int $studentId): ?array
    {
        $row = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('guardian_student as gs')
            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->where('gs.student_id', $studentId)
            ->orderByDesc('gs.is_primary')->orderBy('gs.id')
            ->first(['g.name', 'gs.relation']);

        return $row === null ? null : ['name' => (string) $row->name, 'relation' => (string) $row->relation];
    }
}
