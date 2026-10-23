<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Authentication\OtpCodes;
use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\OtpChallenge;
use App\Domain\Shared\Action;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Actions\LinkGuardianIdentity;
use App\Domain\Students\Queries\GuardianPortalQuery;
use App\Tenancy\Tenant;

class VerifyGuardianOtp extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly OtpCodes $codes,
        private readonly GuardianPortalQuery $portal,
        private readonly CreateIdentity $createIdentity,
        private readonly GrantMembership $grantMembership,
        private readonly LinkGuardianIdentity $linkIdentity,
    ) {}

    /**
     * Checks the code. On success the code is used up, the guardian's identity
     * is created on first sign-in, the school membership is granted and the
     * guardian record is linked to the identity. Every failure (no code, wrong
     * code, expired, too many tries, no portal access, disabled identity)
     * returns null, so the caller shows one generic answer.
     */
    public function handle(Tenant $tenant, string $phoneInput, string $code): ?Identity
    {
        $phone = PhoneNumber::tryParse($phoneInput);

        if ($phone === null) {
            return null;
        }

        $challenge = OtpChallenge::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone_e164', $phone->e164)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', (int) config('shiksha.otp.max_attempts'))
            ->latest('id')
            ->first();

        if ($challenge === null) {
            return null;
        }

        if (! $this->codes->matches(trim($code), $challenge->ulid, $challenge->code_hash)) {
            $this->recordFailure($tenant, $challenge, $phone);

            return null;
        }

        if (! $this->portal->phoneHasPortalAccess($phone->e164)) {
            return null;
        }

        $existing = Identity::query()->where('phone_e164', $phone->e164)->first();

        if ($existing instanceof Identity && ! $existing->isActive()) {
            return null;
        }

        $identity = $this->transaction(function () use ($tenant, $challenge, $phone, $existing) {
            $challenge->forceFill(['consumed_at' => now()])->save();

            $identity = $existing ?? $this->createIdentity->handle(null, $phone->e164, null);

            $this->grantMembership->handle($identity, $tenant, Membership::KIND_GUARDIAN);

            $this->audit(new AuditEntry('guardian.otp_verified', 'identity', $identity->ulid, null, [
                'phone' => $phone->masked(),
            ]), $tenant->id);

            return $identity;
        });

        $this->linkIdentity->handle($phone->e164, $identity->ulid);

        // A new identity has only the columns it was created with; reload the database defaults (status).
        return $identity->refresh();
    }

    private function recordFailure(Tenant $tenant, OtpChallenge $challenge, PhoneNumber $phone): void
    {
        $this->transaction(function () use ($tenant, $challenge, $phone) {
            $challenge->increment('attempts');

            $this->audit(new AuditEntry('guardian.otp_failed', 'otp_challenge', $challenge->ulid, null, [
                'phone' => $phone->masked(),
                'attempts' => $challenge->attempts,
            ]), $tenant->id);
        });
    }
}
