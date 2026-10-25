<?php

namespace App\Domain\Fees\Gateways;

use App\Domain\Fees\Contracts\PaymentGateway;
use App\Tenancy\Tenant;

/**
 * Finds the gateway driver chosen for the current school (landlord column
 * `tenants.payment_gateway`, set by the Super Admin). Null when none is chosen
 * or the chosen one is not available in this environment.
 */
class PaymentGatewayManager
{
    public function current(): ?PaymentGateway
    {
        $key = Tenant::current()?->payment_gateway;

        return is_string($key) && $key !== '' ? $this->driver($key) : null;
    }

    public function driver(string $key): ?PaymentGateway
    {
        $entry = config("shiksha.payment_gateways.{$key}");

        if (! is_array($entry) || ! $this->availableHere($entry)) {
            return null;
        }

        $driver = app($entry['class']);

        return $driver instanceof PaymentGateway ? $driver : null;
    }

    /**
     * The drivers a Super Admin may choose in this environment: key => label.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach ((array) config('shiksha.payment_gateways') as $key => $entry) {
            if (is_array($entry) && self::allowedHere($entry)) {
                $options[(string) $key] = (string) $entry['label'];
            }
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $entry
     */
    private function availableHere(array $entry): bool
    {
        return self::allowedHere($entry);
    }

    /**
     * @param  array<mixed>  $entry
     */
    private static function allowedHere(array $entry): bool
    {
        $environments = $entry['environments'] ?? null;

        return ! is_array($environments) || in_array(app()->environment(), $environments, true);
    }
}
