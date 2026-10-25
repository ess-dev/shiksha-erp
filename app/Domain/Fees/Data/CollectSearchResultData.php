<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One student found by the Collect fee search.
 */
#[TypeScript]
class CollectSearchResultData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $admission_number,
        public string $section_label,
        public string $campus_name,
        public int $due_paise,
    ) {}
}
