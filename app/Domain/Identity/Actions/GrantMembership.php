<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Support\Str;

class GrantMembership extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    /**
     * Grants (or re-activates) the identity's membership of a school.
     */
    public function handle(Identity $identity, Tenant $tenant, string $kind = Membership::KIND_STAFF): Membership
    {
        return $this->transaction(function () use ($identity, $tenant, $kind) {
            $membership = Membership::query()->firstOrNew([
                'identity_id' => $identity->id,
                'tenant_id' => $tenant->id,
                'kind' => $kind,
            ]);

            $before = $membership->exists ? ['status' => $membership->status] : null;

            $membership->ulid ??= strtolower((string) Str::ulid());
            $membership->status = 'active';
            $membership->revoked_at = null;
            $membership->save();

            $this->audit(new AuditEntry('membership.granted', 'membership', $membership->ulid, $before, [
                'kind' => $kind,
                'status' => 'active',
                'identity_ulid' => $identity->ulid,
            ]), $tenant->id);

            return $membership;
        });
    }
}
