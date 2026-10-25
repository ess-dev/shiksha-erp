<?php

namespace App\Domain\Fees\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A concession and how many students currently get it.
 */
#[TypeScript]
class ConcessionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $description,
        public bool $is_automatic_sibling,
        public bool $needs_approval,
        public int $students,
    ) {}
}
