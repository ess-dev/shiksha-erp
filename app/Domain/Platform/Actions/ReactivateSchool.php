<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Exceptions\SchoolChangeException;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;

/**
 * Lifts a suspension. A school whose trial has not ended goes back to trial;
 * every other school goes back to active.
 */
class ReactivateSchool extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Tenant $tenant): Tenant
    {
        return $this->transaction(function () use ($tenant) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if ($tenant->status !== TenantStatus::Suspended) {
                throw SchoolChangeException::notSuspended();
            }

            $resumeAs = $tenant->trial_ends_on !== null && $tenant->trial_ends_on >= CalendarDate::today()
                ? TenantStatus::Trial
                : TenantStatus::Active;

            $reason = $tenant->suspended_reason;

            $tenant->forceFill(['status' => $resumeAs, 'suspended_reason' => null])->save();

            $this->audit(new AuditEntry(
                action: 'platform.school_reactivated',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['status' => TenantStatus::Suspended->value, 'reason' => $reason],
                after: ['status' => $resumeAs->value],
            ), $tenant->id);

            return $tenant;
        });
    }
}
