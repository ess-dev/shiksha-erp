<?php

namespace App\Domain\Exams\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ExamDateSheetRowData extends Data
{
    public function __construct(
        public string $subject_ulid,
        public string $subject_name,
        public string $held_on,
        public ?string $begins_at,
    ) {}
}
