<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\Staff;
use App\Domain\Access\RoleBuilder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * Platform step during onboarding: adds a member of staff to a new school and,
 * when a role template is named, gives them that role over the whole school
 * (the school's owner can narrow the scope later). The owner is set up
 * separately by SetUpSchoolOwner.
 */
class OnboardStaffMember extends Action
{
    public function __construct(
        private readonly RoleBuilder $builder,
        private readonly AssignRole $assignRole,
    ) {}

    public function handle(Tenant $tenant, Identity $actor, Identity $identity, string $fullName, ?string $templateKey): Staff
    {
        return $tenant->execute(fn () => $this->transaction(function () use ($actor, $identity, $fullName, $templateKey) {
            $staff = Staff::query()->firstOrCreate(
                ['identity_ulid' => $identity->ulid],
                ['ulid' => strtolower((string) Str::ulid()), 'full_name' => $fullName, 'status' => 'active'],
            );

            $this->audit(new AuditEntry('staff.onboarded', 'staff', $staff->ulid, null, [
                'identity_ulid' => $identity->ulid,
                'template' => $templateKey,
            ]));

            if ($templateKey !== null) {
                $role = Role::query()->where('source_template_key', $templateKey)->first()
                    ?? $this->builder->fromTemplate($templateKey)['role'];

                // The platform confirms critical permissions here; the holder is sent to
                // two-step setup at first sign-in.
                $this->assignRole->handle($actor, $staff, $role, Scope::school(), confirmCritical: true);
            }

            return $staff;
        }));
    }
}
