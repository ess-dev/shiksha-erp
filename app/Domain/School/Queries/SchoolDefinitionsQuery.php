<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\StructureData;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Subject;

/**
 * School-wide definitions (academic years, classes, subjects), visible to
 * anyone who holds `structure.view` in any scope.
 */
class SchoolDefinitionsQuery
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * @return array<int, StructureData> Newest first
     */
    public function academicYears(Identity $identity): array
    {
        if (! $this->mayView($identity)) {
            return [];
        }

        return AcademicYear::query()->orderByDesc('starts_on')->get()
            ->map(fn (AcademicYear $year) => StructureData::academicYear($year->ulid, $year->name, $year->starts_on, $year->ends_on, $year->is_current))
            ->values()
            ->all();
    }

    public function currentAcademicYear(Identity $identity): ?StructureData
    {
        $year = $this->mayView($identity) ? AcademicYear::query()->where('is_current', true)->first() : null;

        return $year === null
            ? null
            : StructureData::academicYear($year->ulid, $year->name, $year->starts_on, $year->ends_on, $year->is_current);
    }

    /**
     * @return array<int, StructureData> Lowest level first
     */
    public function classes(Identity $identity): array
    {
        if (! $this->mayView($identity)) {
            return [];
        }

        return SchoolClass::query()->orderBy('level')->get()
            ->map(fn (SchoolClass $class) => StructureData::schoolClass($class->ulid, $class->name, $class->level))
            ->values()
            ->all();
    }

    /**
     * @return array<int, StructureData>
     */
    public function subjects(Identity $identity): array
    {
        if (! $this->mayView($identity)) {
            return [];
        }

        return Subject::query()->orderBy('name')->get()
            ->map(fn (Subject $subject) => StructureData::subject($subject->ulid, $subject->name, $subject->code, $subject->status))
            ->values()
            ->all();
    }

    private function mayView(Identity $identity): bool
    {
        return $this->scopes->get($identity, 'structure.view')->allows();
    }
}
