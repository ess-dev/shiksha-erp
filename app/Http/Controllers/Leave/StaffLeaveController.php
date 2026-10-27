<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Actions\DecideStaffLeave;
use App\Domain\Leave\Queries\StaffLeaveRequestsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\DecideStaffLeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffLeaveController extends Controller
{
    /**
     * The Staff and leave screen: leave requests of staff the person may
     * decide on (`?tab=leave`, the default) and the staff directory
     * (`?tab=directory`). Each tab needs its own permission.
     */
    public function index(Request $request, StaffLeaveRequestsQuery $screen): Response
    {
        $actor = $this->actor($request);
        abort_unless($screen->mayOpen($actor), 403);

        return Inertia::render('leave/staff', [
            'screen' => $screen->screen($actor, $request->query('tab') === 'directory' ? 'directory' : 'leave'),
        ]);
    }

    public function decide(DecideStaffLeaveRequest $request, string $leave, DecideStaffLeave $decide): RedirectResponse
    {
        $decision = $request->string('decision')->toString();
        $decide->handle(
            $this->actor($request),
            $leave,
            $decision,
            $request->filled('note') ? $request->string('note')->toString() : null,
            $request->filled('cover') ? $request->string('cover')->toString() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $decision === 'approve'
            ? __('Leave approved. The staff member has been told.')
            : __('Leave rejected. The staff member has been told.')]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
