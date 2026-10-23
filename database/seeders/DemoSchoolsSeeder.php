<?php

namespace Database\Seeders;

use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;

/**
 * Fictional demo schools for local development. Safe to re-run.
 */
class DemoSchoolsSeeder extends Seeder
{
    public function run(ProvisionTenant $provision): void
    {
        $schools = [
            'greenfield' => 'Greenfield Public School',
            'riverside' => 'Riverside International School',
        ];

        foreach ($schools as $subdomain => $name) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            if ($tenant === null) {
                $provision->handle($name, $subdomain, 'active');
            } else {
                $provision->migrate($tenant);
            }
        }
    }
}
