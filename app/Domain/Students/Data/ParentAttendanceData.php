<?php

namespace App\Domain\Students\Data;

use App\Domain\Attendance\Data\StudentDayData;
use App\Domain\Attendance\Data\StudentTallyData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The parent attendance screen for one child and one month.
 */
#[TypeScript]
class ParentAttendanceData extends Data
{
    /**
     * @param  string  $month  "YYYY-MM"
     * @param  list<StudentDayData>  $days  Every calendar day of the month
     * @param  list<StudentDayData>  $absences  The absent days of the month
     */
    public function __construct(
        public string $today,
        public string $month,
        public ?string $previous_month,
        public ?string $next_month,
        public array $days,
        public StudentTallyData $month_tally,
        public StudentTallyData $year_tally,
        public string $session_name,
        public bool $is_current_session,
        public int $minimum_percent,
        public int $correction_window_days,
        public array $absences,
    ) {}
}
