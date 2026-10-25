<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Data\ConcessionData;
use App\Domain\Fees\Data\FeeClassRowData;
use App\Domain\Fees\Data\FeeGroupData;
use App\Domain\Fees\Data\FeeHeadData;
use App\Domain\Fees\Data\FeeStructureData;
use App\Domain\Fees\Data\InstallmentData;
use App\Domain\Fees\Enums\ConcessionKind;
use App\Domain\Fees\FeeClasses;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\InvoicePricing;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeGroupClass;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\AcademicSessionsQuery;
use App\Domain\Shared\CalendarDate;

/**
 * The Fee structure screen for the current academic year: which classes share
 * a fee group, each group's heads, the installment schedule and concessions.
 * Anyone with `fees.view` may read it; changing needs `fee_structures.manage`
 * over the whole school.
 */
class FeeStructureQuery
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly AcademicSessionsQuery $years,
        private readonly FeeClasses $classes,
    ) {}

    public function get(Identity $actor): FeeStructureData
    {
        $year = $this->years->find();
        $today = CalendarDate::today();
        $canManage = $this->visibility->scopeSet($actor, 'fee_structures.manage')->isSchoolWide();

        if ($year === null) {
            return new FeeStructureData('', $today, false, [], [], [], [], [], [], $canManage);
        }

        $groups = FeeGroup::query()->where('academic_year_id', $year['key'])->orderBy('id')->get();
        $groupOfClass = FeeGroupClass::query()->where('academic_year_id', $year['key'])->pluck('fee_group_id', 'school_class_id');
        $counts = $this->classes->enrolledCounts($year['key']);
        $installments = FeeInstallment::query()->where('academic_year_id', $year['key'])->orderBy('position')->get();
        $billedGroupIds = FeeInvoice::query()->whereIn('fee_group_id', $groups->pluck('id')->all())->distinct()->pluck('fee_group_id')->all();

        $classRows = [];
        $withoutGroup = [];
        $classNamesOfGroup = [];
        $studentsOfGroup = [];

        foreach ($this->classes->all() as $class) {
            $group = $groups->firstWhere('id', $groupOfClass[$class['id']] ?? 0);
            $students = $counts[$class['id']] ?? 0;

            $classRows[] = new FeeClassRowData($class['ulid'], $class['name'], $group?->ulid, $group?->termPaise(), $students);

            if ($group === null) {
                $withoutGroup[] = $class['name'];

                continue;
            }

            $classNamesOfGroup[$group->id][] = $class['name'];
            $studentsOfGroup[$group->id] = ($studentsOfGroup[$group->id] ?? 0) + $students;
        }

        $termCount = max(1, $installments->count());
        $groupRows = $groups->map(fn (FeeGroup $group) => new FeeGroupData(
            ulid: $group->ulid,
            name: $group->name,
            heads: array_map(fn (array $head) => new FeeHeadData($head['name'], (int) $head['amount_paise']), $group->heads),
            term_paise: $group->termPaise(),
            year_paise: $group->termPaise() * $termCount,
            class_names: $classNamesOfGroup[$group->id] ?? [],
            students: $studentsOfGroup[$group->id] ?? 0,
            can_delete: ! isset($classNamesOfGroup[$group->id]) && ! in_array($group->id, $billedGroupIds, true),
        ))->all();

        $headNames = [];

        foreach ($groups as $group) {
            foreach ($group->heads as $head) {
                $headNames[mb_strtolower($head['name'])] ??= $head['name'];
            }
        }

        $siblingStudents = FeeInvoice::query()->where('academic_year_id', $year['key'])->where('concession_paise', '>', 0)->distinct()->count('student_id');

        $concessions = FeeConcession::query()->orderBy('id')->get()->map(fn (FeeConcession $concession) => new ConcessionData(
            ulid: $concession->ulid,
            name: $concession->name,
            description: InvoicePricing::describe($concession).($concession->kind === ConcessionKind::Sibling ? ' · applied automatically to the second child onward' : ''),
            is_automatic_sibling: $concession->kind === ConcessionKind::Sibling,
            needs_approval: $concession->needs_approval,
            students: $concession->kind === ConcessionKind::Sibling ? $siblingStudents : 0,
        ))->all();

        return new FeeStructureData(
            academic_year: $year['name'],
            today: $today,
            has_schedule: $installments->isNotEmpty(),
            classes: $classRows,
            groups: array_values($groupRows),
            installments: array_values($installments->map(fn (FeeInstallment $installment) => new InstallmentData(
                $installment->ulid,
                $installment->label,
                $installment->due_on,
                $installment->due_on < $today,
            ))->all()),
            concessions: array_values($concessions),
            head_names: array_values($headNames),
            classes_without_group: $withoutGroup,
            can_manage: $canManage,
        );
    }
}
