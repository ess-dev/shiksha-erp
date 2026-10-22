<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\StructureData;
use App\Domain\School\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class SectionsQuery
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /**
     * Sections the identity may see, filtered by their `structure.view` scope
     * (school, campuses, wing, or named sections) and the optional filters.
     * Nothing is returned without the permission.
     *
     * @return LengthAwarePaginator<int, StructureData>
     */
    public function paginate(
        Identity $identity,
        ?string $academicYearUlid = null,
        ?string $campusUlid = null,
        ?string $classUlid = null,
        int $perPage = 30,
    ): LengthAwarePaginator {
        $scopes = $this->scopes->get($identity, 'structure.view');

        if ($scopes->isEmpty()) {
            return new Paginator([], 0, $perPage);
        }

        $query = Section::query()
            ->with(['campus', 'schoolClass', 'academicYear'])
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->select('sections.*')
            ->orderBy('school_classes.level')
            ->orderBy('sections.name');

        if (! $scopes->isSchoolWide()) {
            $query->where(function ($where) use ($scopes) {
                foreach ($scopes->scopes as $scope) {
                    match ($scope->type) {
                        ScopeType::Campus => $where->orWhereHas('campus', fn ($q) => $q->whereIn('ulid', $scope->campusUlids())),
                        ScopeType::Wing => $where->orWhere(function ($wing) use ($scope) {
                            [$from, $to] = $scope->levelRange() ?? [0, -1];

                            $wing->whereHas('campus', fn ($q) => $q->whereIn('ulid', $scope->campusUlids()))
                                ->whereBetween('school_classes.level', [$from, $to]);
                        }),
                        ScopeType::Sections => $where->orWhereIn('sections.ulid', $scope->sectionUlids()),
                        ScopeType::School => null,
                    };
                }
            });
        }

        if ($academicYearUlid !== null) {
            $query->whereHas('academicYear', fn ($q) => $q->where('ulid', $academicYearUlid));
        }

        if ($campusUlid !== null) {
            $query->whereHas('campus', fn ($q) => $q->where('ulid', $campusUlid));
        }

        if ($classUlid !== null) {
            $query->where('school_classes.ulid', $classUlid);
        }

        return $query->paginate($perPage)->through(fn (Section $section) => StructureData::section(
            $section->ulid,
            $section->name,
            $section->capacity,
            $section->status,
            $section->academicYear->ulid,
            $section->campus->ulid,
            $section->campus->name,
            $section->schoolClass->ulid,
            $section->schoolClass->name,
            $section->schoolClass->level,
        ));
    }
}
