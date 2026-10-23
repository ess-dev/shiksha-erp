<?php

namespace App\Domain\Admissions\Enums;

enum AdmissionSource: string
{
    case WalkIn = 'walk_in';
    case Website = 'website';
    case Referral = 'referral';
    case FacebookAd = 'facebook_ad';
    case Newspaper = 'newspaper';

    public function label(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Website => 'Website',
            self::Referral => 'Referral',
            self::FacebookAd => 'Facebook ad',
            self::Newspaper => 'Newspaper',
        };
    }
}
