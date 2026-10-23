<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Access\Queries\StaffRoleOptionsQuery;
use App\Domain\Platform\Actions\OnboardSchool;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use App\Domain\Shared\CalendarDate;

class OnboardingOptionsQuery
{
    public function __construct(private readonly StaffRoleOptionsQuery $roles) {}

    /**
     * Everything the onboarding wizard offers.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $plans = Plan::query()->with('planModules')->orderBy('sort_order')->get()->map(fn (Plan $plan) => [
            'ulid' => $plan->ulid,
            'name' => $plan->name,
            'price_per_student_year_paise' => $plan->price_per_student_year_paise,
            'min_students' => $plan->min_students,
            'module_keys' => array_values($plan->planModules->map(fn (PlanModule $row) => $row->module_key)->all()),
        ])->all();

        $modules = PlatformModule::query()->orderBy('sort_order')->get()->map(fn (PlatformModule $module) => [
            'key' => $module->key,
            'label' => $module->label,
        ])->all();

        return [
            'plans' => array_values($plans),
            'modules' => array_values($modules),
            'boards' => array_values((array) config('shiksha.boards')),
            'states' => array_values((array) config('shiksha.states')),
            'staff_roles' => [...$this->roles->templates(), ['key' => OnboardSchool::TEACHER_ROLE, 'label' => 'Teacher (no role yet)', 'requires_two_step' => false]],
            'trial_days' => (int) config('shiksha.trial_days'),
            'base_domain' => (string) config('shiksha.base_domain'),
            'today' => CalendarDate::today(),
        ];
    }
}
