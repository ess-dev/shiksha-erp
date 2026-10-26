<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the parent's leave form needs to check dates and explain the rules:
 * the weekly working days (ISO 1 to 7) and the holidays from the earliest
 * allowed date to the end of the year, so the page can count school days.
 * The server checks everything again when the form is sent.
 */
#[TypeScript]
class LeaveFormData extends Data
{
    /**
     * @param  list<int>  $working_days
     * @param  list<string>  $holidays
     * @param  list<LeaveReasonOptionData>  $reasons
     */
    public function __construct(
        public string $today,
        public string $min_date,
        public int $max_days,
        public int $escalation_days,
        public int $reason_min,
        public int $reason_max,
        public int $max_file_mb,
        public ?string $class_teacher_name,
        public array $working_days,
        public array $holidays,
        public array $reasons,
    ) {}
}
