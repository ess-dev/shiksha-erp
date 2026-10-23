<?php

namespace App\Domain\Identity\Authentication;

use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Domain\Students\Queries\GuardianPortalQuery;
use App\Tenancy\Tenant;
use Illuminate\Http\Request;

/**
 * Which hat a signed-in person wears in this school: staff or parent. A person
 * who is both has two memberships and may switch. Staff permissions never
 * apply in the parent context, and the parent context never reaches staff
 * screens. Both are re-checked on every request.
 */
class SchoolContext
{
    public const STAFF = 'staff';

    public const GUARDIAN = 'guardian';

    public const SESSION_KEY = 'school.context';

    public const REQUEST_ATTRIBUTE = 'school.context';

    public function __construct(
        private readonly StaffAuthenticator $staff,
        private readonly GuardianPortalQuery $portal,
    ) {}

    /**
     * The contexts this identity may use here right now.
     *
     * @return list<string>
     */
    public function available(Identity $identity, Tenant $tenant): array
    {
        if (! $identity->isActive()) {
            return [];
        }

        $contexts = [];

        if ($this->staff->hasActiveStaffMembership($identity, $tenant)) {
            $contexts[] = self::STAFF;
        }

        $guardianMembership = Membership::query()
            ->where('identity_id', $identity->id)
            ->where('tenant_id', $tenant->id)
            ->where('kind', Membership::KIND_GUARDIAN)
            ->where('status', 'active')
            ->exists();

        if ($guardianMembership && $this->portal->identityHasPortalAccess($identity->ulid)) {
            $contexts[] = self::GUARDIAN;
        }

        return $contexts;
    }

    /**
     * Whether this person also holds an active staff membership here, so the
     * parent screens can offer the way back to the staff area.
     */
    public function canActAsStaff(Identity $identity): bool
    {
        $tenant = Tenant::current();

        return $tenant instanceof Tenant && $identity->isActive() && $this->staff->hasActiveStaffMembership($identity, $tenant);
    }

    /**
     * The context for this request: the remembered choice when it is still
     * allowed, otherwise staff first (the sign-in page decides by setting the
     * session key). Null when the person has no membership left here.
     *
     * @param  list<string>  $available
     */
    public function choose(Request $request, array $available): ?string
    {
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($chosen) && in_array($chosen, $available, true)) {
            return $chosen;
        }

        return $available[0] ?? null;
    }

    public static function current(Request $request): ?string
    {
        $value = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        return is_string($value) ? $value : null;
    }
}
