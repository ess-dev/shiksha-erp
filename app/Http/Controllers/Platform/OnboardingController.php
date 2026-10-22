<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Actions\OnboardSchool;
use App\Domain\Platform\Queries\OnboardingOptionsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\OnboardSchoolRequest;
use App\Tenancy\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function create(OnboardingOptionsQuery $options): Response
    {
        return Inertia::render('platform/onboard-school', [
            'options' => $options->get(),
        ]);
    }

    public function store(OnboardSchoolRequest $request, OnboardSchool $onboard): RedirectResponse
    {
        $result = $onboard->handle($request->toData());

        // Shown once on the next page; set-password links are never stored.
        $request->session()->flash('onboarded', $result->jsonSerialize());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':school created', ['school' => $result->name])]);

        return to_route('platform.schools.onboarded', $result->ulid);
    }

    public function done(Tenant $school, OnboardingOptionsQuery $options): Response|RedirectResponse
    {
        $onboarded = session('onboarded');

        if (! is_array($onboarded) || ($onboarded['ulid'] ?? null) !== $school->ulid) {
            return to_route('platform.schools.show', $school);
        }

        return Inertia::render('platform/school-onboarded', [
            'onboarded' => $onboarded,
            'roles' => $options->get()['staff_roles'],
            'link_minutes' => (int) config('auth.passwords.users.expire'),
        ]);
    }
}
