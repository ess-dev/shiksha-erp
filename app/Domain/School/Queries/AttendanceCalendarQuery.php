<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\AttendanceCalendarData;
use App\Domain\School\Data\HolidayData;
use App\Domain\School\Models\Holiday;

class AttendanceCalendarQuery
{
    public function __construct(private readonly SchoolSettingsQuery $settings) {}

    public function get(): AttendanceCalendarData
    {
        return new AttendanceCalendarData(
            working_days: $this->settings->attendanceWorkingDays(),
            correction_window_days: $this->settings->attendanceCorrectionWindowDays(),
            holidays: array_values(Holiday::query()->orderBy('holiday_on')->get()
                ->map(fn (Holiday $holiday) => new HolidayData($holiday->ulid, $holiday->holiday_on, $holiday->name))
                ->all()),
        );
    }
}
