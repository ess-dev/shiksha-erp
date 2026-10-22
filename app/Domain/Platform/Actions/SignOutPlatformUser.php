<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\Action;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Session\Session;

class SignOutPlatformUser extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly Session $session,
    ) {}

    public function handle(PlatformUser $user): void
    {
        $this->transaction(function () use ($user) {
            $this->audit(new AuditEntry(
                action: 'platform.user_signed_out',
                subjectType: 'platform_user',
                subjectUlid: $user->ulid,
            ));

            $this->auth->guard('platform')->logout();
            $this->session->invalidate();
            $this->session->regenerateToken();
        });
    }
}
