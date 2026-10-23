<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Platform\Data\PlatformOverviewData;
use App\Domain\Platform\Models\Plan;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;

class PlatformOverviewQuery
{
    public function get(): PlatformOverviewData
    {
        /** @var array<string, int> $counts */
        $counts = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        $byStatus = [];

        foreach (TenantStatus::cases() as $status) {
            $byStatus[$status->value] = $counts[$status->value] ?? 0;
        }

        $planNames = Plan::query()->orderBy('sort_order')->get()->map(fn (Plan $plan) => $plan->name)->all();

        return new PlatformOverviewData(CalendarDate::today(), array_sum($byStatus), $byStatus, array_values($planNames));
    }
}
