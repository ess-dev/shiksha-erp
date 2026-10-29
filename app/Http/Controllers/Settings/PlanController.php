<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Platform\Queries\PlanAndModulesQuery;
use App\Http\Controllers\Controller;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * The school's plan and modules, read-only. Only the owner (who holds the
     * owner-only roles.manage permission) may open it; the Super Admin
     * changes the plan.
     */
    public function show(PlanAndModulesQuery $plan): Response
    {
        Gate::authorize('roles.manage');

        $tenant = Tenant::current();
        abort_if($tenant === null, 404);

        return Inertia::render('settings/plan', ['plan' => $plan->get($tenant)]);
    }
}
