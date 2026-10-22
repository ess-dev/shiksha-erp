<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\RoleUpdateData;
use App\Domain\Access\GrantGuard;

class RoleUpdatePromptQuery
{
    public function __construct(
        private readonly RoleTemplateUpdatesQuery $updates,
        private readonly GrantGuard $guard,
    ) {}

    /**
     * The "template update available" prompt: what each waiting update adds
     * and removes, and whether applying it needs the critical confirmation.
     *
     * @return list<RoleUpdateData>
     */
    public function get(): array
    {
        return array_map(fn (array $update) => new RoleUpdateData(
            role_ulid: $update['role_ulid'],
            role_name: $update['role_name'],
            from_version: $update['from_version'],
            to_version: $update['to_version'],
            added: $update['added'],
            removed: $update['removed'],
            adds_critical: $this->guard->containsCritical($update['added']),
        ), $this->updates->get());
    }
}
