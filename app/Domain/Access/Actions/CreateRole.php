<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Authorizer;
use App\Domain\Access\GrantGuard;
use App\Domain\Access\Models\Role;
use App\Domain\Access\RoleBuilder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

class CreateRole extends Action
{
    public function __construct(
        private readonly GrantGuard $guard,
        private readonly RoleBuilder $builder,
        private readonly Authorizer $authorizer,
    ) {}

    /**
     * Creates a role by copying a template, from a list of permissions, or both.
     *
     * @param  list<string>  $permissions  Extra permissions on top of the template's
     */
    public function handle(Identity $actor, string $name, array $permissions = [], ?string $templateKey = null, bool $confirmCritical = false): Role
    {
        $this->guard->assertMay($actor, ['roles.manage']);

        return $this->transaction(function () use ($actor, $name, $permissions, $templateKey, $confirmCritical) {
            if ($templateKey !== null) {
                ['role' => $role, 'permissions' => $fromTemplate] = $this->builder->fromTemplate($templateKey, $name);
            } else {
                $role = Role::query()->create(['ulid' => strtolower((string) Str::ulid()), 'name' => $name, 'is_owner' => false]);
                $fromTemplate = [];
            }

            $all = array_values(array_unique([...$fromTemplate, ...$permissions]));

            $this->guard->assertAvailable($all, $role->is_owner);
            $this->guard->assertActorHolds($actor, $all);
            $this->guard->assertCriticalConfirmed($all, $confirmCritical);

            $this->builder->setPermissions($role, $all);

            $this->audit(new AuditEntry('roles.created', 'role', $role->ulid, null, [
                'name' => $role->name,
                'template' => $templateKey,
                'is_owner' => $role->is_owner,
                'permissions' => $all,
                'critical_confirmed' => $this->guard->containsCritical($all),
            ]));

            $this->authorizer->flush();

            return $role;
        });
    }
}
