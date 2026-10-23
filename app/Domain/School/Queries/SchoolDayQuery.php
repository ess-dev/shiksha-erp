<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\SchoolDayCheck;
use App\Domain\School\Models\Holiday;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;

/**
 * Answers "is the school open on this date?" from the weekly working days and
 * the holiday list. Dates are calendar dates, never converted through UTC.
 */
class SchoolDayQuery
{
    private const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    public function __construct(private readonly SchoolSettingsQuery $settings) {}

    public function check(string $date): SchoolDayCheck
    {
        $weekday = (int) (new DateTimeImmutable(CalendarDate::parse($date)))->format('N');

        $holiday = Holiday::query()->where('holiday_on', $date)->value('name');

        if (is_string($holiday)) {
            return new SchoolDayCheck(false, $holiday);
        }

        if (! in_array($weekday, $this->settings->attendanceWorkingDays(), true)) {
            return new SchoolDayCheck(false, self::WEEKDAYS[$weekday].' is a weekly day off');
        }

        return new SchoolDayCheck(true);
    }
}
