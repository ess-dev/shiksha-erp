<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One class on the "Subjects by class" tab.
 */
#[TypeScript]
class SubjectClassData extends Data
{
    /**
     * @param  list<string>  $subject_ulids  Subjects the class studies, in list order
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public int $level,
        public int $section_count,
        public array $subject_ulids,
        public bool $can_change,
    ) {}
}
