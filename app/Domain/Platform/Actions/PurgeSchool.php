<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes everything that belongs to a school that DeleteSchool put in
 * "deleting". Every step is idempotent, and the landlord rows go last, so a
 * failure leaves the school visible as "deleting" and the job can be retried.
 *
 * KEEP IN SYNC: whenever a feature adds school-owned data outside the school's
 * own database (a landlord table with tenant_id, files, cache or queue
 * entries, external resources), remove it here and extend the test in
 * tests/Feature/Platform/DeleteSchoolTest.php. Data inside the school's
 * database needs nothing: the whole database is dropped.
 */
class PurgeSchool extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function handle(Tenant $tenant): void
    {
        if ($tenant->status !== TenantStatus::Deleting) {
            return;
        }

        $landlord = DB::connection(config('multitenancy.landlord_database_connection_name'));

        // Not transactional, so these run before the landlord rows are removed.
        Tenant::forgetCurrent();
        DB::purge((string) config('multitenancy.tenant_database_connection_name'));

        if (str_starts_with($tenant->database_name, 'shiksha_t_')) {
            $landlord->statement('DROP DATABASE IF EXISTS '.$landlord->getQueryGrammar()->wrap($tenant->database_name).' WITH (FORCE)');
        }

        Storage::disk('tenant')->deleteDirectory("tenants/{$tenant->ulid}");

        $this->transaction(function () use ($tenant, $landlord) {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            $identityIds = $landlord->table('memberships')->where('tenant_id', $tenant->id)->pluck('identity_id');

            $landlord->table('memberships')->where('tenant_id', $tenant->id)->delete();
            $landlord->table('tenant_modules')->where('tenant_id', $tenant->id)->delete();
            $landlord->table('tenant_domains')->where('tenant_id', $tenant->id)->delete();

            // Sign-in identities that belonged only to this school have nothing left to sign in to.
            $landlord->table('identities')
                ->whereIn('id', $identityIds)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('memberships')->whereColumn('memberships.identity_id', 'identities.id'))
                ->delete();

            $this->audit(new AuditEntry(
                action: 'platform.school_deleted',
                subjectType: 'school',
                subjectUlid: $tenant->ulid,
                before: ['name' => $tenant->name, 'subdomain' => $tenant->subdomain],
            ), $tenant->id);

            $tenant->delete();
        });
    }
}
