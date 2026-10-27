<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Data\LeaveSettingsData;
use App\Domain\Leave\Data\StaffLeavePolicyRowData;
use App\Domain\Leave\Enums\StaffLeaveType;
use App\Domain\Leave\StaffLeavePolicy;
use App\Domain\School\Actions\UpdateLeaveSettings;
use App\Domain\School\Actions\UpdateStaffLeavePolicy;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\UpdateLeaveSettingsRequest;
use App\Http\Requests\Leave\UpdateStaffLeavePolicyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeaveSettingsController extends Controller
{
    public function show(SchoolSettingsQuery $settings, StaffLeavePolicy $policy): Response
    {
        Gate::authorize('settings.manage');

        return Inertia::render('leave/settings', [
            'settings' => new LeaveSettingsData(
                $settings->leaveEscalationDays(),
                array_map(
                    fn (StaffLeaveType $type) => new StaffLeavePolicyRowData(
                        $type->value,
                        $type->label(),
                        $policy->all()[$type->value]['days'],
                        $policy->all()[$type->value]['document_after_days'],
                    ),
                    StaffLeaveType::cases(),
                ),
            ),
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

    public function updateStaffPolicy(UpdateStaffLeavePolicyRequest $request, UpdateStaffLeavePolicy $update): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $policy = [];

        foreach (StaffLeaveType::cases() as $type) {
            $row = (array) $request->input("policy.{$type->value}", []);
            $policy[$type->value] = [
                'days' => isset($row['days']) && $row['days'] !== '' ? (int) $row['days'] : null,
                'document_after_days' => isset($row['document_after_days']) && $row['document_after_days'] !== '' ? (int) $row['document_after_days'] : null,
            ];
        }

        $update->handle($identity, $policy);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Staff leave policy saved.')]);

        return back();
    }
}
