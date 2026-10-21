<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Models\Membership;
use App\Domain\Shared\Action;

class RevokeMembership extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    /**
     * Ends the membership. The next request from that person to the school is
     * signed out (see EnsureActiveMembership); the record itself is kept.
     */
    public function handle(Membership $membership): Membership
    {
        return $this->transaction(function () use ($membership) {
            $before = ['status' => $membership->status];

            $membership->forceFill(['status' => 'revoked', 'revoked_at' => now()])->save();

            $this->audit(new AuditEntry('membership.revoked', 'membership', $membership->ulid, $before, [
                'kind' => $membership->kind,
                'status' => 'revoked',
            ]), $membership->tenant_id);

            return $membership;
        });
    }
}
