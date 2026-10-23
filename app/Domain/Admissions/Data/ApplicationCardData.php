<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ApplicationCardData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $class_name,
        public string $campus_name,
        public string $source,
        public string $parent_name,
        public string $stage,
        public ?string $decision,
        public bool $is_closed,
        public bool $is_locked,
        public int $days,
        public int $missing_documents,
        public ?string $student_ulid,
    ) {}
}
