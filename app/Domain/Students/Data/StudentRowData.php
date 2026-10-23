<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentRowData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $admission_number,
        public string $status,
        public ?string $class_label,
        public ?int $roll_number,
        public ?string $campus_name,
        public ?string $guardian_name,
        public ?string $guardian_relation,
        public ?string $guardian_phone,
        public bool $has_sibling,
        public bool $is_new,
    ) {}
}
