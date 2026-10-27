<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\StaffLeaveType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One staff leave request, for the approver's screen and the applicant's own
 * list. `half_days` is 2 per school day. Dates are calendar dates;
 * `applied_on` is the calendar date it was sent.
 */
#[TypeScript]
class StaffLeaveRequestData extends Data
{
    public function __construct(
        public string $ulid,
        public string $staff_ulid,
        public string $staff_name,
        public string $staff_note,
        public StaffLeaveType $leave_type,
        public string $type_label,
        public string $from_on,
        public string $to_on,
        public int $half_days,
        public string $reason,
        public ?string $suggested_cover,
        public string $applied_on,
        public LeaveStatus $status,
        public ?LeaveAttachmentData $attachment,
        public ?string $decided_by,
        public ?string $decision_note,
        public ?string $cover_name,
        public ?string $section_label,
        public bool $can_decide,
        public bool $can_cancel,
    ) {}
}
