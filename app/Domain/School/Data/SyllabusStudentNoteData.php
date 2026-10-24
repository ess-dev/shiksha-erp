<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A student of the section and the private note their parents see.
 */
#[TypeScript]
class SyllabusStudentNoteData extends Data
{
    public function __construct(
        public string $ulid,
        public ?int $roll_number,
        public string $name,
        public string $note,
    ) {}
}
