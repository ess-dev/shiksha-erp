<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\GrantGuard;
use App\Domain\Access\Models\Role;
use App\Domain\Access\RoleBuilder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class UpdateRolePermissions extends Action
{
    public function __construct(
        private readonly GrantGuard $guard,
        private readonly RoleBuilder $builder,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Replaces the role's permissions. Added permissions must be ones the actor
     * holds, over every scope the role is already assigned at.
     *
     * @param  list<string>  $permissions
     */
    public function handle(Identity $actor, Role $role, array $permissions, bool $confirmCritical = false): Role
    {
        $this->guard->assertMay($actor, ['roles.manage']);

        return $this->transaction(function () use ($actor, $role, $permissions, $confirmCritical) {
            $before = $role->permissionKeys();
            $after = array_values(array_unique($permissions));
            sort($after);
            $added = array_values(array_diff($after, $before));

            $this->guard->assertAvailable($added, $role->is_owner);
            $this->guard->assertActorHolds($actor, $added);
            $this->guard->assertCriticalConfirmed($added, $confirmCritical);

            foreach ($role->assignments as $assignment) {
                $this->guard->assertActorCoversScope($actor, $added, $assignment->toScope());
            }

            $this->builder->setPermissions($role, $after);

            $this->audit(new AuditEntry('roles.permissions_updated', 'role', $role->ulid, ['permissions' => $before], [
                'permissions' => $after,
                'added' => $added,
                'removed' => array_values(array_diff($before, $after)),
                'critical_confirmed' => $this->guard->containsCritical($added),
            ]));

            $this->authorizer->flush();

            return $role;
        });
    }
}
