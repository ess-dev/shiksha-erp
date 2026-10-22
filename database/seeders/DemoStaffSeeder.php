<?php

namespace Database\Seeders;

use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Models\Identity;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One fictional demo staff sign-in per demo school, for local development
 * only. The password below is a public demo value; the seeder refuses to run
 * in production. Safe to re-run.
 */
class DemoStaffSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(CreateIdentity $createIdentity, GrantMembership $grantMembership, SetUpSchoolOwner $setUpOwner): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo staff accounts must never be seeded in production.');
        }

        $staff = [
            'greenfield' => ['email' => 'principal@greenfield.test', 'phone' => '9876500001', 'name' => 'Meera Nair'],
            'riverside' => ['email' => 'principal@riverside.test', 'phone' => '9876500002', 'name' => 'Arjun Mehta'],
        ];

        foreach ($staff as $subdomain => $contact) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            if ($tenant === null) {
                continue;
            }

            $identity = Identity::query()->where('email', $contact['email'])->first()
                ?? $createIdentity->handle($contact['email'], $contact['phone'], self::DEMO_PASSWORD);

            $grantMembership->handle($identity, $tenant);
            $setUpOwner->handle($tenant, $identity, $contact['name']);
        }
    }
}
