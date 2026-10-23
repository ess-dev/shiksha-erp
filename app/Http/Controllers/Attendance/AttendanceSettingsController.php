<?php

namespace App\Http\Controllers\Attendance;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\UpdateAttendanceCalendar;
use App\Domain\School\Queries\AttendanceCalendarQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\UpdateAttendanceCalendarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingsController extends Controller
{
    public function show(AttendanceCalendarQuery $calendar): Response
    {
        Gate::authorize('settings.manage');

        return Inertia::render('attendance/settings', [
            'calendar' => $calendar->get(),
        ]);
    }

    public function update(UpdateAttendanceCalendarRequest $request, UpdateAttendanceCalendar $update): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $update->handle(
            $identity,
            array_values(array_map(intval(...), $request->array('working_days'))),
            $request->integer('correction_window_days'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance settings saved.')]);

        return back();
    }
}
