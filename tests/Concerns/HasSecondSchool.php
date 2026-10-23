<?php

namespace Tests\Concerns;

use App\Tenancy\Tenant;
use Illuminate\Support\Str;

/**
 * A second school for tenant-isolation tests, next to the one HasCurrentSchool
 * makes current. It has its own real database that is created and migrated once
 * per run and emptied around every test, so no test provisions or drops
 * databases itself.
 *
 * Use it together with HasCurrentSchool (list HasCurrentSchool first). The
 * other school is not current; run code as it with
 * `$this->otherSchool->execute(fn () => ...)`, which restores the first school
 * afterwards.
 */
trait HasSecondSchool
{
    public const OTHER_SCHOOL_DATABASE = 'shiksha_t_testshared2';

    public Tenant $otherSchool;

    protected function setUpHasSecondSchool(): void
    {
        self::prepareSharedSchoolDatabase(self::OTHER_SCHOOL_DATABASE);
        self::resetSharedSchoolDatabase(self::OTHER_SCHOOL_DATABASE);

        $this->otherSchool = Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => 'Other Public School',
            'subdomain' => 'otherschool',
            'database_name' => self::OTHER_SCHOOL_DATABASE,
            'status' => 'active',
        ]);

        $this->beforeApplicationDestroyed(fn () => self::resetSharedSchoolDatabase(self::OTHER_SCHOOL_DATABASE));
    }
}
