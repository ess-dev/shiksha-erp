<?php

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\Enums\DayState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One calendar day of one student's attendance, as a parent sees it.
 * `marked_at` is an instant (ISO string); show it in the school's time zone.
 */
#[TypeScript]
class StudentDayData extends Data
{
    public function __construct(
        public string $date,
        public DayState $state,
        public ?string $note = null,
        public ?string $marked_by = null,
        public ?string $marked_at = null,
        public ?string $reason = null,
        public ?string $leave_reason = null,
    ) {}
}
