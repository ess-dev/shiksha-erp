<?php

namespace App\Domain\Access\Queries;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\PermissionOptionData;
use App\Domain\Access\Models\Permission;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;

class PermissionOptionsQuery
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * Permissions of modules in the school's plan, with whether the viewer
     * holds each one (nobody can grant what they do not hold).
     *
     * @return list<PermissionOptionData>
     */
    public function get(Identity $viewer): array
    {
        $tenant = Tenant::current();

        if (! $tenant instanceof Tenant) {
            return [];
        }

        $held = $this->authorizer->heldPermissions($viewer);
        $inPlan = [];
        $options = [];

        foreach (Permission::query()->orderBy('module')->orderBy('key')->get() as $permission) {
            $inPlan[$permission->module] ??= $tenant->hasModule($permission->module);

            if (! $inPlan[$permission->module]) {
                continue;
            }

            $options[] = new PermissionOptionData(
                key: $permission->key,
                module: $permission->module,
                risk: $permission->risk,
                is_owner_only: $permission->is_owner_only,
                description: (string) $permission->getAttribute('description'),
                held_by_you: in_array($permission->key, $held, true),
            );
        }

        return $options;
    }
}
