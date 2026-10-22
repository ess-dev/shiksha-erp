<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Access\RoleBuilder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Support\Str;

class SetUpSchoolOwner extends Action
{
    public const OWNER_TEMPLATE = 'principal';

    public function __construct(
        private readonly RoleBuilder $builder,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Platform step: gives a school its first owner (staff record, owner role
     * from the principal template, school-wide assignment). Not reachable from a
     * school's own screens, so it carries no actor check. Safe to repeat.
     */
    public function handle(Tenant $tenant, Identity $identity, string $fullName): Staff
    {
        return $tenant->execute(fn () => $this->transaction(function () use ($identity, $fullName) {
            $staff = Staff::query()->firstOrCreate(
                ['identity_ulid' => $identity->ulid],
                ['ulid' => strtolower((string) Str::ulid()), 'full_name' => $fullName, 'status' => 'active'],
            );

            $role = Role::query()->where('is_owner', true)->first()
                ?? $this->builder->fromTemplate(self::OWNER_TEMPLATE)['role'];

            // Platform step, so the owner role is brought up to the latest template
            // without the self-grant check an ordinary update would apply.
            if (($plan = $this->builder->pendingUpdate($role)) !== null) {
                $this->builder->applyUpdate($role, $plan);
            }

            RoleAssignment::query()->firstOrCreate(
                ['role_id' => $role->id, 'staff_id' => $staff->id, 'scope_type' => 'school'],
                ['ulid' => strtolower((string) Str::ulid()), 'scope' => Scope::school()->toStored()],
            );

            $this->audit(new AuditEntry('access.owner_set_up', 'staff', $staff->ulid, null, [
                'role' => $role->ulid,
                'scope_type' => 'school',
            ]));

            $this->authorizer->flush();

            return $staff;
        }));
    }
}
