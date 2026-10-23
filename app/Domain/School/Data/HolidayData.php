<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class HolidayData extends Data
{
    public function __construct(
        public string $ulid,
        public string $holiday_on,
        public string $name,
    ) {}
}
