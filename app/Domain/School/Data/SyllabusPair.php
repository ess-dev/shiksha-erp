<?php

namespace App\Domain\School\Data;

/**
 * One subject in one section of the current year that a person may keep up to
 * date. Server-side only (it carries internal keys).
 */
final readonly class SyllabusPair
{
    public function __construct(
        public SectionRefData $section,
        public int $subjectKey,
        public string $subjectUlid,
        public string $subjectName,
        public bool $isChosenTeacher,
        public bool $isClassTeacher,
        public bool $isManager,
    ) {}

    /** The person teaches this pair (chosen teacher, or class teacher). */
    public function isTeaching(): bool
    {
        return $this->isChosenTeacher || $this->isClassTeacher;
    }
}
