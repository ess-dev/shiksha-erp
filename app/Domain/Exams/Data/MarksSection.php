<?php

namespace App\Domain\Exams\Data;

use App\Domain\School\Data\SectionRefData;

/**
 * A section of the current year a person reaches on the marks screen, with
 * the subjects they see there. Server-side only (it carries internal keys).
 */
final readonly class MarksSection
{
    /**
     * @param  list<MarksSubject>  $subjects  In the order the class lists them
     */
    public function __construct(
        public SectionRefData $section,
        public int $classKey,
        public int $campusKey,
        public bool $isOverseer,
        public array $subjects,
    ) {}

    public function subject(string $ulid): ?MarksSubject
    {
        foreach ($this->subjects as $subject) {
            if ($subject->ulid === $ulid) {
                return $subject;
            }
        }

        return null;
    }

    public function hasOwnSubject(): bool
    {
        foreach ($this->subjects as $subject) {
            if ($subject->isMine) {
                return true;
            }
        }

        return false;
    }
}
