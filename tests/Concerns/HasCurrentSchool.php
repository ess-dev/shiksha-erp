<?php

namespace Tests\Concerns;

use App\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * Makes an active school current, as the host finder does for a real request.
 * Requires the landlord database to be migrated first (RefreshDatabase).
 */
trait HasCurrentSchool
{
    protected Tenant $school;

    protected function setUpHasCurrentSchool(): void
    {
        $this->school = Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => 'Test Public School',
            'subdomain' => 'testschool',
            'database_name' => 'shiksha_t_unused',
            'status' => 'active',
        ]);

        $this->school->makeCurrent();
    }
}
