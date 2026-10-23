<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Models\Role;
use App\Domain\Identity\Models\Identity;

class RoleNamesForIdentityQuery
{
    /**
     * Names of the roles the identity holds in the current school, for audit entries.
     *
     * @return list<string>
     */
    public function get(Identity $identity): array
    {
        return array_values(Role::query()
            ->whereHas('assignments.staff', fn ($query) => $query->where('identity_ulid', $identity->ulid))
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->all());
    }
}
