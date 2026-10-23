<?php

namespace App\Domain\Shared;

/**
 * A phone number in E.164 form. Ten-digit Indian mobile numbers (starting 6-9),
 * with or without a leading 0 or 91, become +91XXXXXXXXXX; other numbers must
 * already carry a "+" country code. illustrative — verify numbering rules.
 */
final readonly class PhoneNumber
{
    private function __construct(public string $e164) {}

    public static function tryParse(string $input): ?self
    {
        $compact = preg_replace('/[\s\-().]/', '', trim($input)) ?? '';

        if (str_starts_with($compact, '+')) {
            return preg_match('/^\+[1-9]\d{7,14}$/', $compact) === 1 ? new self($compact) : null;
        }

        if (preg_match('/^\d+$/', $compact) !== 1) {
            return null;
        }

        $national = preg_replace('/^(0|91)(?=\d{10}$)/', '', $compact) ?? '';

        return preg_match('/^[6-9]\d{9}$/', $national) === 1 ? new self('+91'.$national) : null;
    }

    /**
     * Masked for display and logs, e.g. "+91 ******3210".
     */
    public function masked(): string
    {
        return self::mask($this->e164);
    }

    public static function mask(string $e164): string
    {
        return substr($e164, 0, 3).' '.str_repeat('*', max(strlen($e164) - 7, 0)).substr($e164, -4);
    }
}
