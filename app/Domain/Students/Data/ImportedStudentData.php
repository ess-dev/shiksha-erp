<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ImportedStudentData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $class_label,
        public string $admission_number,
    ) {}
}
