<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        Route::middleware(['web', 'school'])->get('/_probe', fn () => 'ok');
        Route::middleware(['web', 'school', 'module:fees'])->get('/_probe-fees', fn () => 'fees');
    }

    private function school(string $status = 'active'): Tenant
    {
        $tenant = Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => 'Probe School',
            'subdomain' => 'probe',
            'database_name' => 'shiksha_t_unused',
            'status' => $status,
        ]);

        $tenant->makeCurrent();

        return $tenant;
    }

    public function test_request_without_a_resolved_school_gets_the_neutral_not_found_page(): void
    {
        $this->get('/_probe')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('errors/school')->where('title', 'School not found'));
    }

    public function test_active_and_trial_schools_pass(): void
    {
        $this->school('active');
        $this->get('/_probe')->assertOk();

        Tenant::forgetCurrent();
        Tenant::query()->delete();

        $this->school('trial');
        $this->get('/_probe')->assertOk();
    }

    public function test_suspended_and_expired_schools_are_blocked(): void
    {
        foreach (['suspended', 'expired'] as $status) {
            $this->school($status);

            $this->get('/_probe')
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page->component('errors/school')->where('title', 'School unavailable'));

            Tenant::forgetCurrent();
            Tenant::query()->delete();
        }
    }

    public function test_module_outside_the_plan_is_not_found(): void
    {
        $tenant = $this->school();

        $this->get('/_probe-fees')->assertNotFound();

        DB::connection('landlord')->table('tenant_modules')->insert(['tenant_id' => $tenant->id, 'module' => 'fees']);

        $this->get('/_probe-fees')->assertOk();
    }

    public function test_module_gate_does_not_leak_between_schools(): void
    {
        $other = $this->school();
        DB::connection('landlord')->table('tenant_modules')->insert(['tenant_id' => $other->id, 'module' => 'fees']);
        Tenant::forgetCurrent();

        $second = Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()), 'name' => 'Second', 'subdomain' => 'second',
            'database_name' => 'shiksha_t_unused2', 'status' => 'active',
        ]);
        $second->makeCurrent();

        $this->get('/_probe-fees')->assertNotFound();
    }
}
