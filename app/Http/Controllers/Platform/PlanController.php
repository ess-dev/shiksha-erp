<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\UpdatePlan;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Queries\PlansQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlanRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(PlansQuery $plans): Response
    {
        return Inertia::render('platform/plans', [
            'plans' => $plans->plans(),
            'modules' => $plans->modules(),
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan, UpdatePlan $updatePlan): RedirectResponse
    {
        $updatePlan->handle($plan, $request->pricePaise(), $request->integer('min_students'), $request->moduleKeys());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':plan plan saved', ['plan' => $plan->name])]);

        return back();
    }
}
