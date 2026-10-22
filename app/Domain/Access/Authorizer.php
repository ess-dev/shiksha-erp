<?php

namespace App\Domain\Access;

use App\Domain\Access\Data\Scope;
use App\Domain\Access\Data\ScopeSet;
use App\Domain\Access\Data\ScopeTarget;
use App\Domain\Access\Enums\PermissionRisk;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;

/**
 * Answers "may this person do this?" for the current school, in the order the
 * access rules set: school available -> module in plan -> permission -> scope.
 * (Object-level checks belong to each module's Policy, using the ScopeSet.)
 *
 * Results are remembered for the request; Actions that change roles call
 * flush().
 */
class Authorizer
{
    /** @var array<string, array{permissions: array<string, list<Scope>>, owner: bool}> */
    private array $loaded = [];

    /** @var array<string, array{module: string, risk: PermissionRisk, owner_only: bool}>|null */
    private ?array $catalog = null;

    public function allows(Identity $identity, string $permission, ?ScopeTarget $target = null): bool
    {
        return $this->scopesFor($identity, $permission)->allows($target);
    }

    public function scopesFor(Identity $identity, string $permission): ScopeSet
    {
        return new ScopeSet($this->load($identity)['permissions'][$permission] ?? []);
    }

    /**
     * @return list<string> Permission keys held in at least one scope
     */
    public function heldPermissions(Identity $identity): array
    {
        $keys = array_keys($this->load($identity)['permissions']);
        sort($keys);

        return $keys;
    }

    public function holdsOwnerRole(Identity $identity): bool
    {
        return $this->load($identity)['owner'];
    }

    public function holdsCriticalPermission(Identity $identity): bool
    {
        foreach ($this->heldPermissions($identity) as $key) {
            if (($this->catalog()[$key]['risk'] ?? null) === PermissionRisk::Critical) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array{module: string, risk: PermissionRisk, owner_only: bool}>
     */
    public function catalog(): array
    {
        if ($this->catalog === null) {
            $this->catalog = [];

            foreach (Permission::query()->get() as $permission) {
                $this->catalog[$permission->key] = [
                    'module' => $permission->module,
                    'risk' => $permission->risk,
                    'owner_only' => $permission->is_owner_only,
                ];
            }
        }

        return $this->catalog;
    }

    public function flush(): void
    {
        $this->loaded = [];
        $this->catalog = null;
    }

    /**
     * @return array{permissions: array<string, list<Scope>>, owner: bool}
     */
    private function load(Identity $identity): array
    {
        $tenant = Tenant::current();
        $empty = ['permissions' => [], 'owner' => false];

        if (! $tenant instanceof Tenant || ! $tenant->isAccessible()) {
            return $empty;
        }

        $cacheKey = $tenant->id.'|'.$identity->ulid;

        return $this->loaded[$cacheKey] ??= $this->build($identity, $tenant) ?? $empty;
    }

    /**
     * @return array{permissions: array<string, list<Scope>>, owner: bool}|null
     */
    private function build(Identity $identity, Tenant $tenant): ?array
    {
        $staff = Staff::query()->where('identity_ulid', $identity->ulid)->where('status', 'active')->first();

        if ($staff === null) {
            return null;
        }

        $catalog = $this->catalog();
        $modulesInPlan = [];
        $permissions = [];
        $owner = false;

        foreach (RoleAssignment::query()->with('role.permissions')->where('staff_id', $staff->id)->get() as $assignment) {
            $scope = $assignment->toScope();
            $owner = $owner || $assignment->role->is_owner;

            foreach ($assignment->role->permissions as $rolePermission) {
                $entry = $catalog[$rolePermission->permission_key] ?? null;

                if ($entry === null) {
                    continue;
                }

                $modulesInPlan[$entry['module']] ??= $tenant->hasModule($entry['module']);

                if ($modulesInPlan[$entry['module']]) {
                    $permissions[$rolePermission->permission_key][] = $scope;
                }
            }
        }

        return ['permissions' => $permissions, 'owner' => $owner];
    }
}
