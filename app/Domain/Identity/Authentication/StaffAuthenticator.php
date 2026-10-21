<?php

namespace App\Domain\Identity\Authentication;

use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Domain\Shared\PhoneNumber;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\Hash;

/**
 * Staff sign-in on a school's own host: phone or email plus password, and an
 * active staff membership of this school. Every way of failing returns null,
 * so a wrong password, an unknown account and a missing membership look the same.
 */
class StaffAuthenticator
{
    private static ?string $dummyHash = null;

    public function attempt(string $login, string $password): ?Identity
    {
        $tenant = Tenant::current();
        $identity = $this->find($login);

        // Always spend one hash check so response time does not reveal whether
        // the account exists.
        $passwordMatches = Hash::check($password, $identity->password ?? $this->dummyHash());

        if (! $tenant instanceof Tenant || $identity === null || ! $passwordMatches) {
            return null;
        }

        if (! $identity->isActive() || ! $this->hasActiveStaffMembership($identity, $tenant)) {
            return null;
        }

        return $identity;
    }

    public function hasActiveStaffMembership(Identity $identity, Tenant $tenant): bool
    {
        return Membership::query()
            ->where('identity_id', $identity->id)
            ->where('tenant_id', $tenant->id)
            ->where('kind', Membership::KIND_STAFF)
            ->where('status', 'active')
            ->exists();
    }

    private function find(string $login): ?Identity
    {
        $login = trim($login);

        $query = Identity::query();

        if (str_contains($login, '@')) {
            return $query->whereRaw('lower(email) = ?', [strtolower($login)])->first();
        }

        $phone = PhoneNumber::tryParse($login);

        return $phone === null ? null : $query->where('phone_e164', $phone->e164)->first();
    }

    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('not-a-real-password');
    }
}
