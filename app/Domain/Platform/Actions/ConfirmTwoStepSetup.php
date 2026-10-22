<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Authentication\PlatformTwoStepSession;
use App\Domain\Platform\Exceptions\InvalidTwoStepCode;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\Action;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

/**
 * Turns two-step on once the person proves their authenticator app works.
 */
class ConfirmTwoStepSetup extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly ConfirmTwoFactorAuthentication $confirm,
        private readonly PlatformTwoStepSession $session,
    ) {}

    /**
     * @throws InvalidTwoStepCode
     */
    public function handle(PlatformUser $user, string $code): void
    {
        $this->transaction(function () use ($user, $code) {
            try {
                ($this->confirm)($user, $code);
            } catch (ValidationException) {
                throw new InvalidTwoStepCode('code');
            }

            $this->session->markPassed($user);

            $this->audit(new AuditEntry(
                action: 'platform.two_step_enabled',
                subjectType: 'platform_user',
                subjectUlid: $user->ulid,
            ));
        });
    }
}
