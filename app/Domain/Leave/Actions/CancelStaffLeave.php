<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Contracts\StaffLeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\StaffLeaveAccess;
use App\Domain\Shared\Action;

class CancelStaffLeave extends Action
{
    public function __construct(
        private readonly StaffLeaveAccess $access,
        private readonly StaffRefQuery $staff,
        private readonly StaffLeaveNotifier $notifier,
    ) {}

    /**
     * The applicant withdraws a request nobody has decided yet. The request
     * stays, as cancelled. It must be the person's own, else 404.
     *
     * @throws LeaveException
     */
    public function handle(Identity $actor, string $leaveUlid): StaffLeaveRequest
    {
        $staffKey = $this->access->staffKey($actor) ?? throw LeaveException::notAllowed();
        $leave = StaffLeaveRequest::query()->where('ulid', $leaveUlid)->where('staff_id', $staffKey)->first()
            ?? throw LeaveException::notFound();

        if ($leave->status !== LeaveStatus::Pending) {
            throw LeaveException::notOpen();
        }

        $this->transaction(function () use ($leave) {
            $leave->update(['status' => LeaveStatus::Cancelled, 'cancelled_at' => now()]);

            $this->audit(new AuditEntry('staff_leave.cancelled', 'staff_leave_request', $leave->ulid, ['status' => LeaveStatus::Pending->value], [
                'status' => LeaveStatus::Cancelled->value,
            ]));
        });

        $this->notifier->notify('cancelled', $leave->ulid, (string) $this->staff->byKey($staffKey)?->ulid);

        return $leave;
    }
}
