<?php

namespace App\Domain\Admissions;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Models\Identity;

/**
 * Permission and scope checks for Actions, in the order the access rules set:
 * permission first (403), then scope (404, so an application outside the
 * person's reach is not confirmed to exist).
 */
class AdmissionGuard
{
    public function __construct(private readonly AdmissionVisibility $visibility) {}

    public function assertMayOn(Identity $actor, string $permission, AdmissionApplication $application): void
    {
        $this->assertHolds($actor, $permission);

        if (! $this->visibility->allows($actor, $application, $permission)) {
            throw AdmissionException::notFound();
        }
    }

    public function assertMayAt(Identity $actor, string $permission, ScopeTarget $target): void
    {
        $set = $this->visibility->scopes($actor, $permission);

        if ($set->isEmpty() || ! $set->allows($target)) {
            throw AdmissionException::notAllowed();
        }
    }

    public function assertHolds(Identity $actor, string $permission): void
    {
        if ($this->visibility->scopes($actor, $permission)->isEmpty()) {
            throw AdmissionException::notAllowed();
        }
    }
}
