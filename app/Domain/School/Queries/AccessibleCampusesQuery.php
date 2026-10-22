<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\CampusOptionData;
use App\Domain\School\Models\Campus;

/**
 * Campuses a person can work in, for the top-bar campus switcher: the union of
 * the campuses their school-structure and student scopes reach.
 */
class AccessibleCampusesQuery
{
    /** Permissions whose scope decides which campuses show up in the switcher. */
    private const PERMISSIONS = ['structure.view', 'students.view'];

    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * @return list<CampusOptionData>
     */
    public function get(Identity $identity): array
    {
        $schoolWide = false;
        $campusUlids = [];
        $sectionUlids = [];

        foreach (self::PERMISSIONS as $permission) {
            $set = $this->scopes->get($identity, $permission);
            $schoolWide = $schoolWide || $set->isSchoolWide();

            foreach ($set->scopes as $scope) {
                if (in_array($scope->type, [ScopeType::Campus, ScopeType::Wing], true)) {
                    array_push($campusUlids, ...$scope->campusUlids());
                }

                array_push($sectionUlids, ...$scope->sectionUlids());
            }
        }

        $query = Campus::query()->where('status', 'active')->orderBy('name');

        if (! $schoolWide) {
            if ($campusUlids === [] && $sectionUlids === []) {
                return [];
            }

            $query->where(function ($where) use ($campusUlids, $sectionUlids) {
                $where->whereIn('ulid', array_values(array_unique($campusUlids)));

                if ($sectionUlids !== []) {
                    $where->orWhereHas('sections', fn ($sections) => $sections->whereIn('ulid', array_values(array_unique($sectionUlids))));
                }
            });
        }

        return array_values($query->get()
            ->map(fn (Campus $campus) => new CampusOptionData($campus->ulid, $campus->name))
            ->all());
    }
}
