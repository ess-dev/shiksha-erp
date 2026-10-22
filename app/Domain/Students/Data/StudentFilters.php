<?php

namespace App\Domain\Students\Data;

/**
 * What the Students list is filtered by. `status` is "current" (enrolled),
 * "left" (left or transferred) or "all". `yearId` is a past academic session
 * (null means today's students): that session's enrolments are listed whatever
 * their status is now. Fees and attendance filters wait for those modules.
 */
final readonly class StudentFilters
{
    public function __construct(
        public string $search = '',
        public ?string $sectionUlid = null,
        public string $status = 'current',
        public ?string $campusUlid = null,
        public ?int $yearId = null,
    ) {}
}
