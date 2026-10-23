<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Contracts\ResolvesAuditActor;
use App\Domain\Audit\Data\AuditActor;
use App\Domain\Audit\Enums\AuditActorType;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Placeholder until identities and memberships exist: a signed-in user is
 * recorded as a plain "user" without identity or role detail; no one signed
 * in (jobs, commands, seeders) is the system.
 */
class SessionAuditActor implements ResolvesAuditActor
{
    public function __construct(private readonly AuthFactory $auth) {}

    public function resolve(): AuditActor
    {
        return $this->auth->guard()->check()
            ? new AuditActor(AuditActorType::User)
            : AuditActor::system();
    }
}
