<?php

namespace App\Domain\Homework\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A section the teacher may set homework for, with the subjects they teach in it.
 */
#[TypeScript]
class HomeworkSectionOptionData extends Data
{
    /**
     * @param  list<HomeworkSubjectOptionData>  $subjects
     */
    public function __construct(
        public string $ulid,
        public string $label,
        public array $subjects,
    ) {}
}
