<?php

namespace App\Domain\School\Data;

/**
 * Whether the school is open on a calendar date, and why not when it is closed.
 * Server-side only.
 */
final readonly class SchoolDayCheck
{
    public function __construct(
        public bool $isSchoolDay,
        public ?string $reason = null,
        public bool $isHoliday = false,
    ) {}
}
