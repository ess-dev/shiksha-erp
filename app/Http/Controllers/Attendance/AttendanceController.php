<?php

namespace App\Http\Controllers\Attendance;

use App\Domain\Attendance\Actions\SaveRegister;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Queries\RegisterQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveRegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * The daily register of one class on one day. `?section=<ulid>&date=Y-m-d`
     * choose which; anything the person may not open falls back to their
     * first class and today.
     */
    public function index(Request $request, RegisterQuery $registers): Response
    {
        abort_unless(Gate::any(['attendance.view', 'attendance.mark']), 403);

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $section = $request->query('section');
        $date = $request->query('date');

        return Inertia::render('attendance/index', [
            'register' => $registers->get(
                $identity,
                is_string($section) ? $section : null,
                is_string($date) ? $date : null,
            ),
        ]);
    }

    public function store(SaveRegisterRequest $request, SaveRegister $save): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $marks = [];

        foreach ($request->array('marks') as $studentUlid => $status) {
            $marks[(string) $studentUlid] = AttendanceStatus::from((string) $status);
        }

        $save->handle(
            $identity,
            $request->string('section')->toString(),
            $request->string('date')->toString(),
            $marks,
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Attendance saved.')]);

        return back();
    }
}
