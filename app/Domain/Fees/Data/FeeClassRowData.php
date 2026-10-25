<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A class and the fee group it uses this academic year.
 */
#[TypeScript]
class FeeClassRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $fee_group_ulid,
        public ?int $term_paise,
        public int $students,
    ) {}
}
