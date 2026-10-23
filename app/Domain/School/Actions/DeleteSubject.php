<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\SectionSubjectTeacher;
use App\Domain\School\Models\Subject;
use App\Domain\School\SubjectGuard;
use App\Domain\Shared\Action;

class DeleteSubject extends Action
{
    public function __construct(private readonly SubjectGuard $guard) {}

    /**
     * Deletes a subject that no class teaches and that has no teacher choices.
     * Nothing else refers to it yet, so nothing else changes.
     */
    public function handle(Identity $actor, Subject $subject): void
    {
        $this->guard->assertMayChangeSubject($actor, $subject);

        $this->transaction(function () use ($subject) {
            if ($this->guard->classIdsTeaching($subject->id) !== []
                || SectionSubjectTeacher::query()->where('subject_id', $subject->id)->exists()) {
                throw StructureException::subjectInUse();
            }

            $this->audit(new AuditEntry('subjects.subject_deleted', 'subject', $subject->ulid, [
                'name' => $subject->name,
                'code' => $subject->code,
                'type' => $subject->type->value,
            ], null));

            $subject->delete();
        });
    }
}
