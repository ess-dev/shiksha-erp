<?php

namespace App\Domain\Exams\Data;

/**
 * An examined subject of a section on the marks screen and what the person
 * may do with it. Server-side only (it carries internal keys).
 */
final readonly class MarksSubject
{
    public function __construct(
        public int $subjectKey,
        public string $ulid,
        public string $name,
        public ?int $teacherStaffKey,
        public bool $isMine,
        public bool $mayEnter,
    ) {}
}
