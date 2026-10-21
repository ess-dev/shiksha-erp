<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class ProvisionTenantTest extends TestCase
{
    use DatabaseMigrations;

    protected function tearDown(): void
    {
        foreach (Tenant::query()->pluck('database_name') as $database) {
            DB::connection('landlord')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
        }

        parent::tearDown();
    }

    private function databaseExists(string $name): bool
    {
        return DB::connection('landlord')->table('pg_database')->where('datname', $name)->exists();
    }

    public function test_provisioning_creates_database_and_runs_tenant_migrations(): void
    {
        $tenant = app(ProvisionTenant::class)->handle('Test School', 'testschool');

        $this->assertTrue($this->databaseExists($tenant->database_name));
        $this->assertStringStartsWith('shiksha_t_', $tenant->database_name);

        $tenant->execute(function () {
            $this->assertTrue(DB::connection('tenant')->getSchemaBuilder()->hasTable('migrations'));
            $this->assertStringContainsString('shiksha_t_', DB::connection('tenant')->getDatabaseName());
        });
    }

    public function test_failed_provisioning_leaves_nothing_behind(): void
    {
        Tenant::query()->create([
            'ulid' => '01hzzzzzzzzzzzzzzzzzzzzzzz', 'name' => 'Taken', 'subdomain' => 'taken',
            'database_name' => 'shiksha_t_taken', 'status' => 'active',
        ]);

        try {
            app(ProvisionTenant::class)->handle('Duplicate', 'taken');
            $this->fail('Expected a unique violation.');
        } catch (\Throwable) {
            $this->assertSame(1, Tenant::query()->count());
        }
    }

    public function test_invalid_subdomain_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProvisionTenant::class)->handle('Bad', 'Bad_Name');
    }

    public function test_reserved_subdomain_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ProvisionTenant::class)->handle('Platform', 'admin');
    }

    public function test_data_in_one_school_is_invisible_from_another(): void
    {
        $a = app(ProvisionTenant::class)->handle('School A', 'school-a');
        $b = app(ProvisionTenant::class)->handle('School B', 'school-b');

        $a->execute(function () {
            DB::connection('tenant')->statement('CREATE TABLE isolation_probe (name text)');
            DB::connection('tenant')->table('isolation_probe')->insert(['name' => 'only in A']);
        });

        $b->execute(function () {
            $this->assertFalse(DB::connection('tenant')->getSchemaBuilder()->hasTable('isolation_probe'));
        });

        $a->execute(function () {
            $this->assertSame(1, DB::connection('tenant')->table('isolation_probe')->count());
        });
    }
}
