<?php

namespace App\Domain\Fees;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Identity\Models\Identity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who may see whose fees: a student's place is their section in the invoice's
 * academic year (payments: the latest section), and the person's scope for a
 * permission has to cover it. Every list and every single-record check starts
 * here so Queries and Actions cannot disagree.
 *
 * Lists are joined as students `st`, enrolment `e`, section `s`, class `sc`
 * and campus `c`, so callers can filter and sort on them.
 */
class FeeVisibility
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    public function scopeSet(Identity $actor, string $permission): ScopeSet
    {
        return $this->scopes->get($actor, $permission);
    }

    /**
     * Invoices the person may see under a permission; null when it is not held.
     *
     * @return Builder<FeeInvoice>|null
     */
    public function invoices(Identity $actor, string $permission = 'fees.view'): ?Builder
    {
        $set = $this->scopes->get($actor, $permission);

        if ($set->isEmpty()) {
            return null;
        }

        $query = FeeInvoice::query()
            ->join('students as st', 'st.id', '=', 'fee_invoices.student_id')
            ->join('enrollments as e', function ($join) {
                $join->whereRaw('e.id = (select en.id from enrollments en where en.student_id = fee_invoices.student_id and en.academic_year_id = fee_invoices.academic_year_id order by en.is_current desc, en.id desc limit 1)');
            })
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->select('fee_invoices.*');

        $this->limit($query, $set);

        return $query;
    }

    /**
     * Payments of students the person may see under a permission; null when it is not held.
     *
     * @return Builder<FeePayment>|null
     */
    public function payments(Identity $actor, string $permission = 'fees.view'): ?Builder
    {
        $set = $this->scopes->get($actor, $permission);

        if ($set->isEmpty()) {
            return null;
        }

        $query = FeePayment::query()
            ->join('students as st', 'st.id', '=', 'fee_payments.student_id')
            ->join('enrollments as e', function ($join) {
                $join->whereRaw('e.id = (select en.id from enrollments en where en.student_id = fee_payments.student_id order by en.is_current desc, en.id desc limit 1)');
            })
            ->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('school_classes as sc', 'sc.id', '=', 's.school_class_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->select('fee_payments.*');

        $this->limit($query, $set);

        return $query;
    }

    /**
     * Where each student sits (their latest section), for single-record checks.
     *
     * @param  list<int>  $studentIds
     * @return array<int, ScopeTarget>
     */
    public function targets(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')
            ->join('enrollments as e', function ($join) {
                $join->whereRaw('e.id = (select en.id from enrollments en where en.student_id = students.id order by en.is_current desc, en.id desc limit 1)');
            })
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

    /**
     * Whether the person holds the permission over each of these students.
     *
     * @param  list<int>  $studentIds
     * @return array<int, bool>
     */
    public function allowsMany(Identity $actor, array $studentIds, string $permission): array
    {
        $set = $this->scopes->get($actor, $permission);
        $targets = $this->targets(array_values(array_unique($studentIds)));
        $result = [];

        foreach ($studentIds as $id) {
            $result[$id] = ! $set->isEmpty() && isset($targets[$id]) && $set->allows($targets[$id]);
        }

        return $result;
    }

    /**
     * @throws FeeException 403 when the permission is not held at all, 404 when this student is out of reach
     */
    public function assertAllows(Identity $actor, int $studentId, string $permission): void
    {
        $set = $this->scopes->get($actor, $permission);

        if ($set->isEmpty()) {
            throw FeeException::notAllowed();
        }

        $target = $this->targets([$studentId])[$studentId] ?? null;

        if ($target === null || ! $set->allows($target)) {
            throw FeeException::notFound();
        }
    }

    /**
     * @throws FeeException 403 when the permission is not held in any scope
     */
    public function assertHolds(Identity $actor, string $permission): ScopeSet
    {
        $set = $this->scopes->get($actor, $permission);

        if ($set->isEmpty()) {
            throw FeeException::notAllowed();
        }

        return $set;
    }

    /**
     * Fee structure changes affect every family, so they need the permission over the whole school.
     *
     * @throws FeeException 403 otherwise
     */
    public function assertSchoolWide(Identity $actor, string $permission): void
    {
        if (! $this->assertHolds($actor, $permission)->isSchoolWide()) {
            throw FeeException::notAllowed();
        }
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function limit(Builder $query, ScopeSet $set): void
    {
        if ($set->isSchoolWide()) {
            return;
        }

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
}
