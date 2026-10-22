<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Data\RoleSummaryData;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleTemplate;
use App\Domain\Access\RoleBuilder;

class RolesQuery
{
    public function __construct(private readonly RoleBuilder $builder) {}

    /**
     * Platform role templates a new role can be copied from.
     *
     * @return list<array{key: string, name: string}>
     */
    public function templates(): array
    {
        return array_values(RoleTemplate::query()->orderBy('id')->get()
            ->map(fn (RoleTemplate $template) => ['key' => $template->key, 'name' => $template->name])
            ->all());
    }

    /**
     * The school's roles, owner roles first, with their permissions, how many
     * people hold them and whether a newer template version is waiting.
     *
     * @return list<RoleSummaryData>
     */
    public function get(): array
    {
        $templates = RoleTemplate::query()->pluck('name', 'key');

        $roles = Role::query()
            ->with('permissions')
            ->withCount('assignments')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get();

        return array_values($roles->map(fn (Role $role) => new RoleSummaryData(
            ulid: $role->ulid,
            name: $role->name,
            template_name: $role->source_template_key === null ? null : ($templates[$role->source_template_key] ?? null),
            template_version: $role->source_template_version,
            is_owner: $role->is_owner,
            permissions: array_values($role->permissions->pluck('permission_key')->sort()->values()->all()),
            assignee_count: (int) $role->getAttribute('assignments_count'),
            update_pending: $this->builder->pendingUpdate($role) !== null,
        ))->all());
    }
}
