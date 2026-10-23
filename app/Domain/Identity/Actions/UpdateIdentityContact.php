<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\PhoneNumber;
use InvalidArgumentException;

class UpdateIdentityContact extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    /**
     * The audit entry records which fields changed, never the values.
     */
    public function handle(Identity $identity, ?string $email, ?string $phone): Identity
    {
        $e164 = null;

        if ($phone !== null) {
            $e164 = PhoneNumber::tryParse($phone)->e164
                ?? throw new InvalidArgumentException('Invalid phone number.');
        }

        $email = $email === null ? null : strtolower(trim($email));

        if ($email === null && $e164 === null) {
            throw new InvalidArgumentException('An identity needs a phone number or an email.');
        }

        return $this->transaction(function () use ($identity, $email, $e164) {
            $changed = array_keys(array_filter([
                'email' => $identity->email !== $email,
                'phone' => $identity->phone_e164 !== $e164,
            ]));

            $identity->forceFill(['email' => $email, 'phone_e164' => $e164])->save();

            $this->audit(new AuditEntry('identity.contact_updated', 'identity', $identity->ulid, null, [
                'changed' => $changed,
            ]));

            return $identity;
        });
    }
}
