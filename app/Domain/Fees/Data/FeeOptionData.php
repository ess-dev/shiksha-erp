<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One choice in a filter or a select: a ulid (or a fixed key) and its label.
 */
#[TypeScript]
class FeeOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
