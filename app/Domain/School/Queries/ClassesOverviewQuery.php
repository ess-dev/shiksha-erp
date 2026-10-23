<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Data\StaffOptionData;
use App\Domain\Access\Queries\ActiveStaffQuery;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Attendance\Queries\SectionRegisterStatusQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\ClassCampusData;
use App\Domain\School\Data\ClassesOverviewData;
use App\Domain\School\Data\ClassLevelOptionData;
use App\Domain\School\Data\ClassSectionData;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Queries\SectionStrengthQuery;
use App\Tenancy\Tenant;

/**
 * The Classes and sections screen for the current academic year: the sections
 * the person may see (their `structure.view` scope, narrowed to the campus
 * chosen in the top bar), their strength and today's register, and what the
 * person may add (their `structure.manage` scope).
 */
class ClassesOverviewQuery
{
    private const SECTION_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private readonly SectionsQuery $sections,
        private readonly SchoolDefinitionsQuery $definitions,
        private readonly CampusesQuery $campuses,
        private readonly PermissionScopesQuery $scopes,
        private readonly ActiveStaffQuery $staff,
        private readonly SectionStrengthQuery $strength,
        private readonly SectionRegisterStatusQuery $registers,
    ) {}

    public function get(Identity $identity, ?string $campusUlid): ClassesOverviewData
    {
        $year = $this->definitions->currentAcademicYear($identity);
        $manage = $this->scopes->get($identity, 'structure.manage');
        $tenant = Tenant::current();
        $hasAttendance = $tenant instanceof Tenant && $tenant->hasModule('attendance');

        $sections = $year === null ? collect() : $this->sections->scopedSections($identity, $year->ulid, $campusUlid);
        $keys = array_values($sections->pluck('id')->map(fn ($id) => (int) $id)->all());
        $strength = $this->strength->forSections($keys);
        $registers = $hasAttendance ? $this->registers->forDay($keys, CalendarDate::today()) : [];

        $taken = [];

        foreach ($sections as $section) {
            $taken[$section->campus->ulid][$section->schoolClass->ulid][] = $section->name;
        }

        $cards = $sections->map(function (Section $section) use ($strength, $registers, $manage, $taken) {
            $campus = $section->campus;
            $class = $section->schoolClass;
            $allowed = $section->status === 'active' && $campus->isActive() && $manage->allows(new ScopeTarget($campus->ulid, $class->level));
            $register = $registers[$section->id] ?? null;

            return new ClassSectionData(
                ulid: $section->ulid,
                name: $section->name,
                class_ulid: $class->ulid,
                class_name: $class->name,
                class_level: $class->level,
                campus_ulid: $campus->ulid,
                room: $section->room,
                capacity: $section->capacity,
                students: $strength[$section->id]['students'] ?? 0,
                girls: $strength[$section->id]['girls'] ?? 0,
                class_teacher_name: $section->getAttribute('class_teacher_name'),
                register_saved_at: $register['saved_at'] ?? null,
                register_absent: $register['absent'] ?? 0,
                can_manage: $allowed,
                next_section_name: $allowed ? $this->nextName($taken[$campus->ulid][$class->ulid] ?? []) : null,
            );
        })->all();

        return new ClassesOverviewData(
            academic_year_ulid: $year?->ulid,
            academic_year_name: $year === null ? null : (string) $year->fields['name'],
            campus_name: $campusUlid === null ? null : $this->campusName($identity, $campusUlid),
            timezone: (string) config('shiksha.timezone'),
            has_attendance: $hasAttendance,
            can_manage: $year !== null && $manage->allows(),
            campuses: $year === null ? [] : $this->campusGroups($identity, $campusUlid, $taken, $manage),
            sections: array_values($cards),
            free_teachers: $year === null ? [] : $this->freeTeachers($year->ulid),
            class_ulids: SchoolClass::query()->orderBy('level')->pluck('ulid', 'level')
                ->mapWithKeys(fn ($ulid, $level) => [(string) $level => (string) $ulid])->all(),
        );
    }

    /**
     * @param  array<string, array<string, list<string>>>  $taken  Section names by campus then class
     * @return list<ClassCampusData>
     */
    private function campusGroups(Identity $identity, ?string $campusUlid, array $taken, ScopeSet $manage): array
    {
        $classes = SchoolClass::query()->orderBy('level')->get();
        $groups = [];

        foreach ($this->campuses->get($identity) as $campus) {
            if (($campusUlid !== null && $campus->ulid !== $campusUlid) || $campus->fields['status'] !== 'active') {
                continue;
            }

            $addable = [];

            foreach ($classes as $class) {
                if (isset($taken[$campus->ulid][$class->ulid])) {
                    continue;
                }

                if ($manage->allows(new ScopeTarget($campus->ulid, $class->level))) {
                    $addable[] = new ClassLevelOptionData($class->level, $class->name);
                }
            }

            $groups[] = new ClassCampusData($campus->ulid, (string) $campus->fields['name'], $addable);
        }

        return $groups;
    }

    /**
     * @return list<StaffOptionData>
     */
    private function freeTeachers(string $yearUlid): array
    {
        $leading = Section::query()
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->join('staff', 'staff.id', '=', 'sections.class_teacher_staff_id')
            ->where('academic_years.ulid', $yearUlid)
            ->where('sections.status', 'active')
            ->pluck('staff.ulid')
            ->all();

        return array_values(array_filter($this->staff->options(), fn (StaffOptionData $person) => ! in_array($person->ulid, $leading, true)));
    }

    /**
     * The first unused letter, "A" for a class with no sections yet.
     *
     * @param  list<string>  $used
     */
    private function nextName(array $used): ?string
    {
        foreach (str_split(self::SECTION_LETTERS) as $letter) {
            if (! in_array($letter, $used, true)) {
                return $letter;
            }
        }

        return null;
    }

    private function campusName(Identity $identity, string $campusUlid): ?string
    {
        foreach ($this->campuses->get($identity) as $campus) {
            if ($campus->ulid === $campusUlid) {
                return (string) $campus->fields['name'];
            }
        }

        return null;
    }
}
