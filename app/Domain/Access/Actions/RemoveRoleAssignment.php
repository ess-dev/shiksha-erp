<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Exceptions\GuardrailViolation;
use App\Domain\Access\GrantGuard;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class RemoveRoleAssignment extends Action
{
    public function __construct(
        private readonly GrantGuard $guard,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Takes a role assignment away. The actor must reach the assignment's scope,
     * and a school's last active owner cannot be removed.
     */
    public function handle(Identity $actor, RoleAssignment $assignment): void
    {
        $this->guard->assertMay($actor, ['roles.assign', 'roles.manage']);

        $this->transaction(function () use ($actor, $assignment) {
            $role = $assignment->role;

            $this->guard->assertActorCoversScope($actor, $role->permissionKeys(), $assignment->toScope());

            if ($role->is_owner) {
                $otherOwners = RoleAssignment::query()
                    ->whereKeyNot($assignment->id)
                    ->whereHas('role', fn ($query) => $query->where('is_owner', true))
                    ->whereHas('staff', fn ($query) => $query->where('status', 'active'))
                    ->count();

                if ($otherOwners === 0) {
                    throw GuardrailViolation::lastOwner();
                }
            }

            $before = [
                'role' => $role->ulid,
                'staff' => $assignment->staff->ulid,
                'scope_type' => $assignment->scope_type->value,
                'scope' => $assignment->scope,
            ];

            $assignment->delete();

            $this->audit(new AuditEntry('roles.assignment_removed', 'role_assignment', $assignment->ulid, $before));

            $this->authorizer->flush();
        });
    }
}
