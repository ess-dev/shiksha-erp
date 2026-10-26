<?php

namespace App\Domain\Homework;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Homework\Data\HomeworkPair;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;
use Illuminate\Support\Facades\DB;

/**
 * Who may set, change and see which homework, in one place so the screen and
 * the Actions cannot disagree. Current academic year only.
 *
 * A teacher sets homework for a subject of a section they teach: its chosen
 * subject teacher, or the section's class teacher (every subject of their
 * class), and they must hold `homework.assign` over that section. Only the
 * teacher who set an assignment changes or deletes it, marks work checked and
 * writes feedback. Everyone else sees it read-only: the people whose
 * `homework.view` covers the section (class teacher, Principal, Vice
 * principal, Coordinator over their scope) and teachers of the section.
 */
class HomeworkAccess
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    public function mayOpen(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'homework.view')->allows() || $this->scopes->get($actor, 'homework.assign')->allows();
    }

    public function staffKey(Identity $actor): ?int
    {
        $me = $this->staff->forIdentity($actor);

        return $me !== null && $me->isActive ? $me->staffKey : null;
    }

    /**
     * Every subject of every current-year section the person may set homework
     * for, lowest class first.
     *
     * @return list<HomeworkPair>
     */
    public function pairs(Identity $actor): array
    {
        $assign = $this->scopes->get($actor, 'homework.assign');
        $meKey = $this->staffKey($actor);

        if ($assign->isEmpty() || $meKey === null) {
            return [];
        }

        $sections = array_filter($this->sections->currentYear(), fn (SectionRefData $section) => $assign->allows($section->scopeTarget()));

        if ($sections === []) {
            return [];
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $classIds = $connection->table('school_classes')->pluck('id', 'ulid')->all();
        $subjectsByClass = [];

        foreach ($connection->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('subjects.status', 'active')
            ->orderBy('subjects.id')
            ->get(['link.school_class_id', 'subjects.id', 'subjects.ulid', 'subjects.name']) as $row) {
            $subjectsByClass[(int) $row->school_class_id][] = $row;
        }

        $chosen = [];

        foreach ($connection->table('section_subject_teachers')->where('staff_id', $meKey)->get(['section_id', 'subject_id']) as $row) {
            $chosen[(int) $row->section_id.'|'.(int) $row->subject_id] = true;
        }

        $pairs = [];

        foreach ($sections as $section) {
            $isClassTeacher = $section->classTeacherStaffKey === $meKey;

            foreach ($subjectsByClass[(int) ($classIds[$section->classUlid] ?? 0)] ?? [] as $subject) {
                $isChosen = isset($chosen[$section->sectionKey.'|'.(int) $subject->id]);

                if ($isChosen || $isClassTeacher) {
                    $pairs[] = new HomeworkPair($section, (int) $subject->id, (string) $subject->ulid, (string) $subject->name, $isChosen);
                }
            }
        }

        usort($pairs, fn (HomeworkPair $a, HomeworkPair $b) => [$a->section->classLevel, $a->section->campusName, $a->section->name, $a->subjectKey]
            <=> [$b->section->classLevel, $b->section->campusName, $b->section->name, $b->subjectKey]);

        return $pairs;
    }

    /**
     * The pair for a section and subject, or a 404 outside the person's reach.
     */
    public function pairOrFail(Identity $actor, string $sectionUlid, string $subjectUlid): HomeworkPair
    {
        foreach ($this->pairs($actor) as $pair) {
            if ($pair->section->ulid === $sectionUlid && $pair->subjectUlid === $subjectUlid) {
                return $pair;
            }
        }

        throw HomeworkException::notFound();
    }

    /**
     * Current-year sections whose homework the person may see: their
     * `homework.view` scope, plus every section they set homework for.
     *
     * @return list<SectionRefData>
     */
    public function viewableSections(Identity $actor): array
    {
        $view = $this->scopes->get($actor, 'homework.view');
        $teaching = [];

        foreach ($this->pairs($actor) as $pair) {
            $teaching[$pair->section->sectionKey] = true;
        }

        $sections = array_values(array_filter(
            $this->sections->currentYear(),
            fn (SectionRefData $section) => isset($teaching[$section->sectionKey]) || $view->allows($section->scopeTarget()),
        ));

        usort($sections, fn (SectionRefData $a, SectionRefData $b) => [$a->classLevel, $a->campusName, $a->name] <=> [$b->classLevel, $b->campusName, $b->name]);

        return $sections;
    }

    /**
     * A current-year assignment in a section the person may see, or a 404.
     */
    public function assignmentOrFail(Identity $actor, string $assignmentUlid): HomeworkAssignment
    {
        $assignment = HomeworkAssignment::query()->where('ulid', $assignmentUlid)->first();

        if ($assignment === null || $this->sectionOf($actor, $assignment) === null) {
            throw HomeworkException::notFound();
        }

        return $assignment;
    }

    /**
     * The assignment's section when the person may see it, otherwise null.
     */
    public function sectionOf(Identity $actor, HomeworkAssignment $assignment): ?SectionRefData
    {
        foreach ($this->viewableSections($actor) as $section) {
            if ($section->sectionKey === $assignment->section_id) {
                return $section;
            }
        }

        return null;
    }

    /**
     * Whether the person may change this assignment, mark work checked and
     * write feedback: the teacher who set it, still holding `homework.assign`
     * over its section.
     */
    public function mayManage(Identity $actor, HomeworkAssignment $assignment): bool
    {
        $meKey = $this->staffKey($actor);

        if ($meKey === null || $assignment->created_by_staff_id !== $meKey) {
            return false;
        }

        $section = $this->sections->byKeys([$assignment->section_id])[$assignment->section_id] ?? null;

        return $section !== null && $this->scopes->get($actor, 'homework.assign')->allows($section->scopeTarget());
    }

    /**
     * The assignment for a change: visible to the person and set by them, else
     * 404 (not visible) or 403 (visible, but someone else's).
     */
    public function manageableOrFail(Identity $actor, string $assignmentUlid): HomeworkAssignment
    {
        $assignment = $this->assignmentOrFail($actor, $assignmentUlid);

        if (! $this->mayManage($actor, $assignment)) {
            throw HomeworkException::notAllowed();
        }

        return $assignment;
    }
}
