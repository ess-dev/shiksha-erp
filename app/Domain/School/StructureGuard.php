<?php

namespace App\Domain\School;

use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Queries\PermissionScopesQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;

/**
 * Who may change the school structure. Everything school-wide (campuses,
 * academic years, classes, subjects) needs `structure.manage` over the whole
 * school; a section is changed by someone whose scope covers where it sits.
 */
class StructureGuard
{
    public function __construct(private readonly PermissionScopesQuery $scopes) {}

    public function assertMayManageSchool(Identity $actor): void
    {
        if (! $this->scopes->get($actor, 'structure.manage')->isSchoolWide()) {
            throw StructureException::notAllowed();
        }
    }

    public function assertMayManageAt(Identity $actor, ScopeTarget $target): void
    {
        if (! $this->scopes->get($actor, 'structure.manage')->allows($target)) {
            throw StructureException::notAllowed();
        }
    }
}
