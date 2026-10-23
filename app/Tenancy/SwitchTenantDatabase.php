<?php

namespace App\Tenancy;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;

/**
 * Switches the tenant connection to the school's database, using its own
 * encrypted credentials when it has them and the configured defaults otherwise.
 */
class SwitchTenantDatabase extends SwitchTenantDatabaseTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        if ($tenant instanceof Tenant) {
            $this->setCredentials($tenant->database_username, $tenant->database_password);
        }

        parent::makeCurrent($tenant);
    }

    public function forgetCurrent(): void
    {
        $this->setCredentials(null, null);

        parent::forgetCurrent();
    }

    protected function setCredentials(?string $username, ?string $password): void
    {
        $connection = $this->tenantDatabaseConnectionName();

        config([
            "database.connections.{$connection}.username" => $username
                ?? config('multitenancy.tenant_default_username'),
            "database.connections.{$connection}.password" => $password
                ?? config('multitenancy.tenant_default_password'),
        ]);
    }
}
