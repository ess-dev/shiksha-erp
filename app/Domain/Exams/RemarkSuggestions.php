<?php

namespace App\Domain\Exams;

/**
 * The suggested class teacher's remark for an overall percentage, from
 * `config('shiksha.report_card_remarks')` (illustrative - verify).
 */
final class RemarkSuggestions
{
    /**
     * @param  int|null  $percentTenths  Overall percentage in tenths (913 = 91.3%)
     */
    public static function for(?int $percentTenths): ?string
    {
        if ($percentTenths === null) {
            return null;
        }

        foreach ((array) config('shiksha.report_card_remarks') as $band) {
            if (is_array($band) && $percentTenths >= 10 * (int) ($band['min_percent'] ?? 0)) {
                return (string) ($band['text'] ?? '');
            }
        }

        return null;
    }
}
