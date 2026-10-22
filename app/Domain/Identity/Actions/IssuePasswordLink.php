<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Tenancy\SchoolUrl;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Password;

/**
 * Creates a one-time link on a school's own host where an identity sets its
 * password. The link is returned to the caller and never stored or logged;
 * it lasts as long as the password-reset broker allows (config/auth.php).
 */
class IssuePasswordLink extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Identity $identity, Tenant $tenant): string
    {
        return $this->transaction(function () use ($identity, $tenant) {
            $token = Password::broker()->createToken($identity);

            $this->audit(new AuditEntry('identity.password_link_issued', 'identity', $identity->ulid), $tenant->id);

            return SchoolUrl::to($tenant, 'reset-password/'.$token).'?email='.rawurlencode((string) $identity->email);
        });
    }
}
