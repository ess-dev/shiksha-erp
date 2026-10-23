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

        $permissions = $this->availableOnly($version->permissionKeys());

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
     * What a newer version of the role's template would change: permissions it
     * adds (only those available to this school) and removes. Null when the role
     * has no template or is already on the latest version. The school's own
     * edits are never part of the plan.
     *
     * @return array{added: list<string>, removed: list<string>, to_version: int}|null
     */
    public function pendingUpdate(Role $role): ?array
    {
        if ($role->source_template_key === null || $role->source_template_version === null) {
            return null;
        }

        $template = RoleTemplate::query()->where('key', $role->source_template_key)->first();

        if ($template === null || $template->current_version <= $role->source_template_version) {
            return null;
        }

        $old = $template->versions()->where('version', $role->source_template_version)->first()?->permissionKeys() ?? [];
        $new = $template->currentVersion()->permissionKeys();

        return [
            'added' => $this->availableOnly(array_values(array_diff($new, $old))),
            'removed' => array_values(array_diff($old, $new)),
            'to_version' => $template->current_version,
        ];
    }

    /**
     * Applies a plan from pendingUpdate() and moves the role to the new version.
     *
     * @param  array{added: list<string>, removed: list<string>, to_version: int}  $plan
     * @return list<string> The role's permissions afterwards
     */
    public function applyUpdate(Role $role, array $plan): array
    {
        $permissions = array_values(array_diff(array_unique([...$role->permissionKeys(), ...$plan['added']]), $plan['removed']));

        $this->setPermissions($role, $permissions);
        $role->forceFill(['source_template_version' => $plan['to_version']])->save();

        return $permissions;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string> Keys that exist in the catalog and whose module is in this school's plan
     */
    private function availableOnly(array $keys): array
    {
        $catalog = $this->authorizer->catalog();
        $tenant = Tenant::current();
        $modulesInPlan = [];
        $available = [];

        foreach ($keys as $key) {
            $module = $catalog[$key]['module'] ?? null;

            if ($module === null || ! $tenant instanceof Tenant) {
                continue;
            }

            $modulesInPlan[$module] ??= $tenant->hasModule($module);

            if ($modulesInPlan[$module]) {
                $available[] = $key;
            }
        }

        return $available;
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
