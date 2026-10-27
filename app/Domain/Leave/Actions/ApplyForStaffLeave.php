<?php

namespace App\Domain\Leave\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Leave\Contracts\StaffLeaveNotifier;
use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\StaffLeaveType;
use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\Leave\LeaveAttachmentStore;
use App\Domain\Leave\LeaveRules;
use App\Domain\Leave\Models\StaffLeaveRequest;
use App\Domain\Leave\StaffLeaveAccess;
use App\Domain\Leave\StaffLeaveBalances;
use App\Domain\Leave\StaffLeavePolicy;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use DateTimeImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

class ApplyForStaffLeave extends Action
{
    /** illustrative — verify: the prototype asks for a few words of reason. */
    public const REASON_MIN = 5;

    public const REASON_MAX = 300;

    public function __construct(
        private readonly LeaveRules $rules,
        private readonly LeaveAttachmentStore $files,
        private readonly StaffLeaveAccess $access,
        private readonly StaffLeaveBalances $balances,
        private readonly StaffLeavePolicy $policy,
        private readonly SectionDirectoryQuery $directory,
        private readonly StaffRefQuery $staff,
        private readonly StaffLeaveNotifier $notifier,
    ) {}

    /**
     * A member of staff asks for leave for themselves in the current session.
     * Full days or one half day; the start may be up to 3 days back, the dates
     * must cover a school day, span at most 30 days, stay inside the academic
     * year and never overlap the person's own pending or approved leave. The
     * type's yearly allowance (a school setting, pending leave counts) must
     * cover it, and a certificate is needed beyond the school's limit for the
     * type. A class teacher's request remembers the class, so the approver
     * names who marks its register.
     *
     * @throws LeaveException
     */
    public function handle(
        Identity $actor,
        string $type,
        bool $halfDay,
        string $from,
        ?string $to,
        string $reason,
        ?string $suggestedCover,
        ?UploadedFile $attachment,
    ): StaffLeaveRequest {
        $staffKey = $this->access->staffKey($actor) ?? throw LeaveException::notAllowed();
        $year = $this->directory->currentYearRef() ?? throw LeaveException::archived();
        $leaveType = StaffLeaveType::tryFrom($type) ?? throw LeaveException::unknownType();

        $to = $halfDay ? $from : ($to ?? $from);
        $days = $this->rules->schoolDays($from, $to, $year['ends_on']);
        $halfDays = $halfDay ? 1 : $days * 2;

        $reason = trim($reason);

        if (mb_strlen($reason) < self::REASON_MIN) {
            throw LeaveException::staffReasonTooShort(self::REASON_MIN);
        }

        if (mb_strlen($reason) > self::REASON_MAX) {
            throw LeaveException::reasonTooLong(self::REASON_MAX);
        }

        $suggestedCover = $suggestedCover === null ? null : mb_substr(trim($suggestedCover), 0, 80);
        $suggestedCover = $suggestedCover === '' ? null : $suggestedCover;

        $balance = collect($this->balances->forStaff($staffKey, $year['key']))->firstWhere('type', $leaveType);

        if ($balance !== null && $balance->allowed_half_days !== null) {
            $left = $balance->allowed_half_days - $balance->used_half_days - $balance->pending_half_days;

            if ($halfDays > $left) {
                throw LeaveException::notEnoughLeave(mb_strtolower($leaveType->label()), $this->days(max($left, 0)));
            }
        }

        $documentAfter = $this->policy->all()[$leaveType->value]['document_after_days'];

        if ($documentAfter !== null && $halfDays > $documentAfter * 2 && $attachment === null) {
            throw LeaveException::certificateNeeded(mb_strtolower($leaveType->label()), $documentAfter);
        }

        $clash = StaffLeaveRequest::query()
            ->where('staff_id', $staffKey)
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->where('from_on', '<=', $to)
            ->where('to_on', '>=', $from)
            ->first();

        if ($clash !== null) {
            throw LeaveException::overlaps($this->short($clash->from_on), $this->short($clash->to_on));
        }

        $section = $this->access->classOf($staffKey);
        $ulid = strtolower((string) Str::ulid());
        $stored = $attachment === null ? null : $this->files->store($attachment, $this->files->check($attachment), $ulid);

        try {
            $leave = $this->transaction(function () use ($ulid, $year, $staffKey, $section, $leaveType, $from, $to, $halfDays, $reason, $suggestedCover, $stored) {
                $leave = StaffLeaveRequest::query()->create([
                    'ulid' => $ulid,
                    'academic_year_id' => $year['key'],
                    'staff_id' => $staffKey,
                    'section_id' => $section?->sectionKey,
                    'leave_type' => $leaveType,
                    'from_on' => $from,
                    'to_on' => $to,
                    'half_days' => $halfDays,
                    'reason' => $reason,
                    'suggested_cover' => $suggestedCover,
                    'attachment_path' => $stored['path'] ?? null,
                    'attachment_name' => $stored['name'] ?? null,
                    'attachment_mime' => $stored['mime'] ?? null,
                    'attachment_size' => $stored['size'] ?? null,
                    'status' => LeaveStatus::Pending,
                    'applied_at' => now(),
                ]);

                $this->audit(new AuditEntry('staff_leave.applied', 'staff_leave_request', $leave->ulid, null, [
                    'type' => $leaveType->value,
                    'from' => $from,
                    'to' => $to,
                    'half_days' => $halfDays,
                    'section' => $section?->ulid,
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

        $this->notifier->notify('applied', $leave->ulid, (string) $this->staff->byKey($staffKey)?->ulid);

        return $leave;
    }

    private function short(string $date): string
    {
        return (new DateTimeImmutable($date))->format('j M');
    }

    private function days(int $halfDays): string
    {
        return $halfDays % 2 === 0 ? (string) intdiv($halfDays, 2) : intdiv($halfDays, 2).'.5';
    }
}
