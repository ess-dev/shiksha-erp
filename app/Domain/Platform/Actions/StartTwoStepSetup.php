<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\Action;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

/**
 * Creates a fresh authenticator secret and recovery codes. Two-step is not on
 * until ConfirmTwoStepSetup sees a valid code. Starting again replaces an
 * unconfirmed secret; a confirmed one is never replaced here.
 */
class StartTwoStepSetup extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(private readonly EnableTwoFactorAuthentication $enable) {}

    public function handle(PlatformUser $user): void
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            return;
        }

        $this->transaction(function () use ($user) {
            ($this->enable)($user, force: true);

            $this->audit(new AuditEntry(
                action: 'platform.two_step_setup_started',
                subjectType: 'platform_user',
                subjectUlid: $user->ulid,
            ));
        });
    }
}
