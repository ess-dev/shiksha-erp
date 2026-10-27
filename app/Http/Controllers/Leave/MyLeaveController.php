<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Actions\ApplyForStaffLeave;
use App\Domain\Leave\Actions\CancelStaffLeave;
use App\Domain\Leave\Queries\MyLeaveQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ApplyForStaffLeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLeaveController extends Controller
{
    /** The signed-in staff member's balances, requests and the apply form. */
    public function index(Request $request, MyLeaveQuery $leave): Response
    {
        $leave = $leave->get($this->actor($request));
        abort_if($leave === null, 403);

        return Inertia::render('leave/my-leave', ['leave' => $leave]);
    }

    public function store(ApplyForStaffLeaveRequest $request, ApplyForStaffLeave $apply): RedirectResponse
    {
        $apply->handle(
            $this->actor($request),
            $request->string('leave_type')->toString(),
            $request->boolean('half_day'),
            $request->string('from_on')->toString(),
            $request->filled('to_on') ? $request->string('to_on')->toString() : null,
            $request->string('reason')->toString(),
            $request->filled('suggested_cover') ? $request->string('suggested_cover')->toString() : null,
            $request->file('attachment'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leave request sent. You will be told when it is decided.')]);

        return back();
    }

    public function cancel(Request $request, string $leave, CancelStaffLeave $cancel): RedirectResponse
    {
        $cancel->handle($this->actor($request), $leave);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request cancelled.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
