<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Audit\Queries\PlatformActivityQuery;
use App\Domain\Platform\Data\SchoolDetailData;
use App\Domain\Platform\Enums\SuspensionReason;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use App\Domain\Platform\MonthlyRevenue;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

class SchoolDetailQuery
{
    private const ACTIVITY_LIMIT = 6;

    public function __construct(private readonly PlatformActivityQuery $activity) {}

    public function get(Tenant $tenant): SchoolDetailData
    {
        $plans = Plan::query()->orderBy('sort_order')->get();
        $plan = $tenant->plan_id === null ? null : $plans->firstWhere('id', $tenant->plan_id);

        $modules = PlatformModule::query()->orderBy('sort_order')->get();
        $labels = $modules->pluck('label', 'key')->all();

        /** @var list<string> $enabled */
        $enabled = DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->table('tenant_modules')->where('tenant_id', $tenant->id)->pluck('module')->all();

        $inPlan = $plan === null ? [] : $plan->planModules()->get()->map(fn (PlanModule $row) => $row->module_key)->all();

        $moduleRows = [];

        foreach ($modules as $module) {
            $isOn = in_array($module->key, $enabled, true);
            $isInPlan = in_array($module->key, $inPlan, true);

            $moduleRows[] = [
                'key' => $module->key,
                'label' => $module->label,
                'enabled' => $isOn,
                'badge' => match (true) {
                    $isOn && $isInPlan => 'in_plan',
                    $isOn => 'add_on',
                    $isInPlan => 'turned_off',
                    default => null,
                },
            ];
        }

        return new SchoolDetailData(
            ulid: $tenant->ulid,
            name: $tenant->name,
            city: $tenant->city,
            state: $tenant->state,
            board: $tenant->board,
            campuses: $tenant->campus_count,
            sinceOn: $tenant->since_on,
            status: $tenant->status->value,
            suspendedReason: SuspensionReason::tryFrom((string) $tenant->suspended_reason)?->label(),
            trialEndsOn: $tenant->trial_ends_on,
            renewsOn: $tenant->renews_on,
            planUlid: $plan?->ulid,
            planMinStudents: $plan?->min_students,
            students: $tenant->student_count,
            billableStudents: $plan === null ? $tenant->student_count : max($tenant->student_count, $plan->min_students),
            annualPaise: $plan === null ? null : MonthlyRevenue::annualPaise($plan, $tenant->student_count),
            mrrPaise: MonthlyRevenue::paise($plan, $tenant->status, $tenant->student_count),
            contactName: $tenant->contact_name,
            contactEmail: $tenant->contact_email,
            modules: $moduleRows,
            plans: array_values($plans->map(fn (Plan $row) => [
                'ulid' => $row->ulid,
                'name' => $row->name,
                'price_per_student_year_paise' => $row->price_per_student_year_paise,
            ])->all()),
            suspensionReasons: array_map(
                fn (SuspensionReason $reason) => ['key' => $reason->value, 'label' => $reason->label()],
                SuspensionReason::cases(),
            ),
            activity: $this->activity($tenant, $labels),
            timezone: (string) config('shiksha.timezone'),
        );
    }

    /**
     * @param  array<string, string>  $moduleLabels
     * @return list<array{summary: string, at: string}>
     */
    private function activity(Tenant $tenant, array $moduleLabels): array
    {
        $rows = [];

        foreach ($this->activity->forSchool($tenant->id, self::ACTIVITY_LIMIT) as $event) {
            $after = $event->after;
            $moduleKey = is_string($after['module'] ?? null) ? $after['module'] : '';
            $module = $moduleLabels[$moduleKey] ?? $moduleKey;
            $reason = is_string($after['reason'] ?? null) ? SuspensionReason::tryFrom($after['reason']) : null;
            $planKey = is_string($after['plan'] ?? null) ? $after['plan'] : '';

            $summary = match ($event->action) {
                'platform.school_suspended' => 'suspended · '.($reason === null ? '' : $reason->label()),
                'platform.school_reactivated' => 'reactivated',
                'platform.school_plan_changed' => 'plan changed · '.$planKey,
                'platform.school_module_enabled' => 'module on · '.$module,
                'platform.school_module_disabled' => 'module off · '.$module,
                default => str_replace(['platform.', '_'], ['', ' '], $event->action),
            };

            $rows[] = ['summary' => rtrim($summary, ' ·'), 'at' => $event->occurredAt];
        }

        return $rows;
    }
}
