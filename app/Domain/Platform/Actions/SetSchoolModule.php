<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Exceptions\SchoolChangeException;
use App\Domain\Platform\Models\PlatformModule;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Switches one sellable module on or off for one school, whatever its plan
 * says (an add-on, or a module turned off). Data is kept.
 */
class SetSchoolModule extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Tenant $tenant, string $moduleKey, bool $enabled): Tenant
    {
        return $this->transaction(function () use ($tenant, $moduleKey, $enabled) {
            if (! PlatformModule::query()->where('key', $moduleKey)->exists()) {
                throw SchoolChangeException::unknownModule($moduleKey);
            }

            $modules = DB::connection(config('multitenancy.landlord_database_connection_name'))->table('tenant_modules');
            $wasEnabled = (clone $modules)->where('tenant_id', $tenant->id)->where('module', $moduleKey)->exists();

            if ($enabled) {
                (clone $modules)->insertOrIgnore([
                    'tenant_id' => $tenant->id,
                    'module' => $moduleKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                (clone $modules)->where('tenant_id', $tenant->id)->where('module', $moduleKey)->delete();
            }

            $this->audit(new AuditEntry(
                action: $enabled ? 'platform.school_module_enabled' : 'platform.school_module_disabled',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['module' => $moduleKey, 'enabled' => $wasEnabled],
                after: ['module' => $moduleKey, 'enabled' => $enabled],
            ), $tenant->id);

            return $tenant;
        });
    }
}
