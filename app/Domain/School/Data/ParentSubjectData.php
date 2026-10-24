<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One subject as a parent reads it: the book, who teaches it, how far the
 * section has got, and the teacher's notes. `percent_done` is a whole number
 * and is null when no chapter list is saved. Chapter states are `done`,
 * `current` or `upcoming`.
 */
#[TypeScript]
class ParentSubjectData extends Data
{
    /**
     * @param  list<ParentChapterData>  $chapters
     */
    public function __construct(
        public string $name,
        public ?string $book,
        public ?string $teacher_name,
        public ?int $periods_per_week,
        public ?int $percent_done,
        public ?string $current_chapter,
        public ?string $teacher_note,
        public ?string $student_note,
        public ?string $assessment,
        public array $chapters,
    ) {}
}
