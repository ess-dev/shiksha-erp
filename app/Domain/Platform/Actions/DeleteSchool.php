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
 * First step of permanently deleting a school: blocks all access at once
 * (status "deleting") and queues the purge. The purge itself is PurgeSchool;
 * if it fails the school stays in "deleting" and can be retried, so nothing
 * is ever half-removed without a visible record.
 */
class DeleteSchool extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public static function confirmationPhrase(Tenant $tenant): string
    {
        return 'delete '.$tenant->name;
    }

    public function handle(Tenant $tenant, string $confirmation): void
    {
        if (trim($confirmation) !== self::confirmationPhrase($tenant)) {
            throw SchoolChangeException::deleteNotConfirmed();
        }

        $this->transaction(function () use ($tenant) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if ($tenant->status === TenantStatus::Deleting) {
                $this->audit(new AuditEntry(
                    action: 'platform.school_deletion_retried',
                    subjectType: 'school',
                    subjectUlid: $tenant->ulid,
                ), $tenant->id);

                return;
            }

            $before = $tenant->status;

            $tenant->forceFill(['status' => TenantStatus::Deleting])->save();

            $this->audit(new AuditEntry(
                action: 'platform.school_deletion_started',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['name' => $tenant->name, 'subdomain' => $tenant->subdomain, 'status' => $before->value],
                after: ['status' => TenantStatus::Deleting->value],
            ), $tenant->id);
        });

        PurgeSchoolData::dispatch($tenant->id);
    }
}
