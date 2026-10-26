<?php

namespace App\Domain\Leave;

use App\Domain\Leave\Exceptions\LeaveException;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The numbers and date checks of a leave request, in one place so the form's
 * hints and the Action cannot disagree.
 */
class LeaveRules
{
    /** illustrative — verify: as in the prototype, a parent may tell the school about a day up to 3 days later. */
    public const BACKDATE_DAYS = 3;

    /** illustrative — verify: the prototype sends longer leave to the principal in person. */
    public const MAX_SPAN_DAYS = 30;

    public const REASON_MIN = 10;

    public const REASON_MAX = 300;

    public const NOTE_MIN = 5;

    public const NOTE_MAX = 200;

    public function __construct(
        private readonly SchoolDayQuery $days,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    public function earliestFrom(): string
    {
        return $this->shift(CalendarDate::today(), -self::BACKDATE_DAYS);
    }

    public function escalationDays(): int
    {
        return $this->settings->leaveEscalationDays();
    }

    /**
     * Checks the dates and returns the number of school days they cover.
     *
     * @param  string  $yearEndsOn  Last day of the academic year the request belongs to
     *
     * @throws LeaveException
     */
    public function schoolDays(string $from, string $to, string $yearEndsOn): int
    {
        try {
            $from = CalendarDate::parse($from);
            $to = CalendarDate::parse($to);
        } catch (InvalidArgumentException) {
            throw LeaveException::invalidDate();
        }

        $earliest = $this->earliestFrom();

        if ($from < $earliest) {
            throw LeaveException::tooEarly($earliest);
        }

        if ($to < $from) {
            throw LeaveException::endBeforeStart();
        }

        if ($to > $yearEndsOn) {
            throw LeaveException::outsideYear();
        }

        if ($this->shift($from, self::MAX_SPAN_DAYS) < $to) {
            throw LeaveException::tooLong(self::MAX_SPAN_DAYS);
        }

        $count = count(array_filter($this->days->range($from, $to), fn ($check) => $check->isSchoolDay));

        if ($count === 0) {
            throw LeaveException::noSchoolDays();
        }

        return $count;
    }

    private function shift(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify(($days >= 0 ? '+' : '').$days.' days')->format('Y-m-d');
    }
}
