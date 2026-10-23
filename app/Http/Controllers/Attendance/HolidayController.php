<?php

namespace App\Http\Controllers\Attendance;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\CreateHoliday;
use App\Domain\School\Actions\RemoveHoliday;
use App\Domain\School\Models\Holiday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreHolidayRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function store(StoreHolidayRequest $request, CreateHoliday $create): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $create->handle($identity, $request->string('date')->toString(), $request->string('name')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holiday added.')]);

        return back();
    }

    public function destroy(Request $request, Holiday $holiday, RemoveHoliday $remove): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $remove->handle($identity, $holiday);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Holiday removed.')]);

        return back();
    }
}
