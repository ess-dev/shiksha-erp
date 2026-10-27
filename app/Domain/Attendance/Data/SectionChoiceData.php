<?php

namespace App\Domain\Attendance\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One class in the picker. `relation` says what the person can do there:
 * `yours` (their own class: they mark it), `cover` (they mark it while the class teacher is on approved leave), `correct` (they may correct it) or
 * `view` (look only).
 */
#[TypeScript]
class SectionChoiceData extends Data
{
    public function __construct(
        public string $ulid,
        public string $label,
        public string $campus_name,
        public string $relation,
    ) {}
}
