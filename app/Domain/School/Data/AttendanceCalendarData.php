<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the attendance settings screen shows: the weekly working days, the
 * correction window and the holiday list.
 */
#[TypeScript]
class AttendanceCalendarData extends Data
{
    /**
     * @param  list<int>  $working_days  ISO weekdays, 1 Monday to 7 Sunday
     * @param  list<HolidayData>  $holidays
     */
    public function __construct(
        public array $working_days,
        public int $correction_window_days,
        public array $holidays,
    ) {}
}
