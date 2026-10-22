<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Platform\Data\ModuleData;
use App\Domain\Platform\Data\PlanData;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use App\Tenancy\Tenant;

class PlansQuery
{
    /**
     * @return list<PlanData>
     */
    public function plans(): array
    {
        /** @var array<int, int> $schoolsByPlan */
        $schoolsByPlan = Tenant::query()
            ->whereNotNull('plan_id')
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id')
            ->map(fn ($total) => (int) $total)
            ->all();

        $plans = Plan::query()
            ->with('planModules')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => new PlanData(
                ulid: $plan->ulid,
                name: $plan->name,
                description: $plan->description,
                badge: $plan->badge,
                pricePerStudentYearPaise: $plan->price_per_student_year_paise,
                minStudents: $plan->min_students,
                moduleKeys: array_values($plan->planModules->map(fn (PlanModule $row) => $row->module_key)->all()),
                schoolsCount: $schoolsByPlan[$plan->id] ?? 0,
            ))
            ->all();

        return array_values($plans);
    }

    /**
     * @return list<ModuleData>
     */
    public function modules(): array
    {
        $modules = PlatformModule::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PlatformModule $module) => new ModuleData($module->key, $module->label))
            ->all();

        return array_values($modules);
    }
}
