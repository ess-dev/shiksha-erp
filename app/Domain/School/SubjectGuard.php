<?php

namespace App\Domain\School;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Who may configure subjects. A class is school-wide (its subjects apply to
 * every section on every campus), so a person with a limited scope may change
 * a class only when `subjects.configure` covers every one of its sections this
 * year. School-wide holders (and school-wide `structure.manage`) change any.
 */
class SubjectGuard
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    /** Whether the person holds the permission at all (any scope). */
    public function mayConfigure(Identity $actor): bool
    {
        return $this->isSchoolWide($actor) || $this->scopes->get($actor, 'subjects.configure')->allows();
    }

    public function assertMayConfigure(Identity $actor): void
    {
        if (! $this->mayConfigure($actor)) {
            throw StructureException::notAllowed();
        }
    }

    public function assertMayChangeClass(Identity $actor, SchoolClass $class): void
    {
        if (! isset($this->changeableClassIds($actor)[$class->id])) {
            throw StructureException::notAllowed();
        }
    }

    /**
     * A subject is changed by someone who may change every class that teaches
     * it; one taught nowhere may be changed by anyone who configures subjects.
     */
    public function assertMayChangeSubject(Identity $actor, Subject $subject): void
    {
        $this->assertMayConfigure($actor);

        $changeable = $this->changeableClassIds($actor);

        foreach ($this->classIdsTeaching($subject->id) as $classId) {
            if (! isset($changeable[$classId])) {
                throw StructureException::notAllowed();
            }
        }
    }

    public function assertMayChooseTeachers(Identity $actor, Section $section): void
    {
        if (! $this->mayChooseTeachers($actor, $section)) {
            throw StructureException::notAllowed();
        }
    }

    public function mayChooseTeachers(Identity $actor, Section $section): bool
    {
        return $this->isSchoolWide($actor)
            || $this->scopes->get($actor, 'subjects.configure')->allows($section->scopeTarget());
    }

    /**
     * Ids of the classes whose subjects the person may change, for lists that
     * show a Change button per class. One query for all sections this year.
     *
     * @return array<int, true>
     */
    public function changeableClassIds(Identity $actor): array
    {
        $classIds = SchoolClass::query()->pluck('id')->all();

        if ($this->isSchoolWide($actor)) {
            return array_fill_keys($classIds, true);
        }

        $configure = $this->scopes->get($actor, 'subjects.configure');

        if ($configure->isEmpty()) {
            return [];
        }

        $rows = DB::connection('tenant')->table('sections')
            ->join('academic_years', 'academic_years.id', '=', 'sections.academic_year_id')
            ->join('campuses', 'campuses.id', '=', 'sections.campus_id')
            ->join('school_classes', 'school_classes.id', '=', 'sections.school_class_id')
            ->where('academic_years.is_current', true)
            ->where('sections.status', 'active')
            ->get(['sections.ulid as section', 'campuses.ulid as campus', 'school_classes.level', 'school_classes.id as class_id']);

        $changeable = [];
        $blocked = [];

        foreach ($rows as $row) {
            $classId = (int) $row->class_id;

            if ($configure->allows(new ScopeTarget((string) $row->campus, (int) $row->level, (string) $row->section))) {
                $changeable[$classId] = true;
            } else {
                $blocked[$classId] = true;
            }
        }

        return array_diff_key($changeable, $blocked);
    }

    /**
     * @return list<int>
     */
    public function classIdsTeaching(int $subjectId): array
    {
        return array_values(DB::connection('tenant')->table('school_class_subject')
            ->where('subject_id', $subjectId)
            ->pluck('school_class_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    private function isSchoolWide(Identity $actor): bool
    {
        return $this->scopes->get($actor, 'structure.manage')->isSchoolWide()
            || $this->scopes->get($actor, 'subjects.configure')->isSchoolWide();
    }
}
