<?php

namespace App\Domain\Identity\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Tenancy\Tenant;

class StaffMembershipUlidQuery
{
    /**
     * The ulid of the identity's active staff membership of the school, if any.
     */
    public function get(Identity $identity, Tenant $tenant): ?string
    {
        $ulid = Membership::query()
            ->where('identity_id', $identity->id)
            ->where('tenant_id', $tenant->id)
            ->where('kind', Membership::KIND_STAFF)
            ->where('status', 'active')
            ->value('ulid');

        return is_string($ulid) ? $ulid : null;
    }
}
