<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\StructureData;
use App\Domain\School\Models\Campus;

class CampusesQuery
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * The campuses the identity may see: all of them for a school-wide scope,
     * otherwise those a campus or wing scope names or where a named section is.
     *
     * @return array<int, StructureData>
     */
    public function get(Identity $identity): array
    {
        $scopes = $this->scopes->get($identity, 'structure.view');

        if ($scopes->isEmpty()) {
            return [];
        }

        $query = Campus::query()->orderBy('name');

        if (! $scopes->isSchoolWide()) {
            $query->where(function ($where) use ($scopes) {
                foreach ($this->campusUlids($scopes) as $ulid) {
                    $where->orWhere('ulid', $ulid);
                }

                $sections = $this->sectionUlids($scopes);

                if ($sections !== []) {
                    $where->orWhereHas('sections', fn ($sectionQuery) => $sectionQuery->whereIn('ulid', $sections));
                }
            });
        }

        return $query->get()
            ->map(fn (Campus $campus) => StructureData::campus($campus->ulid, $campus->name, $campus->code, $campus->status))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function campusUlids(ScopeSet $scopes): array
    {
        $ulids = [];

        foreach ($scopes->scopes as $scope) {
            if (in_array($scope->type, [ScopeType::Campus, ScopeType::Wing], true)) {
                array_push($ulids, ...$scope->campusUlids());
            }
        }

        return array_values(array_unique($ulids));
    }

    /**
     * @return list<string>
     */
    private function sectionUlids(ScopeSet $scopes): array
    {
        $ulids = [];

        foreach ($scopes->scopes as $scope) {
            array_push($ulids, ...$scope->sectionUlids());
        }

        return array_values(array_unique($ulids));
    }
}
