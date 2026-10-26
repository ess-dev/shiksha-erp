<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\LeaveSettingsData;
use App\Domain\School\Actions\UpdateLeaveSettings;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\UpdateLeaveSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveSettingsController extends Controller
{
    public function show(SchoolSettingsQuery $settings): Response
    {
        Gate::authorize('settings.manage');

        return Inertia::render('leave/settings', [
            'settings' => new LeaveSettingsData($settings->leaveEscalationDays()),
        ]);
    }

    public function update(UpdateLeaveSettingsRequest $request, UpdateLeaveSettings $update): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $update->handle($identity, $request->integer('escalation_days'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave settings saved.')]);

        return back();
    }
}
