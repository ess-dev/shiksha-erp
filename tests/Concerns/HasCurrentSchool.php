<?php

namespace Tests\Concerns;

use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * Makes an active school current, as the host finder does for a real request.
 * Requires the landlord database to be migrated first (RefreshDatabase).
 */
trait HasCurrentSchool
{
    protected Tenant $school;

    protected function setUpHasCurrentSchool(): void
    {
        $this->school = Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => 'Test Public School',
            'subdomain' => 'testschool',
            'database_name' => 'shiksha_t_unused',
            'status' => 'active',
        ]);

        $this->school->makeCurrent();
    }

    /**
     * A staff identity (password "password") with an active membership of the current school.
     */
    protected function staff(bool $twoFactor = false): Identity
    {
        $factory = Identity::factory();

        $identity = ($twoFactor ? $factory->withTwoFactor() : $factory)->create();

        $this->grantStaffMembership($identity);

        return $identity;
    }

    protected function grantStaffMembership(Identity $identity, ?Tenant $school = null): Membership
    {
        return Membership::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'identity_id' => $identity->id,
            'tenant_id' => ($school ?? $this->school)->id,
            'kind' => Membership::KIND_STAFF,
        ]);
    }
}
