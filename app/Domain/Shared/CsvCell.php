<?php

namespace App\Domain\Shared;

/**
 * Stops spreadsheet programs from running a cell that starts like a formula.
 */
final class CsvCell
{
    public static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
