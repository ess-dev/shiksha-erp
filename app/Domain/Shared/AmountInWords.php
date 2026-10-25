<?php

namespace App\Domain\Shared;

/**
 * Writes an amount of rupees in words the Indian way (lakh, crore), for
 * receipts: "Rupees Ten Thousand Five Hundred only".
 */
final class AmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
        'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function rupees(int $paise): string
    {
        $rupees = intdiv(abs($paise), 100);
        $paiseLeft = abs($paise) % 100;

        $words = 'Rupees '.($rupees === 0 ? 'Zero' : self::number($rupees));

        if ($paiseLeft > 0) {
            $words .= ' and '.self::number($paiseLeft).' Paise';
        }

        return $words.' only';
    }

    private static function number(int $n): string
    {
        $parts = [];

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $size => $name) {
            if ($n >= $size) {
                $parts[] = self::number(intdiv($n, $size)).' '.$name;
                $n %= $size;
            }
        }

        if ($n >= 100) {
            $parts[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }

        if ($n >= 20) {
            $parts[] = trim(self::TENS[intdiv($n, 10)].' '.self::ONES[$n % 10]);
        } elseif ($n > 0) {
            $parts[] = self::ONES[$n];
        }

        return implode(' ', $parts);
    }
}
