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
            return new SchoolDayCheck(false, $holiday, true);
        }

        if (! in_array($weekday, $this->settings->attendanceWorkingDays(), true)) {
            return new SchoolDayCheck(false, self::WEEKDAYS[$weekday].' is a weekly day off');
        }

        return new SchoolDayCheck(true);
    }

    /**
     * The same answer for every date from one day to another (inclusive), with
     * one holiday lookup instead of one per day.
     *
     * @return array<string, SchoolDayCheck> keyed by calendar date
     */
    public function range(string $from, string $to): array
    {
        $holidays = Holiday::query()->whereBetween('holiday_on', [$from, $to])->pluck('name', 'holiday_on')->all();
        $working = $this->settings->attendanceWorkingDays();
        $checks = [];

        for ($day = new DateTimeImmutable(CalendarDate::parse($from)); $day->format('Y-m-d') <= $to; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $weekday = (int) $day->format('N');

            $checks[$date] = match (true) {
                isset($holidays[$date]) => new SchoolDayCheck(false, (string) $holidays[$date], true),
                ! in_array($weekday, $working, true) => new SchoolDayCheck(false, self::WEEKDAYS[$weekday].' is a weekly day off'),
                default => new SchoolDayCheck(true),
            };
        }

        return $checks;
    }
}
