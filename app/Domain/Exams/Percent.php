<?php

namespace App\Domain\Exams;

/**
 * Percentages of marks with integers only, rounded half-up. Both arguments
 * are in the same unit (usually half-marks), and `$outOf` is above zero.
 */
final class Percent
{
    /** Whole percent: 20 of 25 is 80. */
    public static function whole(int $scored, int $outOf): int
    {
        return intdiv(200 * $scored + $outOf, 2 * $outOf);
    }

    /** Tenths of a percent: 146 of 160 is 913 (91.3%). */
    public static function tenths(int $scored, int $outOf): int
    {
        return intdiv(2000 * $scored + $outOf, 2 * $outOf);
    }
}
