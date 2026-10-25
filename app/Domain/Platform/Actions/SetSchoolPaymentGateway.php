<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Fees\Queries\PaymentGatewayOptionsQuery;
use App\Domain\Platform\Exceptions\SchoolChangeException;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;

/**
 * Chooses which payment gateway driver a school's online payments use, or
 * none. Only drivers listed in `shiksha.payment_gateways` and allowed in this
 * environment can be chosen. Online payments already recorded keep the
 * gateway they were made with.
 */
class SetSchoolPaymentGateway extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(private readonly PaymentGatewayOptionsQuery $options) {}

    public function handle(Tenant $tenant, ?string $gatewayKey): Tenant
    {
        $gatewayKey = $gatewayKey === '' ? null : $gatewayKey;

        if ($gatewayKey !== null && ! array_key_exists($gatewayKey, $this->options->get())) {
            throw SchoolChangeException::unknownGateway($gatewayKey);
        }

        return $this->transaction(function () use ($tenant, $gatewayKey) {
            $before = $tenant->payment_gateway;

            $tenant->forceFill(['payment_gateway' => $gatewayKey])->save();

            $this->audit(new AuditEntry(
                action: 'platform.school_payment_gateway_set',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['payment_gateway' => $before],
                after: ['payment_gateway' => $gatewayKey],
            ), $tenant->id);

            return $tenant;
        });
    }
}
