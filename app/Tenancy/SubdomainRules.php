<?php

namespace App\Tenancy;

/**
 * Slug and reserved-name rules for school subdomains (config/shiksha.php).
 */
class SubdomainRules
{
    public static function isValid(string $subdomain): bool
    {
        return strlen($subdomain) >= (int) config('shiksha.subdomain.min_length')
            && preg_match((string) config('shiksha.subdomain.pattern'), $subdomain) === 1
            && ! self::isReserved($subdomain);
    }

    public static function isReserved(string $subdomain): bool
    {
        return in_array($subdomain, (array) config('shiksha.subdomain.reserved'), true);
    }
}
