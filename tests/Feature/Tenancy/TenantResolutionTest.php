<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\HostTenantFinder;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $subdomain, string $status = 'active'): Tenant
    {
        return Tenant::create([
            'ulid' => (string) Str::ulid(),
            'name' => ucfirst($subdomain).' Public School',
            'subdomain' => $subdomain,
            'database_name' => 'shiksha_t_'.$subdomain,
            'status' => $status,
        ]);
    }

    private function find(string $host): ?Tenant
    {
        $tenant = (new HostTenantFinder)->findForRequest(Request::create("http://{$host}/"));

        return $tenant instanceof Tenant ? $tenant : null;
    }

    public function test_resolves_school_from_subdomain(): void
    {
        $tenant = $this->makeTenant('greenfield');

        $this->assertTrue($tenant->is($this->find('greenfield.'.config('shiksha.base_domain'))));
    }

    public function test_unknown_or_nested_hosts_resolve_to_nothing(): void
    {
        $this->makeTenant('greenfield');

        $this->assertNull($this->find('unknown.'.config('shiksha.base_domain')));
        $this->assertNull($this->find('a.greenfield.'.config('shiksha.base_domain')));
        $this->assertNull($this->find('greenfield.example.com'));
    }

    public function test_only_verified_custom_domains_resolve(): void
    {
        $tenant = $this->makeTenant('riverside');

        DB::connection('landlord')->table('tenant_domains')->insert([
            ['tenant_id' => $tenant->id, 'domain' => 'erp.riverside.test', 'verified_at' => now()],
            ['tenant_id' => $tenant->id, 'domain' => 'pending.riverside.test', 'verified_at' => null],
        ]);

        $this->assertTrue($tenant->is($this->find('erp.riverside.test')));
        $this->assertNull($this->find('pending.riverside.test'));
    }

    public function test_making_a_tenant_current_switches_the_tenant_connection(): void
    {
        $tenant = $this->makeTenant('greenfield');

        $tenant->makeCurrent();

        $this->assertSame('shiksha_t_greenfield', config('database.connections.tenant.database'));

        Tenant::forgetCurrent();

        $this->assertNull(config('database.connections.tenant.database'));
    }

    public function test_tenant_credentials_are_encrypted_at_rest(): void
    {
        $tenant = $this->makeTenant('greenfield');
        $tenant->update(['database_password' => 'secret-pass']);

        $raw = DB::connection('landlord')->table('tenants')->where('id', $tenant->id)->value('database_password');

        $this->assertNotSame('secret-pass', $raw);
        $this->assertSame('secret-pass', $tenant->fresh()->database_password);
    }
}
