<?php

namespace App\Domain\Fees;

use App\Domain\Fees\Enums\ConcessionType;
use App\Domain\Fees\Models\FeeConcession;
use App\Domain\Shared\Money;

/**
 * Prices one installment: the group's fee heads, less a concession on one
 * head. The one place that knows how a concession turns into paise.
 */
final class InvoicePricing
{
    /**
     * @param  list<array{name: string, amount_paise: int}>  $heads
     * @return array{gross_paise: int, concession_paise: int, net_paise: int, note: string|null}
     */
    public static function price(array $heads, ?FeeConcession $concession): array
    {
        $gross = Money::zero();

        foreach ($heads as $head) {
            $gross = $gross->plus(Money::paise($head['amount_paise']));
        }

        $discount = Money::zero();
        $note = null;

        if ($concession !== null) {
            foreach ($heads as $head) {
                if (mb_strtolower($head['name']) !== mb_strtolower($concession->applies_to_head)) {
                    continue;
                }

                $base = Money::paise($head['amount_paise']);
                $discount = $concession->value_type === ConcessionType::Percent
                    ? $base->percent($concession->value)
                    : Money::paise($concession->value)->atMost($base);
                $note = $discount->isZero() ? null : $concession->name.' ('.self::describe($concession).')';

                break;
            }
        }

        return [
            'gross_paise' => $gross->paise,
            'concession_paise' => $discount->paise,
            'net_paise' => $gross->minus($discount)->paise,
            'note' => $note,
        ];
    }

    /** "10% of tuition fee" or "₹2,000 off tuition fee per term". */
    public static function describe(FeeConcession $concession): string
    {
        $head = mb_strtolower($concession->applies_to_head);

        return $concession->value_type === ConcessionType::Percent
            ? "{$concession->value}% of {$head}"
            : Money::paise($concession->value)->format()." off {$head} per term";
    }
}
