<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HistoryRowData extends Data
{
    public function __construct(
        public string $label,
        public string $happened_on,
        public ?string $note,
    ) {}
}
