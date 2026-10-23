<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Permission;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fictional demo schools for local development. Safe to re-run.
 */
class DemoSchoolsSeeder extends Seeder
{
    /**
     * Demo schools get every module in the permission catalog (their "plan").
     */
    private function enableAllModules(Tenant $tenant): void
    {
        $modules = Permission::query()->where('module', '!=', Tenant::CORE_MODULE)->distinct()->pluck('module');

        foreach ($modules as $module) {
            DB::connection('landlord')->table('tenant_modules')->insertOrIgnore([
                'tenant_id' => $tenant->id,
                'module' => $module,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function run(ProvisionTenant $provision): void
    {
        $schools = [
            'greenfield' => 'Greenfield Public School',
            'riverside' => 'Riverside International School',
        ];

        foreach ($schools as $subdomain => $name) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            if ($tenant === null) {
                $tenant = $provision->handle($name, $subdomain, 'active');
            } else {
                $provision->migrate($tenant);
            }

            $this->enableAllModules($tenant);
        }
    }
}
