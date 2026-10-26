<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Contracts\LeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\LeaveAccess;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\DB;

class DecideLeave extends Action
{
    public function __construct(
        private readonly LeaveAccess $access,
        private readonly LeaveNotifier $notifier,
    ) {}

    /**
     * Approves, rejects or recommends one request. What the person may do comes
     * from LeaveAccess: short leave is approved by whoever holds
     * `leave.approve` over the student's section; longer leave is recommended
     * by them and decided by someone holding `leave.approve_long`. A request
     * outside the person's sections is a 404; one they may see but not do this
     * to is a 403. Rejecting and recommending need a note; approving may have
     * one. Parents are told through the LeaveNotifier after the change is
     * stored.
     *
     * @param  string  $decision  approve, reject or recommend
     *
     * @throws LeaveException
     */
    public function handle(Identity $actor, string $leaveUlid, string $decision, ?string $note): StudentLeaveRequest
    {
        if (! in_array($decision, ['approve', 'reject', 'recommend'], true)) {
            throw LeaveException::unknownDecision();
        }

        [$leave, $section] = $this->access->requestOrFail($actor, $leaveUlid);

        if (! $leave->status->isOpen()) {
            throw LeaveException::notOpen();
        }

        $abilities = $this->access->abilities($actor, $leave, $section);

        if (! match ($decision) {
            'approve' => $abilities->approve,
            'reject' => $abilities->reject,
            default => $abilities->recommend,
        }) {
            throw LeaveException::notAllowed();
        }

        $note = $note === null ? null : trim($note);

        if ($note !== null && mb_strlen($note) > LeaveRules::NOTE_MAX) {
            throw LeaveException::noteTooLong(LeaveRules::NOTE_MAX);
        }

        if ($decision !== 'approve' && ($note === null || mb_strlen($note) < LeaveRules::NOTE_MIN)) {
            throw LeaveException::noteNeeded($decision);
        }

        $staffKey = $this->access->staffKey($actor) ?? throw LeaveException::notAllowed();
        $note = $note === '' ? null : $note;

        $this->transaction(function () use ($leave, $decision, $note, $staffKey) {
            $before = ['status' => $leave->status->value];

            if ($decision === 'recommend') {
                $leave->update([
                    'status' => LeaveStatus::Recommended,
                    'recommended_by_staff_id' => $staffKey,
                    'recommended_at' => now(),
                    'recommendation_note' => $note,
                ]);
            } else {
                $leave->update([
                    'status' => $decision === 'approve' ? LeaveStatus::Approved : LeaveStatus::Rejected,
                    'decided_by_staff_id' => $staffKey,
                    'decided_at' => now(),
                    'decision_note' => $note,
                ]);
            }

            $key = match ($decision) {
                'approve' => 'approved',
                'reject' => 'rejected',
                default => 'recommended',
            };

            $this->audit(new AuditEntry("student_leave.{$key}", 'student_leave_request', $leave->ulid, $before, [
                'status' => $leave->status->value,
                'has_note' => $note !== null,
            ]));
        });

        $studentUlid = (string) DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')->where('id', $leave->student_id)->value('ulid');
        $this->notifier->notify(match ($decision) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => 'recommended',
        }, $leave->ulid, $studentUlid);

        return $leave;
    }
}
