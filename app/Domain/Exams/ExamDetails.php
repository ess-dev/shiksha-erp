<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Enums\ExamType;

/**
 * What the Create/Update exam form sends, after request validation.
 */
final readonly class ExamDetails
{
    /**
     * @param  list<string>  $classUlids  Ignored when $allClasses
     * @param  list<string>  $campusUlids  Ignored when $allCampuses
     * @param  list<array{subject_ulid: string, held_on: string, begins_at: string|null}>  $dateSheet
     */
    public function __construct(
        public string $name,
        public ExamType $type,
        public int $maxMarks,
        public string $startsOn,
        public string $endsOn,
        public bool $allClasses,
        public array $classUlids,
        public bool $allCampuses,
        public array $campusUlids,
        public array $dateSheet,
        public ?string $instructions,
        public bool $showOnCalendar,
        public bool $notifyParents,
    ) {}
}
