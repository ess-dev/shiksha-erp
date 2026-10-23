<?php

namespace App\Domain\Shared;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Audit\Exceptions\MissingAuditEvent;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Base class for every Action, the only place that writes data.
 *
 * Subclasses wrap their writes in transaction() and call audit() inside it.
 * An Action that finishes without an audit event fails and rolls back, so a
 * change can never be saved without its audit row.
 */
abstract class Action
{
    protected AuditScope $auditScope = AuditScope::Tenant;

    private int $auditEvents = 0;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    protected function transaction(Closure $work): mixed
    {
        $connection = $this->auditScope === AuditScope::Platform
            ? config('multitenancy.landlord_database_connection_name')
            : config('multitenancy.tenant_database_connection_name');

        $this->auditEvents = 0;

        return DB::connection($connection)->transaction(function () use ($work) {
            $result = $work();

            if ($this->auditEvents === 0) {
                throw MissingAuditEvent::forAction(static::class);
            }

            return $result;
        });
    }

    protected function audit(AuditEntry $entry): void
    {
        app(AuditRecorder::class)->record($entry, $this->auditScope);

        $this->auditEvents++;
    }
}
