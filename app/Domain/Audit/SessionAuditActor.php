<?php

namespace App\Domain\Audit;

use App\Domain\Access\Queries\RoleNamesForIdentityQuery;
use App\Domain\Audit\Contracts\ResolvesAuditActor;
use App\Domain\Audit\Data\AuditActor;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Queries\StaffMembershipUlidQuery;
use App\Domain\Platform\Models\PlatformUser;
use App\Tenancy\Tenant;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Who is acting: the signed-in staff member (identity, membership and role
 * names in the current school), or the system when no one is signed in.
 */
class SessionAuditActor implements ResolvesAuditActor
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly StaffMembershipUlidQuery $memberships,
        private readonly RoleNamesForIdentityQuery $roles,
    ) {}

    public function resolve(): AuditActor
    {
        $platformUser = $this->auth->guard('platform')->user();

        if ($platformUser instanceof PlatformUser) {
            return new AuditActor(AuditActorType::Platform, $platformUser->ulid);
        }

        $identity = $this->auth->guard()->user();

        if (! $identity instanceof Identity) {
            return AuditActor::system();
        }

        $tenant = Tenant::current();
        $membershipUlid = $tenant instanceof Tenant ? $this->memberships->get($identity, $tenant) : null;

        if ($membershipUlid === null) {
            return new AuditActor(AuditActorType::User, $identity->ulid);
        }

        return new AuditActor(AuditActorType::Staff, $identity->ulid, $membershipUlid, $this->roles->get($identity));
    }
}
