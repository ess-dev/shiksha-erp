<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Data\DueRowData;
use App\Domain\Fees\Data\DuesScreenData;
use App\Domain\Fees\Data\DuesSummaryData;
use App\Domain\Fees\Data\FeeOptionData;
use App\Domain\Fees\DueFilters;
use App\Domain\Fees\Enums\InvoiceStatus;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\Money;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The Dues screen and its CSV: unpaid installments of the current academic
 * year for enrolled students, limited to the sections the person's
 * `fees.view` covers (and to the campus chosen in the top bar).
 */
class DuesQuery
{
    public const PER_PAGE = 20;

    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
    ) {}

    public function screen(Identity $actor, DueFilters $filters, int $page): DuesScreenData
    {
        $year = $this->years->find();
        $today = CalendarDate::today();
        $base = $year === null ? null : $this->base($actor, $filters, $year['key'], $today);

        if ($year === null || $base === null) {
            return $this->empty($year['name'] ?? '', $today, $year !== null && $this->hasSchedule($year['key']), $actor);
        }

        $totals = (clone $base->toBase())->cloneWithout(['columns', 'orders'])->selectRaw(
            'count(*) as n, count(distinct fee_invoices.student_id) as students, coalesce(sum(fee_invoices.net_paise - fee_invoices.paid_paise), 0) as balance, '
            .'coalesce(sum(case when fee_invoices.due_on < ? then fee_invoices.net_paise - fee_invoices.paid_paise else 0 end), 0) as overdue',
            [$today],
        )->first();

        $total = (int) ($totals->n ?? 0);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $lastPage));

        $invoices = $this->order($base, $filters->sort)
            ->forPage($page, self::PER_PAGE)
            ->addSelect(['st.ulid as r_student_ulid', 'st.first_name as r_first', 'st.last_name as r_last', 'st.admission_number as r_admission',
                's.name as r_section', 'sc.name as r_class', 'c.name as r_campus', 'i.label as r_installment'])
            ->get();

        $studentIds = array_values($invoices->pluck('student_id')->unique()->map(fn ($id) => (int) $id)->all());
        $collect = $this->visibility->allowsMany($actor, $studentIds, 'fees.collect');
        $remind = $this->visibility->allowsMany($actor, $studentIds, 'fees.remind');
        $pending = $this->pendingInvoiceIds(array_values($invoices->pluck('id')->map(fn ($id) => (int) $id)->all()));

        $rows = $invoices->map(fn (FeeInvoice $invoice) => new DueRowData(
            ulid: $invoice->ulid,
            student_ulid: (string) $invoice->getAttribute('r_student_ulid'),
            student_name: trim($invoice->getAttribute('r_first').' '.$invoice->getAttribute('r_last')),
            admission_number: (string) $invoice->getAttribute('r_admission'),
            section_label: $invoice->getAttribute('r_class').'-'.$invoice->getAttribute('r_section'),
            campus_name: (string) $invoice->getAttribute('r_campus'),
            installment_label: (string) $invoice->getAttribute('r_installment'),
            due_on: $invoice->due_on,
            paid_paise: $invoice->paid_paise,
            balance_paise: $invoice->balancePaise(),
            status: $invoice->status($today),
            payment_pending: isset($pending[$invoice->id]),
            reminded_today: $invoice->last_reminded_on === $today,
            can_collect: $collect[$invoice->student_id] ?? false,
            can_remind: $remind[$invoice->student_id] ?? false,
        ))->all();

        return new DuesScreenData(
            rows: array_values($rows),
            summary: new DuesSummaryData($total, (int) ($totals->students ?? 0), (int) ($totals->balance ?? 0), (int) ($totals->overdue ?? 0)),
            total: $total,
            from: $total === 0 ? 0 : ($page - 1) * self::PER_PAGE + 1,
            to: $total === 0 ? 0 : ($page - 1) * self::PER_PAGE + count($rows),
            current_page: $page,
            last_page: $lastPage,
            sections: $this->sectionOptions($actor, $year['key'], $filters->campusUlid),
            installments: $this->installmentOptions($year['key']),
            academic_year: $year['name'],
            today: $today,
            has_schedule: $this->hasSchedule($year['key']),
            can_export: $this->visibility->scopeSet($actor, 'fees.view')->allows(),
        );
    }

    /**
     * Every row the filters match, as CSV lines (header first). Guardian phone
     * numbers appear only for students the person may see sensitive details of.
     *
     * @return list<list<string>>
     */
    public function exportRows(Identity $actor, DueFilters $filters): array
    {
        $year = $this->years->find();
        $today = CalendarDate::today();
        $base = $year === null ? null : $this->base($actor, $filters, $year['key'], $today);

        $rows = [['Admission no', 'Student', 'Class', 'Campus', 'Installment', 'Due date', 'Net (₹)', 'Paid (₹)', 'Balance (₹)', 'Status', 'Primary guardian', 'Phone']];

        if ($base === null) {
            return $rows;
        }

        $invoices = $this->order($base, $filters->sort)->addSelect(['st.first_name as r_first', 'st.last_name as r_last', 'st.admission_number as r_admission',
            's.name as r_section', 'sc.name as r_class', 'c.name as r_campus', 'i.label as r_installment'])->get();

        $studentIds = array_values($invoices->pluck('student_id')->unique()->map(fn ($id) => (int) $id)->all());
        $guardians = $this->primaryGuardians($studentIds);
        $mayPhone = $this->visibility->allowsMany($actor, $studentIds, 'students.view_sensitive');

        foreach ($invoices as $invoice) {
            $guardian = $guardians[$invoice->student_id] ?? null;

            $rows[] = [
                (string) $invoice->getAttribute('r_admission'),
                trim($invoice->getAttribute('r_first').' '.$invoice->getAttribute('r_last')),
                $invoice->getAttribute('r_class').'-'.$invoice->getAttribute('r_section'),
                (string) $invoice->getAttribute('r_campus'),
                (string) $invoice->getAttribute('r_installment'),
                $invoice->due_on,
                Money::plain($invoice->net_paise),
                Money::plain($invoice->paid_paise),
                Money::plain($invoice->balancePaise()),
                $invoice->status($today)->label(),
                $guardian['name'] ?? '',
                // Written without "+", as the Students export does: a leading plus would be taken for a formula.
                ($mayPhone[$invoice->student_id] ?? false) ? ltrim($guardian['phone'] ?? '', '+') : '',
            ];
        }

        return $rows;
    }

    private function empty(string $yearName, string $today, bool $hasSchedule, Identity $actor): DuesScreenData
    {
        return new DuesScreenData([], new DuesSummaryData(0, 0, 0, 0), 0, 0, 0, 1, 1, [], [], $yearName, $today, $hasSchedule,
            $this->visibility->scopeSet($actor, 'fees.view')->allows());
    }

    /**
     * @return Builder<FeeInvoice>|null
     */
    private function base(Identity $actor, DueFilters $filters, int $yearKey, string $today): ?Builder
    {
        $query = $this->visibility->invoices($actor, 'fees.view');

        if ($query === null) {
            return null;
        }

        $query->join('fee_installments as i', 'i.id', '=', 'fee_invoices.installment_id')
            ->where('fee_invoices.academic_year_id', $yearKey)
            ->where('st.status', 'enrolled')
            ->whereColumn('fee_invoices.paid_paise', '<', 'fee_invoices.net_paise');

        if ($filters->campusUlid !== null) {
            $query->where('c.ulid', $filters->campusUlid);
        }

        if ($filters->sectionUlid !== null) {
            $query->where('s.ulid', $filters->sectionUlid);
        }

        if ($filters->installmentUlid !== null) {
            $query->where('i.ulid', $filters->installmentUlid);
        }

        $horizon = (new DateTimeImmutable($today))->modify('+'.InvoiceStatus::DUE_SOON_DAYS.' days')->format('Y-m-d');

        match ($filters->status) {
            'overdue' => $query->where('fee_invoices.due_on', '<', $today),
            'due' => $query->where('fee_invoices.paid_paise', 0)->whereBetween('fee_invoices.due_on', [$today, $horizon]),
            'partial' => $query->where('fee_invoices.paid_paise', '>', 0),
            'upcoming' => $query->where('fee_invoices.paid_paise', 0)->where('fee_invoices.due_on', '>', $horizon),
            default => null,
        };

        $search = mb_strtolower(trim($filters->search));

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

            $query->where(function (Builder $where) use ($like) {
                $where->whereRaw("lower(st.first_name || ' ' || st.last_name) like ?", [$like])
                    ->orWhereRaw('lower(st.admission_number) like ?', [$like]);
            });
        }

        return $query;
    }

    /**
     * @param  Builder<FeeInvoice>  $query
     * @return Builder<FeeInvoice>
     */
    private function order(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'balance' => $query->orderByRaw('(fee_invoices.net_paise - fee_invoices.paid_paise) desc')->orderBy('fee_invoices.due_on')->orderBy('fee_invoices.id'),
            'class' => $query->orderBy('c.name')->orderBy('sc.level')->orderBy('s.name')->orderBy('e.roll_number')->orderBy('fee_invoices.due_on')->orderBy('fee_invoices.id'),
            default => $query->orderBy('fee_invoices.due_on')->orderByRaw('(fee_invoices.net_paise - fee_invoices.paid_paise) desc')->orderBy('fee_invoices.id'),
        };
    }

    /**
     * @param  list<int>  $invoiceIds
     * @return array<int, true>
     */
    private function pendingInvoiceIds(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $ids = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('fee_payment_allocations as a')
            ->join('fee_payments as p', 'p.id', '=', 'a.fee_payment_id')
            ->where('p.status', PaymentStatus::Pending->value)
            ->whereIn('a.fee_invoice_id', $invoiceIds)
            ->pluck('a.fee_invoice_id');

        $set = [];

        foreach ($ids as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, array{name: string, phone: string}>
     */
    private function primaryGuardians(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('guardian_student as gs')
            ->join('guardians as g', 'g.id', '=', 'gs.guardian_id')
            ->whereIn('gs.student_id', $studentIds)
            ->orderBy('gs.is_primary')->orderByDesc('gs.id')
            ->get(['gs.student_id', 'g.name', 'g.phone']);

        $result = [];

        // Ordered so the primary guardian is written last and wins.
        foreach ($rows as $row) {
            $result[(int) $row->student_id] = ['name' => (string) $row->name, 'phone' => (string) $row->phone];
        }

        return $result;
    }

    /**
     * @return list<FeeOptionData>
     */
    private function sectionOptions(Identity $actor, int $yearKey, ?string $campusUlid): array
    {
        $query = $this->visibility->invoices($actor, 'fees.view');

        if ($query === null) {
            return [];
        }

        $query->where('fee_invoices.academic_year_id', $yearKey)->where('st.status', 'enrolled');

        if ($campusUlid !== null) {
            $query->where('c.ulid', $campusUlid);
        }

        $rows = $query->toBase()->cloneWithout(['columns', 'orders'])
            ->select(['s.ulid as section_ulid', 's.name as section_name', 'sc.name as class_name', 'sc.level', 'c.name as campus_name'])
            ->distinct()->orderBy('c.name')->orderBy('sc.level')->orderBy('s.name')->get();

        $campuses = $rows->pluck('campus_name')->unique()->count();

        return array_values($rows->map(fn ($row) => new FeeOptionData(
            (string) $row->section_ulid,
            $row->class_name.'-'.$row->section_name.($campuses > 1 ? ' · '.$row->campus_name : ''),
        ))->all());
    }

    /**
     * @return list<FeeOptionData>
     */
    private function installmentOptions(int $yearKey): array
    {
        return array_values(FeeInstallment::query()->where('academic_year_id', $yearKey)->orderBy('position')->get()
            ->map(fn (FeeInstallment $installment) => new FeeOptionData($installment->ulid, $installment->label))->all());
    }

    private function hasSchedule(int $yearKey): bool
    {
        return FeeInstallment::query()->where('academic_year_id', $yearKey)->exists();
    }
}
