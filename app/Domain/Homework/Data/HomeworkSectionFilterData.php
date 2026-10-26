<?php

namespace App\Domain\Homework\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HomeworkSectionFilterData extends Data
{
    public function __construct(
        public string $ulid,
        public string $label,
    ) {}
}
