<?php

namespace App\Domain\Access;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RolePermission;
use App\Domain\Access\Models\RoleTemplate;
use App\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * Copies a platform role template into a school's own role. Used inside
 * Actions (and so inside their transaction); it is not an entry point.
 * Permissions of modules outside the school's plan are left out of the copy.
 */
class RoleBuilder
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * @return array{role: Role, permissions: list<string>}
     */
    public function fromTemplate(string $templateKey, ?string $name = null): array
    {
        $template = RoleTemplate::query()->where('key', $templateKey)->firstOrFail();
        $version = $template->currentVersion();

        $catalog = $this->authorizer->catalog();
        $tenant = Tenant::current();
        $modulesInPlan = [];
        $permissions = [];

        foreach ($version->permissionKeys() as $key) {
            $module = $catalog[$key]['module'] ?? null;

            if ($module === null || ! $tenant instanceof Tenant) {
                continue;
            }

            $modulesInPlan[$module] ??= $tenant->hasModule($module);

            if ($modulesInPlan[$module]) {
                $permissions[] = $key;
            }
        }

        $role = Role::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => $name ?? $template->name,
            'source_template_key' => $template->key,
            'source_template_version' => $version->version,
            'is_owner' => $version->is_owner_template,
        ]);

        $this->setPermissions($role, $permissions);

        return ['role' => $role, 'permissions' => $permissions];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function setPermissions(Role $role, array $permissions): void
    {
        RolePermission::query()->where('role_id', $role->id)->delete();

        foreach (array_values(array_unique($permissions)) as $key) {
            RolePermission::query()->create(['role_id' => $role->id, 'permission_key' => $key]);
        }
    }
}
