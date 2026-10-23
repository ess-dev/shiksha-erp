<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Authentication\PlatformTwoStepSession;
use App\Domain\Platform\Exceptions\InvalidTwoStepCode;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\Action;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Accepts an authenticator code or, instead, one single-use recovery code.
 */
class VerifyTwoStepChallenge extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly PlatformTwoStepSession $session,
    ) {}

    /**
     * @throws InvalidTwoStepCode
     */
    public function handle(PlatformUser $user, ?string $code, ?string $recoveryCode): void
    {
        $this->transaction(function () use ($user, $code, $recoveryCode) {
            $usedRecoveryCode = false;

            if (filled($recoveryCode)) {
                $matched = collect($user->recoveryCodes())
                    ->first(fn (string $stored) => hash_equals($stored, $recoveryCode));

                if ($matched === null) {
                    throw new InvalidTwoStepCode('recovery_code');
                }

                $user->replaceRecoveryCode($matched);
                $usedRecoveryCode = true;
            } elseif (! $this->codeIsValid($user, $code)) {
                throw new InvalidTwoStepCode('code');
            }

            $this->session->markPassed($user);

            $this->audit(new AuditEntry(
                action: 'platform.two_step_passed',
                subjectType: 'platform_user',
                subjectUlid: $user->ulid,
                after: ['method' => $usedRecoveryCode ? 'recovery_code' : 'authenticator'],
            ));
        });
    }

    private function codeIsValid(PlatformUser $user, ?string $code): bool
    {
        if (blank($code) || blank($user->two_factor_secret)) {
            return false;
        }

        return $this->provider->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $code);
    }
}
