<?php

namespace App\Domain\Students\Queries;

use App\Domain\Attendance\Queries\StudentAttendanceQuery;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Data\ParentAttendanceData;
use DateTimeImmutable;

class ParentAttendanceQuery
{
    public function __construct(
        private readonly StudentAttendanceQuery $attendance,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * One month of the child's attendance. A month outside the session (or in
     * the future) falls back to the latest month that can be shown.
     *
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     */
    public function get(GuardianChildRef $child, array $session, ?string $requestedMonth): ParentAttendanceData
    {
        $today = CalendarDate::today();
        $first = substr($session['starts_on'], 0, 7);
        $last = max(substr(min($today, $session['ends_on']), 0, 7), $first);

        $month = $requestedMonth !== null
            && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requestedMonth) === 1
            && $requestedMonth >= $first
            && $requestedMonth <= $last
                ? $requestedMonth
                : $last;

        $start = new DateTimeImmutable($month.'-01');
        $days = $this->attendance->days($child->studentKey, $start->format('Y-m-d'), $start->format('Y-m-t'), $today);

        $yearTo = max(min($today, $session['ends_on']), $session['starts_on']);

        return new ParentAttendanceData(
            $today,
            $month,
            $month > $first ? $start->modify('-1 month')->format('Y-m') : null,
            $month < $last ? $start->modify('+1 month')->format('Y-m') : null,
            $days,
            $this->attendance->tallyOf($days),
            $this->attendance->tally($child->studentKey, $session['starts_on'], $yearTo),
            $session['name'],
            $session['is_current'],
            (int) config('shiksha.attendance_minimum_percent'),
            $this->settings->attendanceCorrectionWindowDays(),
            $this->attendance->absencesOf($days),
        );
    }
}
