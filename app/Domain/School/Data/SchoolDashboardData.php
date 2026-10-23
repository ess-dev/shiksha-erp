<?php

namespace App\Domain\School\Data;

use App\Domain\Admissions\Data\AdmissionFunnelData;
use App\Domain\Attendance\Data\AttendanceDashboardData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The staff dashboard. `attendance` and `admissions` are null when the module
 * is not in the school's plan or the person may not see it; everything else on
 * the page (fees, approvals, events other than holidays) is not built yet and
 * is drawn by the page as sample values. `part_of_day` is `morning`,
 * `afternoon` or `evening` in the school's time zone.
 */
#[TypeScript]
class SchoolDashboardData extends Data
{
    /**
     * @param  list<UpcomingHolidayData>  $holidays
     */
    public function __construct(
        public ?string $staff_name,
        public string $part_of_day,
        public string $today,
        public ?string $campus_name,
        public ?string $academic_year_name,
        public string $timezone,
        public ?AttendanceDashboardData $attendance,
        public ?AdmissionFunnelData $admissions,
        public array $holidays,
    ) {}
}
