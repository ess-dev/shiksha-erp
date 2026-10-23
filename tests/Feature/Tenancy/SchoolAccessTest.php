<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Models\Identity;
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

    public function test_suspended_and_expired_schools_get_their_own_subscription_screen(): void
    {
        foreach (['suspended' => 'Subscription suspended', 'expired' => 'Subscription expired'] as $status => $title) {
            $this->school($status);

            $this->get('/_probe')
                ->assertForbidden()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('errors/subscription')
                    ->where('status', $status)
                    ->where('title', $title)
                    ->where('message', fn ($message) => str_contains($message, 'Probe School'))
                    ->where('reason', null)
                    ->where('contact', null));

            Tenant::forgetCurrent();
            Tenant::query()->delete();
        }
    }

    public function test_the_suspension_reason_is_only_shown_to_a_signed_in_person(): void
    {
        $tenant = $this->school('suspended');
        $tenant->forceFill(['suspended_reason' => 'invoice_overdue'])->save();

        $this->get('/_probe')->assertInertia(fn (Assert $page) => $page->where('reason', null));

        $member = Identity::factory()->create();
        app(GrantMembership::class)->handle($member, $tenant);

        $this->actingAs($member)
            ->get('/_probe')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->where('reason', 'Reason: Invoice overdue by 60 days'));
    }

    public function test_a_billing_contact_appears_only_when_one_is_configured(): void
    {
        config(['shiksha.billing_email' => 'billing@shiksha.test']);

        $this->school('suspended');

        $this->get('/_probe')->assertInertia(fn (Assert $page) => $page
            ->where('contact.label', 'Contact billing')
            ->where('contact.href', 'mailto:billing@shiksha.test'));
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
