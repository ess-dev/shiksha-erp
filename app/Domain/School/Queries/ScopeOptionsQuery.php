<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\CampusOptionData;
use App\Domain\School\Data\ClassLevelOptionData;
use App\Domain\School\Data\ScopeOptionsData;
use App\Domain\School\Data\SectionOptionData;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\SchoolClass;

/**
 * Campuses, class levels and current-year sections to pick from when giving a
 * role a scope. Not narrowed by the viewer's reach: the Access guard refuses a
 * scope wider than the giver's own, so the callers need `roles.assign`.
 */
class ScopeOptionsQuery
{
    public function __construct(private readonly SectionDirectoryQuery $sections) {}

    public function get(): ScopeOptionsData
    {
        return new ScopeOptionsData(
            campuses: array_values(Campus::query()->where('status', 'active')->orderBy('name')->get()
                ->map(fn (Campus $campus) => new CampusOptionData($campus->ulid, $campus->name))->all()),
            sections: array_map(fn ($section) => new SectionOptionData(
                $section->ulid, $section->label(), $section->campusName, $section->campusUlid,
            ), $this->sections->currentYear()),
            levels: array_values(SchoolClass::query()->orderBy('level')->get()
                ->map(fn (SchoolClass $class) => new ClassLevelOptionData($class->level, $class->name))->all()),
        );
    }
}
