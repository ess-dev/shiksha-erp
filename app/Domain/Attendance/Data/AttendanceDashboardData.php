<?php

namespace App\Domain\Attendance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Attendance on the staff dashboard, limited to the sections the person may
 * see. Percentages are integers in tenths (955 = 95.5%, half-up) and null
 * while nothing is marked. `closed_reason` is set when today is not a school
 * day (a holiday or a weekly day off).
 */
#[TypeScript]
class AttendanceDashboardData extends Data
{
    /**
     * @param  list<AttendanceTrendPointData>  $trend
     * @param  list<string>  $unmarked_sections
     * @param  list<string>  $frequent_absentees  First few names
     * @param  list<AbsentStudentData>  $absentees
     */
    public function __construct(
        public string $today,
        public ?string $closed_reason,
        public ?int $percent_tenths,
        public int $absent,
        public int $sections_marked,
        public int $sections_total,
        public array $trend,
        public array $unmarked_sections,
        public int $frequent_absent_count,
        public array $frequent_absentees,
        public int $frequent_absence_threshold,
        public int $frequent_absence_days,
        public array $absentees,
        public int $absentees_total,
    ) {}
}
