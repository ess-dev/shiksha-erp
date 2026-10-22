<?php

namespace App\Domain\Shared;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Calendar dates ("Y-m-d") are strings end to end: no time zone, no UTC
 * conversion, so a date never shifts by a day.
 */
final class CalendarDate
{
    /**
     * @throws InvalidArgumentException when the text is not a real date
     */
    public static function parse(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("[{$value}] is not a valid date (expected YYYY-MM-DD).");
        }

        return $value;
    }
}
