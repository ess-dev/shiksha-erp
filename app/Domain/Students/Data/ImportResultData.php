<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ImportResultData extends Data
{
    /**
     * @param  list<ImportedStudentData>  $students
     */
    public function __construct(
        public int $created,
        public int $skipped,
        public int $families_linked,
        public array $students,
    ) {}
}
