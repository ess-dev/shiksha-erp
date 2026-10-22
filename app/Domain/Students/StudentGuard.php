<?php

namespace App\Domain\Students;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Student;

/**
 * Permission and scope checks for Actions, in the order the access rules set:
 * permission first (403), then scope (404, so a record outside the person's
 * reach is not confirmed to exist).
 */
class StudentGuard
{
    public function __construct(private readonly StudentVisibility $visibility) {}

    public function assertMayOn(Identity $actor, string $permission, Student $student): void
    {
        $this->assertHolds($actor, $permission);

        if (! $this->visibility->allows($actor, $student, $permission)) {
            throw StudentException::notFound();
        }
    }

    public function assertMayAt(Identity $actor, string $permission, ScopeTarget $target): void
    {
        $set = $this->visibility->scopes($actor, $permission);

        if ($set->isEmpty()) {
            throw StudentException::notAllowed();
        }

        if (! $set->allows($target)) {
            throw StudentException::sectionOutOfScope();
        }
    }

    public function assertHolds(Identity $actor, string $permission): void
    {
        if ($this->visibility->scopes($actor, $permission)->isEmpty()) {
            throw StudentException::notAllowed();
        }
    }
}
