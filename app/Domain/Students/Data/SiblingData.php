<?php

namespace App\Domain\Students\Data;

use App\Domain\Students\Enums\SiblingVia;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SiblingData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $class_label,
        public ?string $campus_name,
        public SiblingVia $via,
    ) {}
}
