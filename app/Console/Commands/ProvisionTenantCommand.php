<?php

namespace App\Console\Commands;

use App\Tenancy\ProvisionTenant;
use Illuminate\Console\Command;

class ProvisionTenantCommand extends Command
{
    protected $signature = 'tenants:provision {name : School name} {subdomain : Subdomain, e.g. greenfield} {--status=trial}';

    protected $description = 'Create a school: its database, tenant migrations and landlord record';

    public function handle(ProvisionTenant $provision): int
    {
        $name = $this->argument('name');
        $subdomain = $this->argument('subdomain');
        $status = $this->option('status');

        if (! is_string($name) || ! is_string($subdomain) || ! is_string($status)) {
            $this->error('Name, subdomain and status must be text.');

            return self::FAILURE;
        }

        $tenant = $provision->handle($name, $subdomain, $status);

        $this->info("Provisioned {$tenant->name} at {$tenant->subdomain}.".config('shiksha.base_domain'));

        return self::SUCCESS;
    }
}
