<?php

namespace App\Domain\Students;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Where a student sits for access checks, and the query every list of
 * students starts from. A student's place is their current section (or the
 * last one, once they have left). Everything about who may see whom lives
 * here, so Queries and Actions cannot disagree.
 */
class StudentVisibility
{
    /** The row a student's scope is read from: current enrolment first, else the latest. */
    private const SCOPE_ENROLMENT = '(select en.id from enrollments en where en.student_id = students.id order by en.is_current desc, en.id desc limit 1)';

    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * The enrolment a student is placed by: in the given academic session
     * (their latest enrolment there), else the current one, else the latest.
     */
    private function onScopeEnrolment(JoinClause $join, ?int $yearId): void
    {
        if ($yearId === null) {
            $join->whereRaw('e.id = '.self::SCOPE_ENROLMENT);

            return;
        }

        $join->whereRaw('e.id = (select en.id from enrollments en where en.student_id = students.id and en.academic_year_id = ? order by en.id desc limit 1)', [$yearId]);
    }

    public function scopes(Identity $identity, string $permission): ScopeSet
    {
        return $this->scopes->get($identity, $permission);
    }

    /**
     * With `$yearId` only students enrolled in that academic session are
     * returned, placed in the section they had then (past sessions are
     * read-only views). Students the identity may see under a permission, joined to their scope
     * enrolment as `e`, section `s`, class `sc` and campus `c` so callers can
     * filter and sort on them. Null when the permission is not held.
     *
     * @return Builder<Student>|null
     */
    public function query(Identity $identity, string $permission = 'students.view', ?int $yearId = null): ?Builder
    {
        $set = $this->scopes->get($identity, $permission);

        if ($set->isEmpty()) {
            return null;
        }

        $query = Student::query()
            ->join('enrollments as e', fn (JoinClause $join) => $this->onScopeEnrolment($join, $yearId))
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->select('students.*');

        if (! $set->isSchoolWide()) {
            $query->where(function (Builder $where) use ($set) {
                foreach ($set->scopes as $scope) {
                    match ($scope->type) {
                        ScopeType::Campus => $where->orWhereIn('c.ulid', $scope->campusUlids()),
                        ScopeType::Wing => $where->orWhere(function (Builder $wing) use ($scope) {
                            [$from, $to] = $scope->levelRange() ?? [0, -1];

                            $wing->whereIn('c.ulid', $scope->campusUlids())->whereBetween('sc.level', [$from, $to]);
                        }),
                        ScopeType::Sections => $where->orWhereIn('s.ulid', $scope->sectionUlids()),
                        ScopeType::School => null,
                    };
                }
            });
        }

        return $query;
    }

    /**
     * Whether the identity holds the permission over this student's section.
     */
    public function allows(Identity $identity, Student $student, string $permission = 'students.view'): bool
    {
        $set = $this->scopes->get($identity, $permission);

        if ($set->isEmpty()) {
            return false;
        }

        $target = $this->targets([$student->id])[$student->id] ?? null;

        return $target !== null && $set->allows($target);
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, ScopeTarget> student id => where they sit
     */
    public function targets(array $studentIds, ?int $yearId = null): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')
            ->join('enrollments as e', fn (JoinClause $join) => $this->onScopeEnrolment($join, $yearId))
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->whereIn('students.id', $studentIds)
            ->get(['students.id as student_id', 'c.ulid as campus_ulid', 'sc.level as class_level', 's.ulid as section_ulid']);

        $targets = [];

        foreach ($rows as $row) {
            $targets[(int) $row->student_id] = new ScopeTarget((string) $row->campus_ulid, (int) $row->class_level, (string) $row->section_ulid);
        }

        return $targets;
    }
}
