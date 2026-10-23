<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\SectionSubjectTeacher;
use App\Domain\School\Models\Subject;
use App\Domain\School\SubjectGuard;
use App\Domain\Shared\Action;

class SetClassSubjects extends Action
{
    public function __construct(private readonly SubjectGuard $guard) {}

    /**
     * Replaces the subjects taught in a class. Only active subjects can be
     * added and at least one must be examined. A subject taken out of the
     * class also loses the teachers chosen for it in this year's sections.
     *
     * @param  list<Subject>  $subjects
     */
    public function handle(Identity $actor, SchoolClass $class, array $subjects): SchoolClass
    {
        $this->guard->assertMayChangeClass($actor, $class);

        $chosen = array_values(array_filter($subjects, fn (Subject $subject) => $subject->isActive()));

        if (array_filter($chosen, fn (Subject $subject) => $subject->type->isExamined()) === []) {
            throw StructureException::needsExaminedSubject();
        }

        return $this->transaction(function () use ($class, $chosen) {
            $before = $class->subjects()->orderBy('code')->pluck('code', 'subjects.id')->all();
            $ids = array_map(fn (Subject $subject) => $subject->id, $chosen);
            $after = array_map(fn (Subject $subject) => $subject->code, $chosen);
            sort($after);

            $removed = array_values(array_diff(array_keys($before), $ids));
            $cleared = $removed === [] ? 0 : SectionSubjectTeacher::query()
                ->whereIn('subject_id', $removed)
                ->whereIn('section_id', Section::query()
                    ->where('school_class_id', $class->id)
                    ->whereHas('academicYear', fn ($year) => $year->where('is_current', true))
                    ->select('id'))
                ->delete();

            $class->subjects()->sync($ids);

            $this->audit(new AuditEntry('structure.class_subjects_set', 'school_class', $class->ulid, ['subjects' => array_values($before)], [
                'subjects' => $after,
                'teacher_choices_cleared' => $cleared,
            ]));

            return $class;
        });
    }
}
