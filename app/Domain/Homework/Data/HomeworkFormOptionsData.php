<?php

namespace App\Domain\Homework\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the New assignment dialog needs: where the teacher may set homework,
 * the earliest due date and a suggested school day.
 *
 * @param  string  $min_due_on  Tomorrow, as a calendar date
 * @param  string  $suggested_due_on  The second school day from today
 */
#[TypeScript]
class HomeworkFormOptionsData extends Data
{
    /**
     * @param  list<HomeworkSectionOptionData>  $sections
     */
    public function __construct(
        public array $sections,
        public string $min_due_on,
        public string $suggested_due_on,
        public int $max_files,
        public int $max_file_mb,
        public int $title_max,
        public int $instructions_max,
        public int $feedback_max,
    ) {}
}
