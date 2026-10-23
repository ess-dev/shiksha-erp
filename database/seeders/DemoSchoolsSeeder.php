<?php

namespace Database\Seeders;

use App\Domain\Access\Models\Permission;
use App\Domain\Platform\Models\Plan;
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

        // Fictional directory details; student counts stand in for the real
        // figure until the students module copies it to the landlord.
        $details = [
            'greenfield' => [
                'plan' => 'growth', 'city' => 'Mohali', 'state' => 'Punjab', 'board' => 'CBSE',
                'contact_name' => 'Meera Kapoor', 'contact_email' => 'office@greenfield.test',
                'campus_count' => 2, 'student_count' => 208, 'since_on' => '2025-04-01', 'renews_on' => '2027-03-31',
            ],
            'riverside' => [
                'plan' => 'premium', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'board' => 'CBSE',
                'contact_name' => 'Arjun Rao', 'contact_email' => 'office@riverside.test',
                'campus_count' => 3, 'student_count' => 2140, 'since_on' => '2025-04-01', 'renews_on' => '2027-03-31',
            ],
        ];

        foreach ($schools as $subdomain => $name) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            if ($tenant === null) {
                $tenant = $provision->handle($name, $subdomain, 'active');
            } else {
                $provision->migrate($tenant);
            }

            $this->enableAllModules($tenant);

            $detail = $details[$subdomain];
            $planId = Plan::query()->where('key', $detail['plan'])->value('id');
            unset($detail['plan']);

            // Fill gaps only, so edits made locally survive a re-seed.
            if ($tenant->city === null) {
                $tenant->forceFill([...$detail, 'plan_id' => $tenant->plan_id ?? $planId])->save();
            }
        }
    }
}
