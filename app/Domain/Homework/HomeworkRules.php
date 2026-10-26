<?php

namespace App\Domain\Homework;

use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\Shared\CalendarDate;

/**
 * The field rules every homework write shares, so create and edit agree.
 */
class HomeworkRules
{
    /** illustrative — verify: field lengths follow the prototype. */
    public const TITLE_MIN = 3;

    public const TITLE_MAX = 80;

    public const INSTRUCTIONS_MAX = 400;

    public const FEEDBACK_MAX = 160;

    public function __construct(private readonly SchoolDayQuery $days) {}

    public function title(string $title): string
    {
        $title = trim($title);

        if (mb_strlen($title) < self::TITLE_MIN || mb_strlen($title) > self::TITLE_MAX) {
            throw HomeworkException::titleNeeded();
        }

        return $title;
    }

    public function instructions(?string $instructions): ?string
    {
        $instructions = $instructions === null ? '' : trim($instructions);

        if (mb_strlen($instructions) > self::INSTRUCTIONS_MAX) {
            throw HomeworkException::instructionsTooLong(self::INSTRUCTIONS_MAX);
        }

        return $instructions === '' ? null : $instructions;
    }

    /**
     * A due date must be after today and a school day (not a holiday or a
     * weekly day off), as the prototype asks.
     */
    public function dueOn(string $dueOn): string
    {
        $dueOn = CalendarDate::parse($dueOn);

        if ($dueOn <= CalendarDate::today()) {
            throw HomeworkException::dueDateNotAllowed('Pick a due date after today.');
        }

        $check = $this->days->check($dueOn);

        if (! $check->isSchoolDay) {
            $why = $check->isHoliday ? "That day is a holiday ({$check->reason})" : (string) $check->reason;

            throw HomeworkException::dueDateNotAllowed("{$why}. Pick a school day.");
        }

        return $dueOn;
    }
}
