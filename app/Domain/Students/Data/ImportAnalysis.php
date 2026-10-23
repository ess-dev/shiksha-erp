<?php

namespace App\Domain\Students\Data;

/**
 * The result of checking a CSV: columns that are missing and every row.
 */
final readonly class ImportAnalysis
{
    /**
     * @param  list<string>  $missingColumns
     * @param  list<ImportRow>  $rows
     */
    public function __construct(
        public array $missingColumns,
        public array $rows,
    ) {}

    /**
     * @return list<ImportRow>
     */
    public function readyRows(): array
    {
        return array_values(array_filter($this->rows, fn (ImportRow $row) => $row->isReady()));
    }
}
