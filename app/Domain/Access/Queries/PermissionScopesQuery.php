<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\ScopeSet;
use App\Domain\Identity\Models\Identity;

class PermissionScopesQuery
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * Every scope in which the identity holds the permission in the current
     * school; empty when it is not held, the module is outside the plan or the
     * school is unavailable. Other modules use this to guard writes and to
     * scope their list queries.
     */
    public function get(Identity $identity, string $permission): ScopeSet
    {
        return $this->authorizer->scopesFor($identity, $permission);
    }
}
