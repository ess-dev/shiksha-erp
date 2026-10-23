<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EnrollmentHistoryData extends Data
{
    public function __construct(
        public string $academic_year,
        public string $class_label,
        public string $campus_name,
        public string $result,
        public bool $is_current,
        public ?string $note,
        public string $started_on,
        public ?string $ended_on,
    ) {}
}
