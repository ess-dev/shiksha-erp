<?php

namespace App\Domain\Access;

use App\Domain\Access\Data\Scope;
use App\Domain\Access\Enums\PermissionRisk;
use App\Domain\Access\Exceptions\GuardrailViolation;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;

/**
 * The rules every change to roles and assignments must pass: only permissions
 * in the catalog and the school's plan, owner-only permissions only in owner
 * roles, nobody grants what they do not hold, and critical grants are
 * confirmed.
 */
class GrantGuard
{
    public function __construct(private readonly Authorizer $authorizer) {}

    /**
     * The actor must hold at least one of the given permissions.
     *
     * @param  list<string>  $anyOf
     */
    public function assertMay(Identity $actor, array $anyOf): void
    {
        foreach ($anyOf as $key) {
            if ($this->authorizer->allows($actor, $key)) {
                return;
            }
        }

        throw GuardrailViolation::notAuthorised();
    }

    /**
     * Permissions must exist, belong to a module in the plan, and not be
     * owner-only unless the role is an owner role.
     *
     * @param  list<string>  $permissions
     */
    public function assertAvailable(array $permissions, bool $isOwnerRole): void
    {
        $catalog = $this->authorizer->catalog();
        $tenant = Tenant::current();

        foreach ($permissions as $key) {
            $entry = $catalog[$key] ?? null;

            if ($entry === null || ! $tenant instanceof Tenant || ! $tenant->hasModule($entry['module'])) {
                throw GuardrailViolation::unknownPermission($key);
            }

            if ($entry['owner_only'] && ! $isOwnerRole) {
                throw GuardrailViolation::ownerOnlyPermission($key);
            }
        }
    }

    /**
     * The actor must hold every permission themselves (in any scope).
     *
     * @param  list<string>  $permissions
     */
    public function assertActorHolds(Identity $actor, array $permissions): void
    {
        foreach ($permissions as $key) {
            if (! $this->authorizer->allows($actor, $key)) {
                throw GuardrailViolation::permissionNotHeld($key);
            }
        }
    }

    /**
     * The actor must hold every permission over a scope containing the one
     * being handed out.
     *
     * @param  list<string>  $permissions
     */
    public function assertActorCoversScope(Identity $actor, array $permissions, Scope $scope): void
    {
        foreach ($permissions as $key) {
            if (! $this->authorizer->scopesFor($actor, $key)->containsScope($scope)) {
                throw GuardrailViolation::scopeNotHeld($key);
            }
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    public function containsCritical(array $permissions): bool
    {
        $catalog = $this->authorizer->catalog();

        foreach ($permissions as $key) {
            if (($catalog[$key]['risk'] ?? null) === PermissionRisk::Critical) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    public function assertCriticalConfirmed(array $permissions, bool $confirmed): void
    {
        if ($this->containsCritical($permissions) && ! $confirmed) {
            throw GuardrailViolation::criticalNeedsConfirmation();
        }
    }
}
