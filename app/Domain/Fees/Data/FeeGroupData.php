<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A fee group: its heads per installment and the classes that share it.
 */
#[TypeScript]
class FeeGroupData extends Data
{
    /**
     * @param  list<FeeHeadData>  $heads
     * @param  list<string>  $class_names
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public array $heads,
        public int $term_paise,
        public int $year_paise,
        public array $class_names,
        public int $students,
        public bool $can_delete,
    ) {}
}
