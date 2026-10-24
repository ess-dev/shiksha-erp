<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The examined subjects (not co-scholastic) one class studies.
 */
#[TypeScript]
class ExamClassSubjectsData extends Data
{
    /**
     * @param  list<ExamSubjectOptionData>  $subjects  In the order they were added
     */
    public function __construct(
        public string $class_ulid,
        public array $subjects,
    ) {}
}
