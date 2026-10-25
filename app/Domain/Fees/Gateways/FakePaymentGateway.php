<?php

namespace App\Domain\Fees\Gateways;

use App\Domain\Fees\Contracts\PaymentGateway;
use App\Domain\Fees\Enums\GatewayReport;

/**
 * Local and test gateway: reports whatever the caller says, or "pending". It
 * is only listed for the local and testing environments.
 */
class FakePaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Test gateway (no real money)';
    }

    public function isSimulated(): bool
    {
        return true;
    }

    public function status(string $reference, ?GatewayReport $simulated = null): GatewayReport
    {
        return $simulated ?? GatewayReport::Pending;
    }
}
