<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Enums\LeaveStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One leave request as the child's parent sees it. `applied_at` is an instant
 * (ISO string, show it in the school's time zone); `note` is the teacher's or
 * coordinator's note to the parent.
 */
#[TypeScript]
class ParentLeaveItemData extends Data
{
    public function __construct(
        public string $ulid,
        public string $from_on,
        public string $to_on,
        public int $school_days,
        public string $reason_label,
        public string $reason,
        public LeaveStatus $status,
        public bool $needs_coordinator,
        public string $applied_at,
        public ?string $recommended_by,
        public ?string $decided_by,
        public ?string $note,
        public ?string $attachment_name,
        public bool $can_cancel,
    ) {}
}
