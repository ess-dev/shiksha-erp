<?php

namespace App\Http\Controllers\Students;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\UpdateSchoolSettings;
use App\Domain\School\Queries\HousesQuery;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\UpdateNumberingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentSettingsController extends Controller
{
    /**
     * Houses and the admission / TC number prefixes. Each card's controls
     * show only to someone who may change them.
     */
    public function show(HousesQuery $houses, SchoolSettingsQuery $settings): Response
    {
        Gate::authorize('students.view');

        return Inertia::render('students/settings', [
            'houses' => $houses->all(),
            'numbering' => $settings->data(),
        ]);
    }

    public function updateNumbering(UpdateNumberingRequest $request, UpdateSchoolSettings $updateSettings): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $updateSettings->handle($identity, $request->string('admission_prefix')->toString(), $request->string('tc_prefix')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Number prefixes saved.')]);

        return back();
    }
}
