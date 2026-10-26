<?php

namespace App\Domain\Homework\Data;

use App\Domain\School\Data\SectionRefData;

/**
 * One subject in one current-year section that a teacher may set homework for.
 * Server-side only (it carries internal keys).
 */
final readonly class HomeworkPair
{
    public function __construct(
        public SectionRefData $section,
        public int $subjectKey,
        public string $subjectUlid,
        public string $subjectName,
        public bool $isChosenTeacher,
    ) {}
}
