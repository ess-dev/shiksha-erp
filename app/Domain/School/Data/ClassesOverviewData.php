<?php

namespace App\Domain\School\Data;

use App\Domain\Access\Data\StaffOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Everything the Classes and sections screen shows for the current academic
 * year.
 */
#[TypeScript]
class ClassesOverviewData extends Data
{
    /**
     * @param  list<ClassCampusData>  $campuses
     * @param  list<ClassSectionData>  $sections  Class level, then section name
     * @param  list<StaffOptionData>  $free_teachers  Active staff who lead no class this year
     * @param  array<string, string>  $class_ulids  Class ulid by class level, for adding a class at a campus
     */
    public function __construct(
        public ?string $academic_year_ulid,
        public ?string $academic_year_name,
        public ?string $campus_name,
        public string $timezone,
        public bool $has_attendance,
        public bool $can_manage,
        public array $campuses,
        public array $sections,
        public array $free_teachers,
        public array $class_ulids,
    ) {}
}
