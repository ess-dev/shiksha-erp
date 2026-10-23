<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Looks sections up for other modules (students, later attendance and fees)
 * without exposing the School models. No scope is applied here: the caller
 * checks each section's scope target against the person's permission scope.
 */
class SectionDirectoryQuery
{
    public function find(string $ulid): ?SectionRefData
    {
        return $this->base()->where('sections.ulid', $ulid)->get()->map($this->ref(...))->first();
    }

    /**
     * Sections by internal key, for modules that store a section key (such as an enrolment).
     *
     * @param  list<int>  $keys
     * @return array<int, SectionRefData>
     */
    public function byKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $refs = [];

        foreach ($this->base()->whereIn('sections.id', array_values(array_unique($keys)))->get() as $row) {
            $refs[$row->id] = $this->ref($row);
        }

        return $refs;
    }

    /**
     * Active sections of the current academic year, optionally of one class level.
     *
     * @return list<SectionRefData>
     */
    public function currentYear(?int $classLevel = null): array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        if ($year === null) {
            return [];
        }

        $query = $this->base()->where('sections.academic_year_id', $year->id)->where('sections.status', 'active');

        if ($classLevel !== null) {
            $query->where('school_classes.level', $classLevel);
        }

        return array_values($query->get()->map($this->ref(...))->all());
    }

    /**
     * Sections of one (usually past) academic year, for read-only filters.
     *
     * @return list<SectionRefData>
     */
    public function inYear(int $yearKey): array
    {
        return array_values($this->base()->where('sections.academic_year_id', $yearKey)->get()->map($this->ref(...))->all());
    }

    /**
     * The current academic year's key and ulid, for new enrolments.
     *
     * @return array{key: int, ulid: string, name: string, starts_on: string}|null
     */
    public function currentYearRef(): ?array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        return $year === null ? null : ['key' => $year->id, 'ulid' => $year->ulid, 'name' => $year->name, 'starts_on' => $year->starts_on];
    }

    /**
     * @return Builder<Section>
     */
    private function base(): Builder
    {
        $tenant = config('multitenancy.tenant_database_connection_name');

        return Section::query()
            ->join('campuses', 'campuses.id', '=', 'sections.campus_id')
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->leftJoin('staff as class_teacher', 'class_teacher.id', '=', 'sections.class_teacher_staff_id')
            ->select([
                'sections.id', 'sections.ulid', 'sections.name', 'sections.capacity', 'sections.status',
                'sections.academic_year_id', 'sections.class_teacher_staff_id', 'academic_years.ulid as year_ulid',
                'class_teacher.ulid as class_teacher_ulid', 'class_teacher.full_name as class_teacher_name',
                'campuses.ulid as campus_ulid', 'campuses.name as campus_name',
                'school_classes.ulid as class_ulid', 'school_classes.name as class_name', 'school_classes.level as class_level',
                DB::connection($tenant)->raw('(select count(*) from enrollments where enrollments.section_id = sections.id and enrollments.is_current) as enrolled_count'),
            ])
            ->orderBy('school_classes.level')
            ->orderBy('campuses.name')
            ->orderBy('sections.name');
    }

    private function ref(Section $row): SectionRefData
    {
        return new SectionRefData(
            sectionKey: $row->id,
            ulid: $row->ulid,
            name: $row->name,
            capacity: $row->capacity,
            enrolledCount: (int) $row->getAttribute('enrolled_count'),
            academicYearKey: (int) $row->academic_year_id,
            academicYearUlid: (string) $row->getAttribute('year_ulid'),
            campusUlid: (string) $row->getAttribute('campus_ulid'),
            campusName: (string) $row->getAttribute('campus_name'),
            classUlid: (string) $row->getAttribute('class_ulid'),
            className: (string) $row->getAttribute('class_name'),
            classLevel: (int) $row->getAttribute('class_level'),
            status: $row->status,
            classTeacherStaffKey: $row->getAttribute('class_teacher_staff_id') === null ? null : (int) $row->getAttribute('class_teacher_staff_id'),
            classTeacherUlid: $row->getAttribute('class_teacher_ulid'),
            classTeacherName: $row->getAttribute('class_teacher_name'),
        );
    }
}
