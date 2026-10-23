<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Subject;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;

class SetClassSubjects extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    /**
     * Replaces the subjects taught in a class. Only active subjects can be added.
     *
     * @param  list<Subject>  $subjects
     */
    public function handle(Identity $actor, SchoolClass $class, array $subjects): SchoolClass
    {
        $this->guard->assertMayManageSchool($actor);

        return $this->transaction(function () use ($class, $subjects) {
            $before = $class->subjects()->orderBy('code')->pluck('code')->all();

            $ids = [];
            $after = [];

            foreach ($subjects as $subject) {
                if (! $subject->isActive()) {
                    continue;
                }

                $ids[] = $subject->id;
                $after[] = $subject->code;
            }

            $class->subjects()->sync($ids);
            sort($after);

            $this->audit(new AuditEntry('structure.class_subjects_set', 'school_class', $class->ulid, ['subjects' => $before], [
                'subjects' => $after,
            ]));

            return $class;
        });
    }
}
