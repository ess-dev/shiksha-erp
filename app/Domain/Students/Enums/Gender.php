<?php

namespace App\Domain\Students\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case Other = 'other';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Reads a CSV cell ("M", "female", "Other"); null when it is none of them.
     */
    public static function fromInput(string $input): ?self
    {
        return match (strtolower(trim($input))) {
            'm', 'male' => self::Male,
            'f', 'female' => self::Female,
            'o', 'other' => self::Other,
            default => null,
        };
    }
}
