<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ImportPreviewData extends Data
{
    /**
     * @param  list<string>  $missing_columns
     * @param  list<ImportRowData>  $rows
     */
    public function __construct(
        public string $file_name,
        public array $missing_columns,
        public array $rows,
        public int $ready,
        public int $with_warnings,
        public int $need_fixing,
    ) {}
}
