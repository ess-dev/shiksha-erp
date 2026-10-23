<?php

namespace App\Domain\School\Queries;

use App\Domain\Access\Queries\ActiveStaffQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionTeachersData;
use App\Domain\School\Data\SubjectClassData;
use App\Domain\School\Data\SubjectRowData;
use App\Domain\School\Data\SubjectSectionOptionData;
use App\Domain\School\Data\SubjectsOverviewData;
use App\Domain\School\Data\SubjectTeacherRowData;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\SectionSubjectTeacher;
use App\Domain\School\Models\Subject;
use App\Domain\School\SubjectGuard;
use Illuminate\Support\Facades\DB;

/**
 * The Subjects screen. Classes and the subject list are school-wide facts, so
 * anyone who can view the structure sees all of them; the sections offered
 * for teachers are the ones in the person's `structure.view` scope (narrowed
 * to the campus chosen in the top bar), and every Change/Edit/Delete flag
 * comes from their `subjects.configure` scope.
 */
class SubjectsOverviewQuery
{
    public function __construct(
        private readonly SubjectGuard $guard,
        private readonly SectionsQuery $sections,
        private readonly SchoolDefinitionsQuery $definitions,
        private readonly CampusesQuery $campuses,
        private readonly ActiveStaffQuery $staff,
    ) {}

    public function get(Identity $identity, ?string $campusUlid, ?string $sectionUlid): SubjectsOverviewData
    {
        $mayConfigure = $this->guard->mayConfigure($identity);
        $changeable = $this->guard->changeableClassIds($identity);

        $classes = SchoolClass::query()->orderBy('level')->get();
        $subjects = Subject::query()->orderBy('id')->get();

        /** @var array<int, list<int>> $subjectIdsByClass */
        $subjectIdsByClass = [];
        /** @var array<int, list<int>> $classIdsBySubject */
        $classIdsBySubject = [];

        foreach (DB::connection('tenant')->table('school_class_subject')->get() as $link) {
            $subjectIdsByClass[(int) $link->school_class_id][] = (int) $link->subject_id;
            $classIdsBySubject[(int) $link->subject_id][] = (int) $link->school_class_id;
        }

        $year = $this->definitions->currentAcademicYear($identity);
        $sectionCounts = $year === null ? [] : $this->sectionCounts($year->ulid);
        $withTeacherChoices = SectionSubjectTeacher::query()->distinct()->pluck('subject_id')->map(fn ($id) => (int) $id)->all();

        $ulidsById = $subjects->pluck('ulid', 'id')->all();
        $namesByClassId = $classes->pluck('name', 'id')->all();

        $classRows = $classes->map(fn (SchoolClass $class) => new SubjectClassData(
            ulid: $class->ulid,
            name: $class->name,
            level: $class->level,
            section_count: $sectionCounts[$class->id] ?? 0,
            subject_ulids: array_map(
                fn (int $subjectId) => (string) $ulidsById[$subjectId],
                $this->inListOrder($subjectIdsByClass[$class->id] ?? []),
            ),
            can_change: isset($changeable[$class->id]),
        ))->all();

        $subjectRows = $subjects->map(function (Subject $subject) use ($classIdsBySubject, $changeable, $mayConfigure, $withTeacherChoices, $classes, $namesByClassId) {
            $taughtIn = $classIdsBySubject[$subject->id] ?? [];
            $canChange = $mayConfigure && array_diff($taughtIn, array_keys($changeable)) === [];
            $taughtNames = array_values($classes->filter(fn (SchoolClass $class) => in_array($class->id, $taughtIn, true))
                ->map(fn (SchoolClass $class) => (string) $namesByClassId[$class->id])
                ->all());

            return new SubjectRowData(
                ulid: $subject->ulid,
                name: $subject->name,
                code: $subject->code,
                type: $subject->type,
                taught_in: $taughtNames,
                can_change: $canChange,
                can_delete: $canChange && $taughtIn === [] && ! in_array($subject->id, $withTeacherChoices, true),
            );
        })->all();

        $scoped = $year === null ? collect() : $this->sections->scopedSections($identity, $year->ulid, $campusUlid);
        $active = $scoped->filter(fn (Section $section) => $section->status === 'active')->values();

        $selected = $active->first(fn (Section $section) => $section->ulid === $sectionUlid) ?? $active->first();

        return new SubjectsOverviewData(
            can_configure: $mayConfigure,
            campus_name: $campusUlid === null ? null : $this->campusName($identity, $campusUlid),
            classes: array_values($classRows),
            subjects: array_values($subjectRows),
            sections: array_values($active->map(fn (Section $section) => new SubjectSectionOptionData(
                ulid: $section->ulid,
                name: $section->schoolClass->name.'-'.$section->name,
                campus_name: $section->campus->name,
            ))->all()),
            selected_section: $selected === null ? null : $this->teachersOf($identity, $selected),
            staff: $mayConfigure ? $this->staff->options() : [],
        );
    }

    private function teachersOf(Identity $identity, Section $section): SectionTeachersData
    {
        $classTeacher = $section->getAttribute('class_teacher_name');
        $classTeacher = is_string($classTeacher) ? $classTeacher : null;

        /** @var array<int, string> $chosen */
        $chosen = SectionSubjectTeacher::query()
            ->join('staff', 'staff.id', '=', 'section_subject_teachers.staff_id')
            ->where('section_subject_teachers.section_id', $section->id)
            ->pluck('staff.ulid', 'section_subject_teachers.subject_id')
            ->all();

        $rows = $section->schoolClass->subjects()->orderBy('subjects.id')->get()
            ->map(fn (Subject $subject) => new SubjectTeacherRowData(
                subject_ulid: $subject->ulid,
                subject_name: $subject->name,
                teacher_ulid: $chosen[$subject->id] ?? null,
                automatic_name: $classTeacher,
            ))->all();

        return new SectionTeachersData(
            ulid: $section->ulid,
            name: $section->schoolClass->name.'-'.$section->name,
            class_teacher_name: $classTeacher,
            can_change: $this->guard->mayChooseTeachers($identity, $section),
            rows: array_values($rows),
        );
    }

    /**
     * Active sections of the academic year per class, across all campuses.
     *
     * @return array<int, int>
     */
    private function sectionCounts(string $yearUlid): array
    {
        /** @var array<int, int> $counts */
        $counts = Section::query()
            ->whereHas('academicYear', fn ($year) => $year->where('ulid', $yearUlid))
            ->where('status', 'active')
            ->selectRaw('school_class_id, count(*) as total')
            ->groupBy('school_class_id')
            ->pluck('total', 'school_class_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        return $counts;
    }

    /**
     * Subject ids in the order the subjects were added to the school's list.
     *
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function inListOrder(array $ids): array
    {
        sort($ids);

        return $ids;
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
