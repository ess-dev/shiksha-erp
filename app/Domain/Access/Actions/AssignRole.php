<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\GrantGuard;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AssignRole extends Action
{
    public function __construct(
        private readonly GrantGuard $guard,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Gives a member of staff a role over a scope. The actor must hold every
     * permission of the role over a scope containing the one handed out.
     */
    public function handle(Identity $actor, Staff $staff, Role $role, Scope $scope, bool $confirmCritical = false): RoleAssignment
    {
        $this->guard->assertMay($actor, ['roles.assign', 'roles.manage']);

        if (! $staff->isActive()) {
            throw new InvalidArgumentException('Roles can only be assigned to active staff.');
        }

        return $this->transaction(function () use ($actor, $staff, $role, $scope, $confirmCritical) {
            $permissions = $role->permissionKeys();

            $this->guard->assertActorHolds($actor, $permissions);
            $this->guard->assertActorCoversScope($actor, $permissions, $scope);
            $this->guard->assertCriticalConfirmed($permissions, $confirmCritical);

            $exists = RoleAssignment::query()
                ->where('role_id', $role->id)
                ->where('staff_id', $staff->id)
                ->where('scope_type', $scope->type->value)
                ->whereRaw("coalesce(scope::text, '') = coalesce(?::jsonb::text, '')", [$scope->toStored() === null ? null : json_encode($scope->toStored())])
                ->exists();

            if ($exists) {
                throw new InvalidArgumentException('This role is already assigned to that person for that scope.');
            }

            $assignment = RoleAssignment::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'role_id' => $role->id,
                'staff_id' => $staff->id,
                'scope_type' => $scope->type,
                'scope' => $scope->toStored(),
            ]);

            $this->audit(new AuditEntry('roles.assigned', 'role_assignment', $assignment->ulid, null, [
                'role' => $role->ulid,
                'staff' => $staff->ulid,
                'scope_type' => $scope->type->value,
                'scope' => $scope->toStored(),
                'critical_confirmed' => $this->guard->containsCritical($permissions),
            ]));

            $this->authorizer->flush();

            return $assignment;
        });
    }
}
