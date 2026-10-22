<?php

namespace App\Domain\Students;

/**
 * Reads an uploaded CSV into rows of trimmed text. Handles a byte-order mark
 * (Excel's "CSV UTF-8") and skips blank lines.
 */
final class CsvTable
{
    /**
     * @return list<list<string>>
     */
    public static function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $cells = array_map(fn (?string $cell): string => trim((string) $cell), $cells);

            if ($rows === []) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', $cells[0]) ?? $cells[0];
            }

            if (implode('', $cells) === '') {
                continue;
            }

            $rows[] = $cells;
        }

        fclose($handle);

        return $rows;
    }
}
