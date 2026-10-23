<?php

namespace App\Domain\Students\Queries;

use App\Domain\Attendance\Queries\StudentAttendanceQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Data\ParentHomeData;
use DateTimeImmutable;

class ParentHomeQuery
{
    public function __construct(private readonly StudentAttendanceQuery $attendance) {}

    /**
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     */
    public function get(GuardianChildRef $child, array $session): ParentHomeData
    {
        $today = CalendarDate::today();
        $now = new DateTimeImmutable($today);

        $monday = $now->modify('-'.(((int) $now->format('N')) - 1).' days');
        $week = $this->attendance->days($child->studentKey, $monday->format('Y-m-d'), $monday->modify('+4 days')->format('Y-m-d'), $today);
        $todayState = $this->attendance->days($child->studentKey, $today, $today, $today)[0];

        $yearTo = max(min($today, $session['ends_on']), $session['starts_on']);

        return new ParentHomeData(
            $today,
            $todayState,
            $week,
            $this->attendance->tally($child->studentKey, $now->format('Y-m-01'), $now->format('Y-m-t')),
            $now->format('F'),
            $this->attendance->tally($child->studentKey, $session['starts_on'], $yearTo),
            $session['name'],
            $session['is_current'],
        );
    }
}
