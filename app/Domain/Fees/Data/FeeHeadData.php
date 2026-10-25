<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One fee head of a group or of a billed installment: a name and paise.
 */
#[TypeScript]
class FeeHeadData extends Data
{
    public function __construct(
        public string $name,
        public int $amount_paise,
    ) {}
}
