<?php

namespace App\Tenancy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Creates a school: landlord row, its own database, and its tenant migrations.
 * On failure the half-built school is removed so the call can be retried.
 */
class ProvisionTenant
{
    public const SUBDOMAIN_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    public function handle(string $name, string $subdomain, string $status = 'trial'): Tenant
    {
        if (! preg_match(self::SUBDOMAIN_PATTERN, $subdomain)) {
            throw new InvalidArgumentException("Invalid subdomain [{$subdomain}].");
        }

        $ulid = strtolower((string) Str::ulid());
        $databaseName = 'shiksha_t_'.$ulid;

        $tenant = Tenant::query()->create([
            'ulid' => $ulid,
            'name' => $name,
            'subdomain' => $subdomain,
            'database_name' => $databaseName,
            'status' => $status,
        ]);

        try {
            $landlord = DB::connection(config('multitenancy.landlord_database_connection_name'));
            $landlord->statement('CREATE DATABASE '.$landlord->getQueryGrammar()->wrap($databaseName));

            $this->migrate($tenant);
        } catch (Throwable $e) {
            $this->rollBack($tenant);

            throw $e;
        }

        return $tenant;
    }

    public function migrate(Tenant $tenant): void
    {
        $tenant->execute(function () {
            Artisan::call('migrate', [
                '--database' => config('multitenancy.tenant_database_connection_name'),
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);
        });
    }

    private function rollBack(Tenant $tenant): void
    {
        Tenant::forgetCurrent();

        $landlord = DB::connection(config('multitenancy.landlord_database_connection_name'));
        $landlord->statement('DROP DATABASE IF EXISTS '.$landlord->getQueryGrammar()->wrap($tenant->database_name));

        $tenant->delete();
    }
}
