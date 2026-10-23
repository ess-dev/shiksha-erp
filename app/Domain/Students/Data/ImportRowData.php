<?php

namespace App\Domain\Students\Data;

use App\Domain\Students\Enums\ImportRowStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ImportRowData extends Data
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $line,
        public string $student_name,
        public ?string $details,
        public ?string $class_label,
        public string $guardian,
        public ImportRowStatus $status,
        public array $errors,
        public array $warnings,
    ) {}
}
