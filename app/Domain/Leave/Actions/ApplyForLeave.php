<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Contracts\LeaveNotifier;
use App\Domain\Leave\Enums\LeaveReason;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\LeaveAttachmentStore;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StudentLeaveRequest;
use App\Domain\Shared\Action;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Queries\GuardianChildrenQuery;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class ApplyForLeave extends Action
{
    public function __construct(
        private readonly LeaveRules $rules,
        private readonly LeaveAttachmentStore $files,
        private readonly GuardianChildrenQuery $children,
        private readonly LeaveNotifier $notifier,
    ) {}

    /**
     * A guardian asks for leave for one of their children in the current
     * session. The child comes from the parent context, so it is one of the
     * guardian's own. Dates may start up to 3 days back, must cover at least
     * one school day, may span at most 30 days, stay inside the academic year
     * and never overlap another open or approved request of the same child.
     * Leave longer than the school's limit is marked as needing the coordinator
     * too. The limit is read now and kept on the request.
     *
     * @param  array{key: int, ends_on: string, is_current: bool}  $session
     *
     * @throws LeaveException
     */
    public function handle(
        Identity $actor,
        GuardianChildRef $child,
        array $session,
        string $from,
        string $to,
        string $type,
        string $reason,
        ?UploadedFile $attachment,
    ): StudentLeaveRequest {
        $guardian = $this->children->guardian($actor) ?? throw LeaveException::notAllowed();

        if (! $session['is_current']) {
            throw LeaveException::archived();
        }

        $reasonType = LeaveReason::tryFrom($type) ?? throw LeaveException::reasonTooShort(LeaveRules::REASON_MIN);
        $days = $this->rules->schoolDays($from, $to, $session['ends_on']);
        $reason = trim($reason);

        if (mb_strlen($reason) < LeaveRules::REASON_MIN) {
            throw LeaveException::reasonTooShort(LeaveRules::REASON_MIN);
        }

        if (mb_strlen($reason) > LeaveRules::REASON_MAX) {
            throw LeaveException::reasonTooLong(LeaveRules::REASON_MAX);
        }

        $clash = StudentLeaveRequest::query()
            ->where('student_id', $child->studentKey)
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Recommended->value, LeaveStatus::Approved->value])
            ->where('from_on', '<=', $to)
            ->where('to_on', '>=', $from)
            ->first();

        if ($clash !== null) {
            throw LeaveException::overlaps($this->short($clash->from_on), $this->short($clash->to_on));
        }

        $ulid = strtolower((string) Str::ulid());
        $stored = $attachment === null ? null : $this->files->store($attachment, $this->files->check($attachment), $ulid);

        try {
            $leave = $this->transaction(function () use ($ulid, $session, $child, $guardian, $from, $to, $days, $reasonType, $reason, $stored) {
                $leave = StudentLeaveRequest::query()->create([
                    'ulid' => $ulid,
                    'academic_year_id' => $session['key'],
                    'student_id' => $child->studentKey,
                    'section_id' => $child->sectionKey,
                    'applied_by_guardian_id' => $guardian->id,
                    'from_on' => $from,
                    'to_on' => $to,
                    'school_days' => $days,
                    'reason_type' => $reasonType,
                    'reason' => $reason,
                    'attachment_path' => $stored['path'] ?? null,
                    'attachment_name' => $stored['name'] ?? null,
                    'attachment_mime' => $stored['mime'] ?? null,
                    'attachment_size' => $stored['size'] ?? null,
                    'status' => LeaveStatus::Pending,
                    'needs_coordinator' => $days > $this->rules->escalationDays(),
                    'applied_at' => now(),
                ]);

                $this->audit(new AuditEntry('student_leave.applied', 'student_leave_request', $leave->ulid, null, [
                    'student' => $child->ulid,
                    'from' => $from,
                    'to' => $to,
                    'school_days' => $days,
                    'reason_type' => $reasonType->value,
                    'needs_coordinator' => $leave->needs_coordinator,
                    'has_attachment' => $stored !== null,
                ]));

                return $leave;
            });
        } catch (Throwable $e) {
            if ($stored !== null) {
                $this->files->delete($stored['path']);
            }

            throw $e;
        }

        $this->notifier->notify('applied', $leave->ulid, $child->ulid);

        return $leave;
    }

    private function short(string $date): string
    {
        return (new DateTimeImmutable($date))->format('j M');
    }
}
