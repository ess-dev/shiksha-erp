<?php

namespace App\Domain\Attendance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One school day on the dashboard's attendance trend; `percent_tenths` is null
 * when no visible register was saved that day.
 */
#[TypeScript]
class AttendanceTrendPointData extends Data
{
    public function __construct(
        public string $date,
        public ?int $percent_tenths,
    ) {}
}
