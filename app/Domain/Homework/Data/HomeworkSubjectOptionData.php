<?php

namespace App\Domain\Homework\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HomeworkSubjectOptionData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
    ) {}
}
