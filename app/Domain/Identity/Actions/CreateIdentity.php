<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\PhoneNumber;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateIdentity extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(?string $email, ?string $phone, ?string $password): Identity
    {
        $e164 = null;

        if ($phone !== null) {
            $e164 = PhoneNumber::tryParse($phone)->e164
                ?? throw new InvalidArgumentException('Invalid phone number.');
        }

        if ($email === null && $e164 === null) {
            throw new InvalidArgumentException('An identity needs a phone number or an email.');
        }

        return $this->transaction(function () use ($email, $e164, $password) {
            $identity = Identity::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'email' => $email,
                'phone_e164' => $e164,
                'password' => $password,
            ]);

            $this->audit(new AuditEntry('identity.created', 'identity', $identity->ulid, null, [
                'has_email' => $email !== null,
                'has_phone' => $e164 !== null,
                'has_password' => $password !== null,
            ]));

            return $identity;
        });
    }
}
