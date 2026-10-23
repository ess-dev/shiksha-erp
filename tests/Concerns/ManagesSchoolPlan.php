<?php

namespace Tests\Concerns;

use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use Illuminate\Support\Facades\DB;

/**
 * Plan modules and status of a test school, so tests do not repeat raw
 * `tenant_modules` inserts. Needs HasCurrentSchool (for `$this->school`).
 */
trait ManagesSchoolPlan
{
    /**
     * Puts the modules in the school's plan (already-present ones are left alone).
     *
     * @param  list<string>  $modules
     */
    protected function enableModules(array $modules, ?Tenant $school = null): void
    {
        $school ??= $this->school;

        foreach ($modules as $module) {
            DB::connection('landlord')->table('tenant_modules')->updateOrInsert(
                ['tenant_id' => $school->id, 'module' => $module],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    /**
     * Takes the module out of the school's plan.
     */
    protected function disableModule(string $module, ?Tenant $school = null): void
    {
        $school ??= $this->school;

        DB::connection('landlord')->table('tenant_modules')
            ->where('tenant_id', $school->id)
            ->where('module', $module)
            ->delete();
    }

    protected function setSchoolStatus(TenantStatus $status, ?Tenant $school = null): void
    {
        $school ??= $this->school;

        $school->update(['status' => $status]);
    }
}
