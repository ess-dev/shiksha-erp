<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One section's progress in a subject. Chapter positions count from 0.
 */
#[TypeScript]
class SyllabusProgressData extends Data
{
    /**
     * @param  list<int>  $done_chapters
     */
    public function __construct(
        public array $done_chapters,
        public ?int $current_chapter,
        public ?int $periods_per_week,
        public string $note,
        public ?string $updated_on,
        public ?string $updated_by,
    ) {}
}
