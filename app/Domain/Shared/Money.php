<?php

namespace App\Domain\Shared;

use InvalidArgumentException;

/**
 * An amount of rupees held as whole paise. The only way Domain code does
 * money arithmetic: no floats, percentages rounded half-up to the paisa.
 */
final readonly class Money
{
    private function __construct(public int $paise) {}

    public static function paise(int $paise): self
    {
        return new self($paise);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Whole rupees as typed by a person ("5000"); paise are not accepted here.
     *
     * @throws InvalidArgumentException when the text is not a whole number of rupees
     */
    public static function fromRupees(int|string $rupees): self
    {
        if (is_string($rupees) && preg_match('/^\d{1,9}$/', trim($rupees)) !== 1) {
            throw new InvalidArgumentException('Rupees must be a whole number.');
        }

        return new self(((int) $rupees) * 100);
    }

    public function plus(self $other): self
    {
        return new self($this->paise + $other->paise);
    }

    public function minus(self $other): self
    {
        return new self($this->paise - $other->paise);
    }

    /**
     * A whole percentage of this amount, half-up to the paisa.
     */
    public function percent(int $percent): self
    {
        if ($percent < 0) {
            throw new InvalidArgumentException('A percentage cannot be negative.');
        }

        return new self(intdiv($this->paise * $percent + 50, 100));
    }

    /** The smaller of the two amounts. */
    public function atMost(self $limit): self
    {
        return $this->paise <= $limit->paise ? $this : $limit;
    }

    public function isZero(): bool
    {
        return $this->paise === 0;
    }

    public function isPositive(): bool
    {
        return $this->paise > 0;
    }

    public function isNegative(): bool
    {
        return $this->paise < 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->paise > $other->paise;
    }

    public function equals(self $other): bool
    {
        return $this->paise === $other->paise;
    }

    /**
     * Plain number of rupees for CSV files ("15200" or "15200.50"), no sign or grouping.
     */
    public static function plain(int $paise): string
    {
        $text = (string) intdiv(abs($paise), 100);
        $remainder = abs($paise) % 100;

        return ($paise < 0 ? '-' : '').$text.($remainder === 0 ? '' : sprintf('.%02d', $remainder));
    }

    /**
     * Indian digit grouping with the rupee sign ("₹1,25,350"), for text and CSV only.
     */
    public function format(): string
    {
        $rupees = intdiv(abs($this->paise), 100);
        $remainder = abs($this->paise) % 100;
        $digits = (string) $rupees;

        if (strlen($digits) > 3) {
            $head = substr($digits, 0, -3);
            $digits = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head).','.substr($digits, -3);
        }

        $text = '₹'.$digits.($remainder === 0 ? '' : sprintf('.%02d', $remainder));

        return $this->paise < 0 ? '-'.$text : $text;
    }
}
