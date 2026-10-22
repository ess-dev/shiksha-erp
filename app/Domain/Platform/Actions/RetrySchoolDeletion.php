<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Exceptions\SchoolChangeException;
use App\Domain\Platform\Jobs\PurgeSchoolData;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;

/**
 * Queues the purge again for a school whose deletion did not finish.
 */
class RetrySchoolDeletion extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Tenant $tenant): void
    {
        $this->transaction(function () use ($tenant) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if ($tenant->status !== TenantStatus::Deleting) {
                throw SchoolChangeException::notDeleting();
            }

            $this->audit(new AuditEntry(
                action: 'platform.school_deletion_retried',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
            ), $tenant->id);
        });

        PurgeSchoolData::dispatch($tenant->id);
    }
}
