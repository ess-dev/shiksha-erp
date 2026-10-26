<?php

namespace App\Http\Controllers\Leave;

use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Actions\DecideLeave;
use App\Domain\Leave\LeaveAccess;
use App\Domain\Leave\Queries\StaffLeaveQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\DecideLeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    /**
     * The staff Student leave screen: what waits for the person (`?tab=review`,
     * the default) or every request from the sections they cover (`?tab=all`).
     */
    public function index(Request $request, LeaveAccess $access, StaffLeaveQuery $leave): Response
    {
        $actor = $this->actor($request);
        abort_unless($access->mayOpen($actor), 403);

        return Inertia::render('leave/index', [
            'screen' => $leave->screen($actor, $request->query('tab') === 'all' ? 'all' : 'review'),
        ]);
    }

    public function decide(DecideLeaveRequest $request, string $leave, DecideLeave $decide): RedirectResponse
    {
        $decision = $request->string('decision')->toString();
        $decide->handle($this->actor($request), $leave, $decision, $request->filled('note') ? $request->string('note')->toString() : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($decision) {
            'approve' => __('Leave approved. The parents have been told.'),
            'reject' => __('Leave rejected. The parents have been told.'),
            default => __('Sent to the coordinator.'),
        }]);

        return back();
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
