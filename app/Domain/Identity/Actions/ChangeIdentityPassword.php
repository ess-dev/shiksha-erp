<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class ChangeIdentityPassword extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Identity $identity, string $password): void
    {
        $this->transaction(function () use ($identity, $password) {
            $identity->forceFill(['password' => $password])->save();

            $this->audit(new AuditEntry('identity.password_changed', 'identity', $identity->ulid));
        });
    }
}
