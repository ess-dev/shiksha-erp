<?php

namespace App\Domain\Platform\Jobs;

use App\Domain\Platform\Actions\PurgeSchool;
use App\Tenancy\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Finishes deleting a school. Landlord-level: it carries only the school's id
 * and never switches to the school's database. Safe to run again.
 */
class PurgeSchoolData implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $tenantId) {}

    public function handle(PurgeSchool $purge): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant instanceof Tenant) {
            $purge->handle($tenant);
        }
    }
}
