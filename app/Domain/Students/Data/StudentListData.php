<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentListData extends Data
{
    /**
     * @param  list<StudentRowData>  $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
        public int $from,
        public int $to,
        public int $current_page,
        public int $last_page,
        public int $current_students_total,
    ) {}
}
