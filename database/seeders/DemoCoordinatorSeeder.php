<?php

namespace Database\Seeders;

use App\Domain\Access\Actions\AssignRole;
use App\Domain\Access\Actions\CreateRole;
use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Data\Scope;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\Campus;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One fictional demo Coordinator at Greenfield, for local development only,
 * so the Principal and a scoped role can be compared side by side. The role is
 * copied from the platform's Coordinator template and assigned to a wing
 * (Classes 1 to 3 at the Main campus). The password is a public demo value;
 * the seeder refuses to run in production. Safe to re-run.
 * illustrative — verify: the wing is a placeholder.
 */
class DemoCoordinatorSeeder extends Seeder
{
    private const EMAIL = 'coordinator@greenfield.test';

    public function run(CreateIdentity $createIdentity, GrantMembership $grantMembership, CreateStaffRecord $createStaff, CreateRole $createRole, AssignRole $assignRole): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo staff accounts must never be seeded in production.');
        }

        $tenant = Tenant::query()->where('subdomain', 'greenfield')->first();
        $principal = Identity::query()->where('email', 'principal@greenfield.test')->first();

        if ($tenant === null || $principal === null || Identity::query()->where('email', self::EMAIL)->exists()) {
            return;
        }

        $identity = $createIdentity->handle(self::EMAIL, '9876500003', 'password');
        $grantMembership->handle($identity, $tenant);

        $tenant->execute(function () use ($identity, $principal, $createStaff, $createRole, $assignRole) {
            $campus = Campus::query()->where('code', 'MAIN')->first();

            if ($campus === null) {
                return;
            }

            $staff = $createStaff->handle($identity->ulid, 'Anjali Khanna');
            $role = $createRole->handle($principal, 'Coordinator, Classes 1 to 3', [], 'coordinator');
            $assignRole->handle($principal, $staff, $role, Scope::wing([$campus->ulid], 1, 3));
        });
    }
}
