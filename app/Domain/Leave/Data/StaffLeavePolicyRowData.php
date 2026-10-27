<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/** One staff leave type with its yearly allowance in school days (null = as needed). */
#[TypeScript]
class StaffLeavePolicyRowData extends Data
{
    public function __construct(
        public string $type,
        public string $label,
        public ?int $days,
        public ?int $document_after_days,
    ) {}
}
