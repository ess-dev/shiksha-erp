<?php

namespace App\Domain\Exams;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Exams\Data\MarksSection;
use App\Domain\Exams\Data\MarksSubject;
use App\Domain\Exams\Exceptions\MarksException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;
use Illuminate\Support\Facades\DB;

/**
 * Who may see and enter which marks, in one place so the screen and the
 * Actions cannot disagree. Current academic year only.
 *
 * A subject's marks in a section are entered by its teacher: the chosen
 * subject teacher, or the section's class teacher when none is chosen
 * ("Automatic"), who must also hold `marks.enter` over the section. Anyone
 * holding `marks.reopen` over the section (Principal, Vice principal,
 * Coordinator) may enter or correct any subject there and reopen published
 * marks. Other subjects of the section are view only for its class teacher,
 * for subject teachers holding `exams.view` over it, and for overseers.
 * Co-scholastic subjects are not examined, so they have no marks.
 */
class MarksAccess
{
    /** @var array<int, array{0: list<int>, 1: list<int>}> class and campus keys per exam */
    private array $reachOf = [];

    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
        private readonly ExamReach $reach,
    ) {}

    public function mayOpen(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'marks.enter')->allows() || $this->scopes->get($actor, 'marks.reopen')->allows();
    }

    /**
     * Every section of the current year with at least one subject the person
     * sees, lowest class first.
     *
     * @return list<MarksSection>
     */
    public function sections(Identity $actor): array
    {
        $enter = $this->scopes->get($actor, 'marks.enter');
        $reopen = $this->scopes->get($actor, 'marks.reopen');
        $view = $this->scopes->get($actor, 'exams.view');

        if ($enter->isEmpty() && $reopen->isEmpty()) {
            return [];
        }

        $me = $this->staff->forIdentity($actor);
        $meKey = $me !== null && $me->isActive ? $me->staffKey : null;

        $sections = array_values(array_filter(
            $this->sections->currentYear(),
            fn (SectionRefData $section) => $enter->allows($section->scopeTarget()) || $reopen->allows($section->scopeTarget()) || $view->allows($section->scopeTarget()),
        ));

        if ($sections === []) {
            return [];
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $classKeys = array_column($this->reach->classes(), 'id', 'ulid');
        $campusKeys = $connection->table('campuses')->pluck('id', 'ulid')->all();

        /** @var array<int, list<array{key: int, ulid: string, name: string}>> $subjectsByClass */
        $subjectsByClass = [];

        foreach ($connection->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('subjects.status', 'active')
            ->where('subjects.type', '!=', 'co_scholastic')
            ->orderBy('subjects.name')
            ->get(['link.school_class_id', 'subjects.id', 'subjects.ulid', 'subjects.name']) as $row) {
            $subjectsByClass[(int) $row->school_class_id][] = ['key' => (int) $row->id, 'ulid' => (string) $row->ulid, 'name' => (string) $row->name];
        }

        $chosen = [];

        foreach ($connection->table('section_subject_teachers')->whereIn('section_id', array_map(fn (SectionRefData $s) => $s->sectionKey, $sections))->get(['section_id', 'subject_id', 'staff_id']) as $row) {
            $chosen[(int) $row->section_id.'|'.(int) $row->subject_id] = (int) $row->staff_id;
        }

        $result = [];

        foreach ($sections as $section) {
            $target = $section->scopeTarget();
            $isOverseer = $reopen->allows($target);
            $mayTeach = $meKey !== null && $enter->allows($target);
            $isClassTeacher = $meKey !== null && $section->classTeacherStaffKey === $meKey;
            $seesAll = $isOverseer || $isClassTeacher || $view->allows($target);
            $classKey = (int) ($classKeys[$section->classUlid] ?? 0);

            $subjects = [];

            foreach ($subjectsByClass[$classKey] ?? [] as $subject) {
                $teacherKey = $chosen[$section->sectionKey.'|'.$subject['key']] ?? $section->classTeacherStaffKey;
                $isMine = $mayTeach && $teacherKey === $meKey;

                if (! $isMine && ! $seesAll) {
                    continue;
                }

                $subjects[] = new MarksSubject($subject['key'], $subject['ulid'], $subject['name'], $teacherKey, $isMine, $isMine || $isOverseer);
            }

            if ($subjects !== []) {
                $result[] = new MarksSection($section, $classKey, (int) ($campusKeys[$section->campusUlid] ?? 0), $isOverseer, $subjects);
            }
        }

        usort($result, fn (MarksSection $a, MarksSection $b) => [$a->section->classLevel, $a->section->campusName, $a->section->name]
            <=> [$b->section->classLevel, $b->section->campusName, $b->section->name]);

        return $result;
    }

    /**
     * Whether the exam is for this section's class at its campus.
     */
    public function appliesTo(Exam $exam, MarksSection $section): bool
    {
        $this->reachOf[$exam->id] ??= [$this->reach->examClassIds($exam), $this->reach->examCampusIds($exam)];

        return in_array($section->classKey, $this->reachOf[$exam->id][0], true)
            && in_array($section->campusKey, $this->reachOf[$exam->id][1], true);
    }

    /**
     * The section and subject for a change, or a 404 when the person cannot
     * see them or the exam is not for that section.
     *
     * @return array{0: MarksSection, 1: MarksSubject}
     */
    public function pairOrFail(Identity $actor, Exam $exam, string $sectionUlid, string $subjectUlid): array
    {
        foreach ($this->sections($actor) as $section) {
            if ($section->section->ulid !== $sectionUlid) {
                continue;
            }

            $subject = $section->subject($subjectUlid);

            if ($subject !== null && $section->section->academicYearKey === $exam->academic_year_id && $this->appliesTo($exam, $section)) {
                return [$section, $subject];
            }
        }

        throw MarksException::notFound();
    }
}
