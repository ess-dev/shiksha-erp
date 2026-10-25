<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Enums\ConcessionKind;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates the invoices an academic year's enrolled students are owed: one per
 * installment, priced from the fee group of the student's class. Called from
 * inside Actions (it writes, so it must run in their transaction and they own
 * the audit event). Safe to repeat: an installment already billed is skipped.
 *
 * A student admitted mid-year is billed only for terms that have not ended by
 * the day they were admitted (the prototype also adds a one-time admission
 * fee; that is not built).
 */
class InvoiceBiller
{
    /**
     * @param  list<int>|null  $studentIds  internal ids, or null for every enrolled student of the year
     * @param  bool  $allTerms  bill every installment whatever the admission date (demo data, imports of old records)
     * @return array{invoices: int, students: int}
     */
    public function bill(int $yearId, ?array $studentIds = null, bool $allTerms = false, ?int $classId = null): array
    {
        $installments = FeeInstallment::query()->where('academic_year_id', $yearId)->orderBy('position')->get();

        if ($installments->isEmpty()) {
            return ['invoices' => 0, 'students' => 0];
        }

        $groups = FeeGroup::query()->where('academic_year_id', $yearId)->get()->keyBy('id');
        $groupOfClass = FeeGroupClass::query()->where('academic_year_id', $yearId)->pluck('fee_group_id', 'school_class_id');
        $sibling = FeeConcession::query()->where('kind', ConcessionKind::Sibling)->first();

        $students = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students as st')
            ->join('enrollments as e', function ($join) use ($yearId) {
                $join->on('e.student_id', '=', 'st.id')->where('e.academic_year_id', $yearId)->where('e.is_current', true);
            })
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->where('st.status', 'enrolled')
            ->when($studentIds !== null, fn ($query) => $query->whereIn('st.id', $studentIds))
            ->when($classId !== null, fn ($query) => $query->where('s.school_class_id', $classId))
            ->orderBy('st.id')
            ->get(['st.id', 'st.admitted_on', 's.school_class_id']);

        if ($students->isEmpty()) {
            return ['invoices' => 0, 'students' => 0];
        }

        $billedAlready = FeeInvoice::query()->where('academic_year_id', $yearId)
            ->whereIn('student_id', $students->pluck('id')->all())
            ->get(['student_id', 'installment_id'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('installment_id')->all());

        $withSibling = $sibling === null ? [] : $this->studentsWithOlderSibling(array_values($students->pluck('id')->map(fn ($id) => (int) $id)->all()));

        $created = 0;
        $billedStudents = [];

        foreach ($students as $student) {
            $group = $groups[$groupOfClass[$student->school_class_id] ?? 0] ?? null;

            // A group with no fee heads yet has nothing to bill; students are billed when its heads are saved.
            if ($group === null || $group->heads === []) {
                continue;
            }

            foreach ($installments as $installment) {
                if (in_array($installment->id, $billedAlready[$student->id] ?? [], true)) {
                    continue;
                }

                if (! $allTerms && $installment->ends_on < (string) $student->admitted_on) {
                    continue;
                }

                $price = InvoicePricing::price($group->heads, isset($withSibling[(int) $student->id]) ? $sibling : null);

                FeeInvoice::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'student_id' => $student->id,
                    'academic_year_id' => $yearId,
                    'installment_id' => $installment->id,
                    'fee_group_id' => $group->id,
                    'due_on' => $installment->due_on,
                    'heads' => $group->heads,
                    'gross_paise' => $price['gross_paise'],
                    'concession_paise' => $price['concession_paise'],
                    'concession_note' => $price['note'],
                    'net_paise' => $price['net_paise'],
                    'paid_paise' => 0,
                ]);

                $created++;
                $billedStudents[(int) $student->id] = true;
            }
        }

        return ['invoices' => $created, 'students' => count($billedStudents)];
    }

    /**
     * Students who share a guardian with an enrolled student that has a lower
     * id (admitted earlier): the second child onward.
     *
     * @param  list<int>  $studentIds
     * @return array<int, true>
     */
    private function studentsWithOlderSibling(array $studentIds): array
    {
        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('guardian_student as mine')
            ->join('guardian_student as theirs', function ($join) {
                $join->on('theirs.guardian_id', '=', 'mine.guardian_id')->whereColumn('theirs.student_id', '<', 'mine.student_id');
            })
            ->join('students as older', function ($join) {
                $join->on('older.id', '=', 'theirs.student_id')->where('older.status', 'enrolled');
            })
            ->whereIn('mine.student_id', $studentIds)
            ->distinct()
            ->pluck('mine.student_id');

        $set = [];

        foreach ($rows as $id) {
            $set[(int) $id] = true;
        }

        return $set;
    }
}
