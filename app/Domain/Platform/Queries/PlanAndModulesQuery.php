<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Platform\Data\PlanAndModulesData;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Reads the current school's own plan and module entitlements from the
 * landlord database. Only this school's rows are read.
 */
class PlanAndModulesQuery
{
    public function get(Tenant $tenant): PlanAndModulesData
    {
        $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);

        /** @var list<string> $enabled */
        $enabled = DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->table('tenant_modules')->where('tenant_id', $tenant->id)->pluck('module')->all();

        $inPlan = $plan === null ? [] : $plan->planModules()->get()->map(fn (PlanModule $row) => $row->module_key)->all();

        $rows = [];

        foreach (PlatformModule::query()->where('key', '!=', Tenant::CORE_MODULE)->orderBy('sort_order')->get() as $module) {
            $isOn = in_array($module->key, $enabled, true);
            $isInPlan = in_array($module->key, $inPlan, true);

            $rows[] = [
                'key' => $module->key,
                'label' => $module->label,
                'state' => match (true) {
                    $isOn && $isInPlan => 'in_plan',
                    $isOn => 'add_on',
                    $isInPlan => 'turned_off',
                    default => 'not_in_plan',
                },
            ];
        }

        return new PlanAndModulesData(
            school_name: $tenant->name,
            plan_name: $plan?->name,
            status: $tenant->status->value,
            trial_ends_on: $tenant->trial_ends_on,
            renews_on: $tenant->renews_on,
            students: $tenant->student_count,
            plan_min_students: $plan?->min_students,
            modules: $rows,
        );
    }
}
