<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Moves a school to another plan. The school's modules follow the new plan
 * at once, dropping any add-ons or switched-off modules set by hand. Billing
 * adjustments are not built yet.
 */
class ChangeSchoolPlan extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Tenant $tenant, Plan $plan): Tenant
    {
        return $this->transaction(function () use ($tenant, $plan) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            $previous = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);

            $tenant->forceFill(['plan_id' => $plan->id])->save();

            $planKeys = $plan->planModules()->get()->map(fn (PlanModule $row) => $row->module_key)->all();
            $sellableKeys = PlatformModule::query()->pluck('key')->all();

            $modules = DB::connection(config('multitenancy.landlord_database_connection_name'))->table('tenant_modules');

            (clone $modules)->where('tenant_id', $tenant->id)->whereIn('module', $sellableKeys)->delete();

            foreach ($planKeys as $key) {
                (clone $modules)->insertOrIgnore([
                    'tenant_id' => $tenant->id,
                    'module' => $key,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->audit(new AuditEntry(
                action: 'platform.school_plan_changed',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['plan' => $previous?->key],
                after: ['plan' => $plan->key],
            ), $tenant->id);

            return $tenant;
        });
    }
}
