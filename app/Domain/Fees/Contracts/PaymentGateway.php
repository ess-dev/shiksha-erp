<?php

namespace App\Domain\Fees\Contracts;

use App\Domain\Fees\Enums\GatewayReport;

/**
 * A payment provider. The Fees module only talks to this interface; each
 * school uses the driver the Super Admin chose for it (see
 * PaymentGatewayManager and `shiksha.payment_gateways`). Add a provider by
 * implementing this and listing it in the config.
 */
interface PaymentGateway
{
    /** The key the driver is listed under in `shiksha.payment_gateways`. */
    public function key(): string;

    public function label(): string;

    /**
     * A test driver that takes no real money. The accountant's screens then
     * offer a "what the gateway reports" choice so flows can be tried; a real
     * provider returns false and is simply asked.
     */
    public function isSimulated(): bool;

    /**
     * Asks the provider for the final status of a payment, by the reference it gave.
     * A simulated driver returns `$simulated` when it is given one.
     */
    public function status(string $reference, ?GatewayReport $simulated = null): GatewayReport;
}
