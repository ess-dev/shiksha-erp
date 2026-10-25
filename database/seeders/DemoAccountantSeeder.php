<?php

namespace Database\Seeders;

use App\Domain\Access\Actions\ApplyRoleTemplateUpdate;
use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Access\Models\Role;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use InvalidArgumentException;
use RuntimeException;

/**
 * One fictional demo accounts clerk at Greenfield, for local development
 * only, so the fee screens can be tried without two-step sign-in (the real
 * Accountant template includes the critical refund permission, which needs
 * two-step). The role has every fee permission except refunds. The
 * password is a public demo value; the seeder refuses to run in production.
 * It also brings the owner role up to the latest template, because nobody can
 * hand on a permission they do not hold. Safe to re-run.
 * illustrative — verify: the role's permissions are a starting point.
 */
class DemoAccountantSeeder extends Seeder
{
    private const EMAIL = 'accountant@greenfield.test';

    public function run(CreateIdentity $createIdentity, GrantMembership $grantMembership, CreateStaffRecord $createStaff, CreateRole $createRole, AssignRole $assignRole, ApplyRoleTemplateUpdate $applyUpdate): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo staff accounts must never be seeded in production.');
        }

        $tenant = Tenant::query()->where('subdomain', 'greenfield')->first();
        $principal = Identity::query()->where('email', 'principal@greenfield.test')->first();

        if ($tenant === null || $principal === null) {
            return;
        }

        $tenant->execute(function () use ($principal, $applyUpdate) {
            foreach (Role::query()->where('is_owner', true)->get() as $role) {
                try {
                    $applyUpdate->handle($principal, $role, true);
                } catch (InvalidArgumentException) {
                    // already up to date
                }
            }
        });

        if (Identity::query()->where('email', self::EMAIL)->exists()) {
            return;
        }

        $identity = $createIdentity->handle(self::EMAIL, '9876500044', 'password');
        $grantMembership->handle($identity, $tenant);

        $tenant->execute(function () use ($identity, $principal, $createStaff, $createRole, $assignRole) {
            $staff = $createStaff->handle($identity->ulid, 'Harpreet Kaur');
            $role = $createRole->handle($principal, 'Accounts clerk', [
                'students.view', 'structure.view', 'fees.view', 'fees.collect', 'fees.remind', 'fees.reconcile',
                'fee_structures.manage', 'concessions.apply',
            ], null, true);
            $assignRole->handle($principal, $staff, $role, Scope::school());
        });
    }
}
