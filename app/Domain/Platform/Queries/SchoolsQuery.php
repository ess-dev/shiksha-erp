<?php

namespace App\Domain\Platform\Queries;

use App\Domain\Platform\Data\SchoolListData;
use App\Domain\Platform\Data\SchoolRowData;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\MonthlyRevenue;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use Illuminate\Database\Eloquent\Builder;

class SchoolsQuery
{
    public const PER_PAGE = 25;

    /**
     * @param  string|null  $status  a TenantStatus value, or null for all
     * @param  string|null  $planUlid  only schools on this plan
     * @param  string  $search  matches name, city, board or contact
     */
    public function list(?string $status, ?string $planUlid, string $search, int $page = 1): SchoolListData
    {
        $plans = Plan::query()->orderBy('sort_order')->get();
        $plansById = $plans->keyBy('id');

        $paginator = $this->filtered($status, $planUlid, $search)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, $page));

        $rows = [];

        foreach ($paginator->items() as $tenant) {
            /** @var Tenant $tenant */
            $plan = $tenant->plan_id === null ? null : $plansById->get($tenant->plan_id);

            $rows[] = $this->row($tenant, $plan);
        }

        /** @var array<string, int> $counts */
        $counts = Tenant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        $statusCounts = ['all' => array_sum($counts)];

        foreach (TenantStatus::cases() as $case) {
            $statusCounts[$case->value] = $counts[$case->value] ?? 0;
        }

        return new SchoolListData(
            rows: $rows,
            page: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            matching: $paginator->total(),
            totalSchools: $statusCounts['all'],
            totalStudents: (int) Tenant::query()->sum('student_count'),
            statusCounts: $statusCounts,
            plans: array_values($plans->map(fn (Plan $plan) => ['ulid' => $plan->ulid, 'name' => $plan->name])->all()),
        );
    }

    /**
     * Every school matching the filters (no paging), with the contact
     * details the CSV export adds.
     *
     * @return list<array{row: SchoolRowData, contact_name: ?string, contact_email: ?string, since_on: ?string}>
     */
    public function forExport(?string $status, ?string $planUlid, string $search): array
    {
        $plansById = Plan::query()->get()->keyBy('id');
        $entries = [];

        foreach ($this->filtered($status, $planUlid, $search)->orderBy('name')->orderBy('id')->cursor() as $tenant) {
            $plan = $tenant->plan_id === null ? null : $plansById->get($tenant->plan_id);

            $entries[] = [
                'row' => $this->row($tenant, $plan),
                'contact_name' => $tenant->contact_name,
                'contact_email' => $tenant->contact_email,
                'since_on' => $tenant->since_on,
            ];
        }

        return $entries;
    }

    /**
     * @return Builder<Tenant>
     */
    private function filtered(?string $status, ?string $planUlid, string $search): Builder
    {
        $query = Tenant::query();

        if ($status !== null && TenantStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        if ($planUlid !== null && $planUlid !== '') {
            $planId = Plan::query()->where('ulid', $planUlid)->value('id');

            // An unknown plan matches nothing rather than everything.
            $query->where('plan_id', $planId ?? 0);
        }

        $term = trim($search);

        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

            $query->where(function (Builder $inner) use ($like) {
                foreach (['name', 'city', 'board', 'contact_name'] as $column) {
                    $inner->orWhere($column, 'ilike', $like);
                }
            });
        }

        return $query;
    }

    private function row(Tenant $tenant, ?Plan $plan): SchoolRowData
    {
        return new SchoolRowData(
            ulid: $tenant->ulid,
            name: $tenant->name,
            city: $tenant->city,
            state: $tenant->state,
            board: $tenant->board,
            campuses: $tenant->campus_count,
            planName: $plan?->name,
            status: $tenant->status->value,
            students: $tenant->student_count,
            mrrPaise: MonthlyRevenue::paise($plan, $tenant->status, $tenant->student_count),
            trialEndsOn: $tenant->trial_ends_on,
            renewsOn: $tenant->renews_on,
        );
    }
}
