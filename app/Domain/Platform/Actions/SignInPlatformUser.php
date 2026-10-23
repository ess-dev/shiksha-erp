<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Authentication\PlatformAuthenticator;
use App\Domain\Platform\Authentication\PlatformTwoStepSession;
use App\Domain\Platform\Exceptions\PlatformSignInFailed;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\Action;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;

/**
 * Signs a Super Admin in with email and password. This is only the first
 * step: the platform area stays closed until two-step is also passed.
 */
class SignInPlatformUser extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly PlatformAuthenticator $authenticator,
        private readonly AuthFactory $auth,
        private readonly Session $session,
        private readonly PlatformTwoStepSession $twoStep,
    ) {}

    /**
     * @throws PlatformSignInFailed
     */
    public function handle(string $email, string $password): PlatformUser
    {
        $user = $this->authenticator->attempt($email, $password) ?? throw PlatformSignInFailed::generic();

        return $this->transaction(function () use ($user) {
            $this->auth->guard('platform')->login($user);
            $this->session->regenerate();
            $this->twoStep->clear();

            $user->forceFill(['last_signed_in_at' => now()])->save();

            $this->audit(new AuditEntry(
                action: 'platform.user_signed_in',
                subjectType: 'platform_user',
                subjectUlid: $user->ulid,
            ));

            return $user;
        });
    }
}
