<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\GrantGuard;
use App\Domain\Access\Models\Role;
use App\Domain\Access\RoleBuilder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use InvalidArgumentException;

class ApplyRoleTemplateUpdate extends Action
{
    public function __construct(
        private readonly GrantGuard $guard,
        private readonly RoleBuilder $builder,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Brings a role up to the latest version of the template it was copied
     * from: permissions the new version adds are added, ones it drops are
     * removed, and the school's own edits stay. The same rules as any other
     * change to a role apply to what gets added.
     */
    public function handle(Identity $actor, Role $role, bool $confirmCritical = false): Role
    {
        $this->guard->assertMay($actor, ['roles.manage']);

        return $this->transaction(function () use ($actor, $role, $confirmCritical) {
            $plan = $this->builder->pendingUpdate($role)
                ?? throw new InvalidArgumentException('This role has no template update waiting.');

            $this->guard->assertAvailable($plan['added'], $role->is_owner);
            $this->guard->assertCriticalConfirmed($plan['added'], $confirmCritical);

            // The owner role is brought up to date by an owner (roles.manage is
            // owner-only): nobody else could hold what a new version adds yet.
            if (! $role->is_owner) {
                $this->guard->assertActorHolds($actor, $plan['added']);

                foreach ($role->assignments as $assignment) {
                    $this->guard->assertActorCoversScope($actor, $plan['added'], $assignment->toScope());
                }
            }

            $from = $role->source_template_version;
            $this->builder->applyUpdate($role, $plan);

            $this->audit(new AuditEntry('roles.template_update_applied', 'role', $role->ulid, ['version' => $from], [
                'version' => $plan['to_version'],
                'added' => $plan['added'],
                'removed' => $plan['removed'],
                'critical_confirmed' => $this->guard->containsCritical($plan['added']),
            ]));

            $this->authorizer->flush();

            return $role;
        });
    }
}
