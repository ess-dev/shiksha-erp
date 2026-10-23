<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentSiblingLink;
use App\Domain\Students\SiblingFinder;
use App\Domain\Students\StudentGuard;

class UnlinkSiblings extends Action
{
    public function __construct(
        private readonly StudentGuard $guard,
        private readonly SiblingFinder $siblings,
    ) {}

    /**
     * Removes a staff-made sibling link. Siblings who share a guardian cannot
     * be unlinked here: that link is the guardian's. Needs
     * `students.link_siblings` over both students.
     */
    public function handle(Identity $actor, Student $student, Student $sibling): void
    {
        $this->guard->assertMayOn($actor, 'students.link_siblings', $student);
        $this->guard->assertMayOn($actor, 'students.link_siblings', $sibling);

        $this->transaction(function () use ($student, $sibling) {
            [$low, $high] = $this->siblings->ordered($student->id, $sibling->id);

            $link = StudentSiblingLink::query()->where('student_id', $low)->where('sibling_id', $high)->first()
                ?? throw StudentException::notLinked();

            $link->delete();

            $this->audit(new AuditEntry('students.siblings_unlinked', 'student', $student->ulid, ['sibling' => $sibling->ulid]));
        });
    }
}
