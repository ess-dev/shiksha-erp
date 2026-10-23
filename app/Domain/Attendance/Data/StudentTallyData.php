<?php

namespace App\Domain\Attendance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Counts over a stretch of days. Late counts as present, as in the prototype;
 * `percent_tenths` is the attendance in tenths of a percent (955 = 95.5%, half-up), null while no school day has been recorded.
 */
#[TypeScript]
class StudentTallyData extends Data
{
    public function __construct(
        public int $present,
        public int $late,
        public int $absent,
        public int $school_days,
        public ?int $percent_tenths,
    ) {}
}
