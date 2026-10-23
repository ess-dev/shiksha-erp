<?php

namespace App\Domain\Admissions;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Models\Identity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Where an application sits for access checks (its campus and class level),
 * and the query every list of applications starts from. An application has no
 * section yet, so a scope limited to specific sections reaches none of them.
 */
class AdmissionVisibility
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    public function scopes(Identity $identity, string $permission): ScopeSet
    {
        return $this->scopes->get($identity, $permission);
    }

    /**
     * Applications the identity may see under a permission, joined to campus
     * `c` and class `sc`. Null when the permission is not held.
     *
     * @return Builder<AdmissionApplication>|null
     */
    public function query(Identity $identity, string $permission = 'admissions.view'): ?Builder
    {
        $set = $this->scopes->get($identity, $permission);

        if ($set->isEmpty()) {
            return null;
        }

        $query = AdmissionApplication::query()
            ->join('campuses as c', 'c.id', '=', 'admission_applications.campus_id')
            ->join('school_classes as sc', 'sc.id', '=', 'admission_applications.school_class_id')
            ->select('admission_applications.*');

        if (! $set->isSchoolWide()) {
            $query->where(function (Builder $where) use ($set) {
                // False to start with, so a set of only section scopes matches nothing.
                $where->whereRaw('1 = 0');

                foreach ($set->scopes as $scope) {
                    match ($scope->type) {
                        ScopeType::Campus => $where->orWhereIn('c.ulid', $scope->campusUlids()),
                        ScopeType::Wing => $where->orWhere(function (Builder $wing) use ($scope) {
                            [$from, $to] = $scope->levelRange() ?? [0, -1];

                            $wing->whereIn('c.ulid', $scope->campusUlids())->whereBetween('sc.level', [$from, $to]);
                        }),
                        ScopeType::Sections, ScopeType::School => null,
                    };
                }
            });
        }

        return $query;
    }

    public function allows(Identity $identity, AdmissionApplication $application, string $permission = 'admissions.view'): bool
    {
        $set = $this->scopes->get($identity, $permission);

        return ! $set->isEmpty() && $set->allows($this->target($application));
    }

    public function target(AdmissionApplication $application): ScopeTarget
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $campus = $connection->table('campuses')->where('id', $application->campus_id)->value('ulid');
        $level = $connection->table('school_classes')->where('id', $application->school_class_id)->value('level');

        return new ScopeTarget($campus === null ? null : (string) $campus, $level === null ? null : (int) $level);
    }
}
