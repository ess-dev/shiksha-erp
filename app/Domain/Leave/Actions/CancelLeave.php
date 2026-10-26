<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Leave\Contracts\LeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\Shared\Action;
use App\Domain\Students\Data\GuardianChildRef;

class CancelLeave extends Action
{
    public function __construct(private readonly LeaveNotifier $notifier) {}

    /**
     * The guardian withdraws a request that nobody has decided yet (with the
     * class teacher or with the coordinator). The request stays, as cancelled.
     * It must belong to the child in the parent context, else 404.
     *
     * @param  array{is_current: bool}  $session
     *
     * @throws LeaveException
     */
    public function handle(GuardianChildRef $child, array $session, string $leaveUlid): StudentLeaveRequest
    {
        $leave = StudentLeaveRequest::query()->where('ulid', $leaveUlid)->where('student_id', $child->studentKey)->first()
            ?? throw LeaveException::notFound();

        if (! $session['is_current']) {
            throw LeaveException::archived();
        }

        if (! $leave->status->isOpen()) {
            throw LeaveException::notOpen();
        }

        $this->transaction(function () use ($leave, $child) {
            $before = $leave->status->value;

            $leave->update(['status' => LeaveStatus::Cancelled, 'cancelled_at' => now()]);

            $this->audit(new AuditEntry('student_leave.cancelled', 'student_leave_request', $leave->ulid, ['status' => $before], [
                'status' => LeaveStatus::Cancelled->value,
                'student' => $child->ulid,
            ]));
        });

        $this->notifier->notify('cancelled', $leave->ulid, $child->ulid);

        return $leave;
    }
}
