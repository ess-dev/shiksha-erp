<?php

namespace App\Domain\Platform\Authentication;

use App\Domain\Platform\Models\PlatformUser;
use Illuminate\Contracts\Session\Session;

/**
 * Remembers, for this browser session only, that the Super Admin has passed
 * two-step. It is bound to the user, and cleared at sign-in and sign-out.
 */
class PlatformTwoStepSession
{
    private const KEY = 'platform.two_step_passed_for';

    public function __construct(private readonly Session $session) {}

    public function markPassed(PlatformUser $user): void
    {
        $this->session->put(self::KEY, $user->ulid);
    }

    public function hasPassed(PlatformUser $user): bool
    {
        return $this->session->get(self::KEY) === $user->ulid;
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
