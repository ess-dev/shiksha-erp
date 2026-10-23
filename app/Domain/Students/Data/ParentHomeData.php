<?php

namespace App\Domain\Students\Data;

use App\Domain\Attendance\Data\StudentDayData;
use App\Domain\Attendance\Data\StudentTallyData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The attendance part of the parent home for the selected child. The cards for
 * modules that are not built (fees, bus, homework, notices) are placeholders in
 * the page and carry no data.
 */
#[TypeScript]
class ParentHomeData extends Data
{
    /**
     * @param  list<StudentDayData>  $week  Monday to Friday of this week
     */
    public function __construct(
        public string $today,
        public StudentDayData $today_state,
        public array $week,
        public StudentTallyData $month,
        public string $month_label,
        public StudentTallyData $year,
        public string $session_name,
        public bool $is_current_session,
    ) {}
}
