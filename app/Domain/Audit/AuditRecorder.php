<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Contracts\ResolvesAuditActor;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Tenancy\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes audit rows. Called by the base Action inside its transaction; the
 * request context (IP, user agent) is taken from the current request, if any.
 */
class AuditRecorder
{
    public function __construct(
        private readonly ResolvesAuditActor $actors,
        private readonly Request $request,
    ) {}

    /**
     * @param  int|null  $tenantId  Platform events only: the school concerned, when it is not the current one
     */
    public function record(AuditEntry $entry, AuditScope $scope = AuditScope::Tenant, ?int $tenantId = null): void
    {
        $actor = $this->actors->resolve();

        $common = [
            'ulid' => strtolower((string) Str::ulid()),
            'occurred_at' => now(),
            'actor_type' => $actor->type->value,
            'actor_identity_ulid' => $actor->identityUlid,
            'action' => $entry->action,
            'subject_type' => $entry->subjectType,
            'subject_ulid' => $entry->subjectUlid,
            'before' => $entry->before,
            'after' => $entry->after,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ];

        if ($scope === AuditScope::Platform) {
            PlatformAuditEvent::query()->create([
                ...$common,
                'tenant_id' => $tenantId ?? Tenant::current()?->getKey(),
            ]);

            return;
        }

        AuditEvent::query()->create([
            ...$common,
            'actor_membership_ulid' => $actor->membershipUlid,
            'actor_role_keys' => $actor->roleKeys,
        ]);
    }
}
