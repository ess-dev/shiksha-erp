<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Models\Role;
use App\Domain\Access\RoleBuilder;

class RoleTemplateUpdatesQuery
{
    public function __construct(private readonly RoleBuilder $builder) {}

    /**
     * Roles with a newer template version waiting, with what it would change.
     *
     * @return list<array{role_ulid: string, role_name: string, from_version: int|null, to_version: int, added: list<string>, removed: list<string>}>
     */
    public function get(): array
    {
        $updates = [];

        foreach (Role::query()->whereNotNull('source_template_key')->orderBy('name')->get() as $role) {
            $plan = $this->builder->pendingUpdate($role);

            if ($plan !== null) {
                $updates[] = [
                    'role_ulid' => $role->ulid,
                    'role_name' => $role->name,
                    'from_version' => $role->source_template_version,
                    ...$plan,
                ];
            }
        }

        return $updates;
    }
}
