<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Enums\LeaveStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One leave request as staff see it, with what the signed-in person may do.
 * Dates are calendar dates; `applied_on` is the calendar date it was sent.
 */
#[TypeScript]
class StaffLeaveItemData extends Data
{
    public function __construct(
        public string $ulid,
        public string $student_name,
        public string $section_label,
        public ?int $roll_number,
        public LeaveStatus $status,
        public bool $needs_coordinator,
        public string $reason_type,
        public string $reason_label,
        public string $reason,
        public string $from_on,
        public string $to_on,
        public int $school_days,
        public string $parent_name,
        public string $applied_on,
        public ?LeaveAttachmentData $attachment,
        public ?string $recommended_by,
        public ?string $recommendation_note,
        public ?string $decided_by,
        public ?string $decision_note,
        public bool $can_approve,
        public bool $can_reject,
        public bool $can_recommend,
    ) {}
}
