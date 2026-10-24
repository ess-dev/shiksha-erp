<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One student on a marks sheet. `value` is what the marks box shows:
 * "" (not entered), "AB" (absent) or marks such as "12.5".
 */
#[TypeScript]
class MarkRowData extends Data
{
    public function __construct(
        public string $student_ulid,
        public ?int $roll_number,
        public string $name,
        public string $value,
    ) {}
}
