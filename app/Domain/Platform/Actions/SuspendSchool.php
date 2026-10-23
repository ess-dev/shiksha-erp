<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Enums\SuspensionReason;
use App\Domain\Platform\Exceptions\SchoolChangeException;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;

/**
 * Blocks a school's staff and parents until it is reactivated. No data is
 * deleted.
 */
class SuspendSchool extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Tenant $tenant, SuspensionReason $reason): Tenant
    {
        return $this->transaction(function () use ($tenant, $reason) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if (! in_array($tenant->status, [TenantStatus::Active, TenantStatus::Trial], true)) {
                throw SchoolChangeException::cannotSuspend();
            }

            $before = $tenant->status;

            $tenant->forceFill(['status' => TenantStatus::Suspended, 'suspended_reason' => $reason->value])->save();

            $this->audit(new AuditEntry(
                action: 'platform.school_suspended',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['status' => $before->value],
                after: ['status' => TenantStatus::Suspended->value, 'reason' => $reason->value],
            ), $tenant->id);

            return $tenant;
        });
    }
}
