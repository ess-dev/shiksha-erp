<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Contracts\StaffLeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\Queries\StaffLeaveCoverQuery;
use App\Domain\Leave\StaffLeaveAccess;
use App\Domain\Shared\Action;

class DecideStaffLeave extends Action
{
    public function __construct(
        private readonly StaffLeaveAccess $access,
        private readonly StaffLeaveCoverQuery $cover,
        private readonly StaffRefQuery $staff,
        private readonly StaffLeaveNotifier $notifier,
    ) {}

    /**
     * Approves or rejects one pending request. Needs `staff.leave_approve`
     * over the applicant's class (or the whole school when they lead none);
     * a request outside that is a 404, the person's own is a 403. Rejecting
     * needs a reason for the applicant. Approving a class teacher's leave
     * needs the staff member who marks that class's register on those days
     * (`$coverUlid`): active, not the applicant and not on leave on those
     * days. That person may then mark the register for the leave dates only
     * (see AttendanceAccess); the access is worked out from this request.
     *
     * @param  string  $decision  approve or reject
     *
     * @throws LeaveException
     */
    public function handle(Identity $actor, string $leaveUlid, string $decision, ?string $note, ?string $coverUlid = null): StaffLeaveRequest
    {
        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw LeaveException::unknownDecision();
        }

        $leave = $this->access->requestOrFail($actor, $leaveUlid);

        if ($leave->status !== LeaveStatus::Pending) {
            throw LeaveException::notOpen();
        }

        if (! $this->access->mayDecide($actor, $leave)) {
            throw LeaveException::notAllowed();
        }

        $note = $note === null ? null : trim($note);

        if ($note !== null && mb_strlen($note) > LeaveRules::NOTE_MAX) {
            throw LeaveException::noteTooLong(LeaveRules::NOTE_MAX);
        }

        if ($decision === 'reject' && ($note === null || mb_strlen($note) < LeaveRules::NOTE_MIN)) {
            throw LeaveException::noteNeeded('reject');
        }

        $coverKey = null;

        if ($decision === 'approve' && $leave->section_id !== null) {
            $section = $this->access->sections()[$leave->section_id] ?? null;

            if ($coverUlid === null || $coverUlid === '') {
                throw LeaveException::coverNeeded($section?->label() ?? 'the class');
            }

            $cover = $this->staff->find($coverUlid);

            if ($cover === null || ! $cover->isActive || $cover->staffKey === $leave->staff_id
                || $this->cover->isOnLeave($cover->staffKey, $leave->from_on, $leave->to_on)) {
                throw LeaveException::coverInvalid();
            }

            $coverKey = $cover->staffKey;
        }

        $decidedBy = $this->access->staffKey($actor) ?? throw LeaveException::notAllowed();
        $note = $note === '' ? null : $note;

        $this->transaction(function () use ($leave, $decision, $note, $decidedBy, $coverKey, $coverUlid) {
            $leave->update([
                'status' => $decision === 'approve' ? LeaveStatus::Approved : LeaveStatus::Rejected,
                'decided_by_staff_id' => $decidedBy,
                'decided_at' => now(),
                'decision_note' => $note,
                'cover_staff_id' => $coverKey,
            ]);

            $this->audit(new AuditEntry($decision === 'approve' ? 'staff_leave.approved' : 'staff_leave.rejected', 'staff_leave_request', $leave->ulid, ['status' => LeaveStatus::Pending->value], [
                'status' => $leave->status->value,
                'has_note' => $note !== null,
                'cover_staff' => $coverKey === null ? null : $coverUlid,
            ]));
        });

        $this->notifier->notify($decision === 'approve' ? 'approved' : 'rejected', $leave->ulid, (string) $this->staff->byKey($leave->staff_id)?->ulid);

        return $leave;
    }
}
