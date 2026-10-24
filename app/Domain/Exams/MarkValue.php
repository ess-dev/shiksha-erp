<?php

namespace App\Domain\Exams;

/**
 * What a teacher typed in a marks box: blank, "AB" (absent) or marks from 0
 * to the exam's maximum in steps of a half. Marks are kept as whole
 * half-marks (12.5 is 25) so no fractional numbers are needed.
 */
final readonly class MarkValue
{
    private function __construct(
        public bool $isBlank,
        public bool $isAbsent,
        public ?int $halfMarks,
    ) {}

    public static function blank(): self
    {
        return new self(true, false, null);
    }

    public static function absent(): self
    {
        return new self(false, true, null);
    }

    public static function halves(int $halfMarks): self
    {
        return new self(false, false, $halfMarks);
    }

    /**
     * The typed text, or null when it is not a valid entry for this maximum.
     */
    public static function parse(?string $text, int $maxMarks): ?self
    {
        $text = strtoupper(trim((string) $text));

        if ($text === '') {
            return self::blank();
        }

        if ($text === 'AB') {
            return self::absent();
        }

        if (preg_match('/^(\d{1,3})(?:\.(\d))?$/', $text, $match) !== 1) {
            return null;
        }

        $decimal = $match[2] ?? '0';

        if ($decimal !== '0' && $decimal !== '5') {
            return null;
        }

        $halfMarks = 2 * (int) $match[1] + ($decimal === '5' ? 1 : 0);

        return $halfMarks > 2 * $maxMarks ? null : self::halves($halfMarks);
    }

    /** "12.5", "20", "AB" or "" as the marks box shows it. */
    public function text(): string
    {
        if ($this->isAbsent) {
            return 'AB';
        }

        return $this->halfMarks === null ? '' : self::format($this->halfMarks);
    }

    /** Whole half-marks as marks: 25 is "12.5", 40 is "20". */
    public static function format(int $halfMarks): string
    {
        return intdiv($halfMarks, 2).($halfMarks % 2 === 1 ? '.5' : '');
    }

    public function equals(self $other): bool
    {
        return $this->isBlank === $other->isBlank && $this->isAbsent === $other->isAbsent && $this->halfMarks === $other->halfMarks;
    }
}
