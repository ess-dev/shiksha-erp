<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Actions\ApplyForLeave;
use App\Domain\Leave\Actions\CancelLeave;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Queries\ParentLeaveQuery;
use App\Domain\Students\Data\ParentPortalContext;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(
        private readonly ParentPortalContextQuery $portal,
        private readonly SchoolContext $contexts,
    ) {}

    /**
     * The selected child's leave requests for the viewed session
     * (`?child=<ulid>`) and the form to apply for more.
     */
    public function index(Request $request, ParentLeaveQuery $leave): Response
    {
        $context = $this->context($request);

        return Inertia::render('parent/leave', [
            'shell' => $context->shell,
            'requests' => $context->child === null ? [] : $leave->get($context->child, $context->session),
            'form' => $context->child === null ? null : $leave->form($context->child),
            'child_ulid' => $context->child?->ulid,
            'child_first_name' => $context->child?->firstName,
            'session_name' => $context->session['name'],
            'is_current_session' => $context->session['is_current'],
        ]);
    }

    public function store(StoreLeaveRequest $request, ApplyForLeave $apply): RedirectResponse
    {
        $context = $this->context($request);
        $child = $context->child ?? throw LeaveException::notFound();

        $leave = $apply->handle(
            $this->identity($request),
            $child,
            $context->session,
            $request->string('from_on')->toString(),
            $request->string('to_on')->toString(),
            $request->string('reason_type')->toString(),
            $request->string('reason')->toString(),
            $request->file('attachment'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Leave request for 1 day sent.|[2,*] Leave request for :count days sent.', $leave->school_days)]);

        return back();
    }

    public function cancel(Request $request, string $leave, CancelLeave $cancel): RedirectResponse
    {
        $context = $this->context($request);

        $cancel->handle($context->child ?? throw LeaveException::notFound(), $context->session, $leave);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request cancelled.')]);

        return back();
    }

    private function context(Request $request): ParentPortalContext
    {
        $identity = $this->identity($request);

        return $this->portal->resolve(
            $identity,
            $request->filled('child') ? $request->string('child')->toString() : null,
            $request->session()->get('parent.session'),
            $this->contexts->canActAsStaff($identity),
        );
    }

    private function identity(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
