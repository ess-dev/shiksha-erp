<?php

namespace App\Domain\School;

use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Data\SyllabusPair;
use App\Domain\School\Exceptions\SyllabusException;
use App\Domain\School\Queries\SectionDirectoryQuery;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Who may keep which subject up to date, in one place so the screen and the
 * Actions cannot disagree. It works on the current academic year only.
 *
 * A subject in a section is open to a person who holds `subjects.update` over
 * that section and is either its chosen subject teacher, the section's class
 * teacher (every subject of their class), or holds `subjects.configure` over
 * it (Principal, Vice principal, Coordinator: any subject in their scope).
 * The textbook and chapter list are shared by every section of a class, so
 * changing them needs a pair of that class the person teaches, or a manager
 * whose `subjects.configure` covers every section of the class.
 */
class SyllabusAccess
{
    public function __construct(
        private readonly PermissionScopesQuery $scopes,
        private readonly StaffRefQuery $staff,
        private readonly SectionDirectoryQuery $sections,
        private readonly SubjectGuard $guard,
    ) {}

    public function mayOpen(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'subjects.update')->allows();
    }

    /**
     * Every subject in every active section of the current year the person
     * may keep up to date, the ones they teach first.
     *
     * @return list<SyllabusPair>
     */
    public function pairs(Identity $actor): array
    {
        $update = $this->scopes->get($actor, 'subjects.update');

        if ($update->isEmpty()) {
            return [];
        }

        $configure = $this->scopes->get($actor, 'subjects.configure');
        $schoolWideManager = $this->scopes->get($actor, 'structure.manage')->isSchoolWide() || $configure->isSchoolWide();
        $me = $this->staff->forIdentity($actor);
        $meKey = $me !== null && $me->isActive ? $me->staffKey : null;

        $sections = array_filter($this->sections->currentYear(), fn (SectionRefData $section) => $update->allows($section->scopeTarget()));

        if ($sections === []) {
            return [];
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $classIds = $connection->table('school_classes')->pluck('id', 'ulid')->all();
        /** @var array<int, list<stdClass>> $subjectsByClass */
        $subjectsByClass = [];

        foreach ($connection->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('subjects.status', 'active')
            ->orderBy('subjects.id')
            ->get(['link.school_class_id', 'subjects.id', 'subjects.ulid', 'subjects.name']) as $row) {
            $subjectsByClass[(int) $row->school_class_id][] = $row;
        }

        $chosen = [];

        foreach ($connection->table('section_subject_teachers')->get(['section_id', 'subject_id', 'staff_id']) as $row) {
            $chosen[(int) $row->section_id.'|'.(int) $row->subject_id] = (int) $row->staff_id;
        }

        $pairs = [];

        foreach ($sections as $section) {
            $isManager = $schoolWideManager || $configure->allows($section->scopeTarget());
            $isClassTeacher = $meKey !== null && $section->classTeacherStaffKey === $meKey;

            foreach ($subjectsByClass[(int) ($classIds[$section->classUlid] ?? 0)] ?? [] as $subject) {
                $isChosen = $meKey !== null && ($chosen[$section->sectionKey.'|'.(int) $subject->id] ?? null) === $meKey;

                if (! $isChosen && ! $isClassTeacher && ! $isManager) {
                    continue;
                }

                $pairs[] = new SyllabusPair($section, (int) $subject->id, (string) $subject->ulid, (string) $subject->name, $isChosen, $isClassTeacher, $isManager);
            }
        }

        usort($pairs, fn (SyllabusPair $a, SyllabusPair $b) => [! $a->isChosenTeacher, $a->section->classLevel, $a->section->campusName, $a->section->name, $a->subjectKey]
            <=> [! $b->isChosenTeacher, $b->section->classLevel, $b->section->campusName, $b->section->name, $b->subjectKey]);

        return $pairs;
    }

    /**
     * The pair, or a 404 when it does not exist or is outside the person's reach.
     */
    public function pairOrFail(Identity $actor, string $sectionUlid, string $subjectUlid): SyllabusPair
    {
        foreach ($this->pairs($actor) as $pair) {
            if ($pair->section->ulid === $sectionUlid && $pair->subjectUlid === $subjectUlid) {
                return $pair;
            }
        }

        throw SyllabusException::notFound();
    }

    /**
     * Whether the person may change the textbook and chapters this pair's
     * class shares across all its sections.
     *
     * @param  list<SyllabusPair>  $pairs  The person's pairs (from pairs())
     */
    public function mayEditClass(Identity $actor, SyllabusPair $pair, array $pairs): bool
    {
        foreach ($pairs as $other) {
            if ($other->section->classUlid === $pair->section->classUlid && $other->subjectKey === $pair->subjectKey && $other->isTeaching()) {
                return true;
            }
        }

        $classId = DB::connection(config('multitenancy.tenant_database_connection_name'))->table('school_classes')->where('ulid', $pair->section->classUlid)->value('id');

        return $classId !== null && isset($this->guard->changeableClassIds($actor)[(int) $classId]);
    }
}
