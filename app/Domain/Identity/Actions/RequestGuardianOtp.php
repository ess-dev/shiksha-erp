<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Authentication\OtpCodes;
use App\Domain\Identity\Contracts\OtpSender;
use App\Domain\Identity\Exceptions\OtpThrottled;
use App\Domain\Identity\Models\OtpChallenge;
use App\Domain\Shared\Action;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Queries\GuardianPortalQuery;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

class RequestGuardianOtp extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly OtpCodes $codes,
        private readonly OtpSender $sender,
        private readonly GuardianPortalQuery $portal,
    ) {}

    /**
     * Sends a sign-in code when the phone belongs to a guardian with portal
     * access at this school. Anything else does nothing, and the caller shows
     * the same "code sent" answer either way. The limits (per phone, per
     * address, per school) count every request, known phone or not.
     *
     * @throws OtpThrottled
     */
    public function handle(Tenant $tenant, string $phoneInput, string $ip): void
    {
        $this->assertWithinLimits($tenant, $phoneInput, $ip);

        $phone = PhoneNumber::tryParse($phoneInput);

        if ($phone === null || ! $this->portal->phoneHasPortalAccess($phone->e164)) {
            return;
        }

        $recent = OtpChallenge::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone_e164', $phone->e164)
            ->where('created_at', '>', now()->subSeconds((int) config('shiksha.otp.resend_seconds')))
            ->exists();

        if ($recent) {
            return;
        }

        $code = $this->codes->generate();

        $challenge = $this->transaction(function () use ($tenant, $phone, $code) {
            OtpChallenge::query()
                ->where('tenant_id', $tenant->id)
                ->where('phone_e164', $phone->e164)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $ulid = strtolower((string) Str::ulid());

            $challenge = OtpChallenge::query()->create([
                'ulid' => $ulid,
                'tenant_id' => $tenant->id,
                'phone_e164' => $phone->e164,
                'code_hash' => $this->codes->hash($code, $ulid),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('shiksha.otp.ttl_minutes')),
            ]);

            $this->audit(new AuditEntry('guardian.otp_requested', 'otp_challenge', $ulid, null, [
                'phone' => $phone->masked(),
            ]), $tenant->id);

            return $challenge;
        });

        try {
            $this->sender->send($phone->e164, $code, $tenant->name);
        } catch (Throwable $e) {
            $challenge->forceFill(['consumed_at' => now()])->save();

            throw $e;
        }
    }

    private function assertWithinLimits(Tenant $tenant, string $phoneInput, string $ip): void
    {
        $phoneKey = PhoneNumber::tryParse($phoneInput)->e164 ?? strtolower(trim($phoneInput));

        $limits = [
            ["otp:phone:{$tenant->id}:{$phoneKey}", (int) config('shiksha.otp.per_phone_per_hour')],
            ["otp:ip:{$ip}", (int) config('shiksha.otp.per_ip_per_hour')],
            ["otp:school:{$tenant->id}", (int) config('shiksha.otp.per_school_per_hour')],
        ];

        foreach ($limits as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw new OtpThrottled(RateLimiter::availableIn($key));
            }
        }

        foreach ($limits as [$key]) {
            RateLimiter::hit($key, 3600);
        }
    }
}
