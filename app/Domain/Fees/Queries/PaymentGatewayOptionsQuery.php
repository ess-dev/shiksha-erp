<?php

namespace App\Domain\Fees\Queries;

use App\Domain\Fees\Gateways\PaymentGatewayManager;

/**
 * The payment gateways a Super Admin may choose for a school in this
 * environment (the platform side reads this instead of the gateway classes).
 */
class PaymentGatewayOptionsQuery
{
    /**
     * @return array<string, string> driver key => label
     */
    public function get(): array
    {
        return PaymentGatewayManager::options();
    }
}
