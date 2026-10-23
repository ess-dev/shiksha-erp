<?php

namespace App\Domain\Attendance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SavedRegisterData extends Data
{
    public function __construct(
        public ?string $saved_by,
        public string $saved_at,
        public ?string $reason,
    ) {}
}
