<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class LeaveSettingsData extends Data
{
    public function __construct(
        public int $escalation_days,
    ) {}
}
