<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Platform\Models\PlatformUser;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    url()->forceRootUrl('http://'.config('shiksha.platform_host'));

    $user = PlatformUser::factory()->withTwoFactor()->create();
    $this->withSession(['platform.two_step_passed_for' => $user->ulid])->actingAs($user, 'platform');

    $this->gatewaySchool = Tenant::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'name' => 'Gateway School', 'subdomain' => 'gateway',
        'database_name' => 'shiksha_t_unused_gateway', 'status' => 'active',
    ]);
});

it('offers the gateways allowed in this environment and shows the current choice', function () {
    $this->get(route('platform.schools.show', $this->gatewaySchool))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('school.payment_gateway', null)
            ->where('school.gateway_options', [['key' => 'fake', 'label' => 'Test gateway (no real money)']]));
});

it('lets the Super Admin choose a gateway for a school, and records it', function () {
    $this->patch(route('platform.schools.payment-gateway', $this->gatewaySchool), ['gateway' => 'fake'])->assertRedirect();

    expect($this->gatewaySchool->fresh()->payment_gateway)->toBe('fake');

    $event = PlatformAuditEvent::query()->where('action', 'platform.school_payment_gateway_set')->firstOrFail();
    expect($event->before)->toBe(['payment_gateway' => null])
        ->and($event->after)->toBe(['payment_gateway' => 'fake'])
        ->and($event->tenant_id)->toBe($this->gatewaySchool->id);

    $this->patch(route('platform.schools.payment-gateway', $this->gatewaySchool), ['gateway' => ''])->assertRedirect();
    expect($this->gatewaySchool->fresh()->payment_gateway)->toBeNull();
});

it('refuses a gateway that does not exist or is not allowed in this environment', function () {
    $this->patch(route('platform.schools.payment-gateway', $this->gatewaySchool), ['gateway' => 'razorpay'])->assertRedirect();
    expect($this->gatewaySchool->fresh()->payment_gateway)->toBeNull();

    config(['shiksha.payment_gateways.fake.environments' => ['production']]);
    $this->patch(route('platform.schools.payment-gateway', $this->gatewaySchool), ['gateway' => 'fake'])->assertRedirect();
    expect($this->gatewaySchool->fresh()->payment_gateway)->toBeNull();

    $this->get(route('platform.schools.show', $this->gatewaySchool))
        ->assertInertia(fn (Assert $page) => $page->where('school.gateway_options', []));
});

it('keeps the gateway choice off school staff and parents', function () {
    auth('platform')->logout();

    $this->patch(route('platform.schools.payment-gateway', $this->gatewaySchool), ['gateway' => 'fake'])->assertRedirect();

    expect($this->gatewaySchool->fresh()->payment_gateway)->toBeNull();
});
