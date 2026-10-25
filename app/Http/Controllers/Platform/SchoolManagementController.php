<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\ChangeSchoolPlan;
use App\Domain\Platform\Actions\DeleteSchool;
use App\Domain\Platform\Actions\ReactivateSchool;
use App\Domain\Platform\Actions\RetrySchoolDeletion;
use App\Domain\Platform\Actions\SetSchoolModule;
use App\Domain\Platform\Actions\SetSchoolPaymentGateway;
use App\Domain\Platform\Actions\SuspendSchool;
use App\Domain\Platform\Exceptions\SchoolChangeException;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangeSchoolPlanRequest;
use App\Http\Requests\Platform\DeleteSchoolRequest;
use App\Http\Requests\Platform\SetSchoolModuleRequest;
use App\Http\Requests\Platform\SetSchoolPaymentGatewayRequest;
use App\Http\Requests\Platform\SuspendSchoolRequest;
use App\Tenancy\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Changes to one school made from its details screen.
 */
class SchoolManagementController extends Controller
{
    public function suspend(SuspendSchoolRequest $request, Tenant $school, SuspendSchool $suspend): RedirectResponse
    {
        try {
            $suspend->handle($school, $request->reason());
        } catch (SchoolChangeException $exception) {
            return $this->refused($exception);
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => __(':school suspended', ['school' => $school->name])]);

        return back();
    }

    public function reactivate(Tenant $school, ReactivateSchool $reactivate): RedirectResponse
    {
        try {
            $reactivate->handle($school);
        } catch (SchoolChangeException $exception) {
            return $this->refused($exception);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':school is active again', ['school' => $school->name])]);

        return back();
    }

    public function changePlan(ChangeSchoolPlanRequest $request, Tenant $school, ChangeSchoolPlan $change): RedirectResponse
    {
        $plan = Plan::query()->where('ulid', $request->string('plan')->toString())->firstOrFail();

        $change->handle($school, $plan);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan changed to :plan', ['plan' => $plan->name])]);

        return back();
    }

    public function setModule(SetSchoolModuleRequest $request, Tenant $school, SetSchoolModule $setModule): RedirectResponse
    {
        $key = $request->string('module')->toString();
        $enabled = $request->boolean('enabled');

        try {
            $setModule->handle($school, $key, $enabled);
        } catch (SchoolChangeException $exception) {
            return $this->refused($exception);
        }

        $label = PlatformModule::query()->where('key', $key)->value('label');

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':module turned :state for :school', [
            'module' => $label, 'state' => $enabled ? 'on' : 'off', 'school' => $school->name,
        ])]);

        return back();
    }

    public function setPaymentGateway(SetSchoolPaymentGatewayRequest $request, Tenant $school, SetSchoolPaymentGateway $setGateway): RedirectResponse
    {
        try {
            $setGateway->handle($school, $request->input('gateway'));
        } catch (SchoolChangeException $exception) {
            return $this->refused($exception);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment gateway updated for :school', ['school' => $school->name])]);

        return back();
    }

    public function destroy(DeleteSchoolRequest $request, Tenant $school, DeleteSchool $delete): RedirectResponse
    {
        $name = $school->name;

        try {
            $delete->handle($school, $request->string('confirmation')->toString());
        } catch (SchoolChangeException $exception) {
            return $this->refused($exception);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':school is being deleted. It disappears from this list when the removal finishes.', ['school' => $name])]);

        return to_route('platform.schools.index');
    }

    public function retryDeletion(Tenant $school, RetrySchoolDeletion $retry): RedirectResponse
    {
        try {
            $retry->handle($school);
        } catch (SchoolChangeException $exception) {
            return $this->refused($exception);
        }

        Inertia::flash('toast', ['type' => 'warning', 'message' => __('Deleting :school again', ['school' => $school->name])]);

        return back();
    }

    private function refused(SchoolChangeException $exception): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

        return back();
    }
}
