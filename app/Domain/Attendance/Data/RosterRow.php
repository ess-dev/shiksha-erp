<?php

namespace App\Domain\Attendance\Data;

/**
 * One student on a section's register for a day, as the Actions need them.
 * Server-side only: it carries the internal key that marks point at.
 */
final readonly class RosterRow
{
    public function __construct(
        public int $studentKey,
        public string $ulid,
        public string $name,
        public string $admissionNumber,
        public ?int $rollNumber,
    ) {}
}
