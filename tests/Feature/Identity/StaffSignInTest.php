<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Actions\RevokeMembership;
use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class);

function otherSchool(): Tenant
{
    return Tenant::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'name' => 'Other School', 'subdomain' => 'otherschool',
        'database_name' => 'shiksha_t_unused_other', 'status' => 'active',
    ]);
}

function signIn(string $login, string $password = 'password'): TestResponse
{
    return test()->post(route('login.store'), ['login' => $login, 'password' => $password]);
}

it('signs staff in with email or phone in any common format', function (string $login) {
    $staff = $this->staff();
    $staff->update(['email' => 'principal@example.com', 'phone_e164' => '+919876543210']);

    signIn($login === 'EMAIL' ? 'Principal@Example.com' : $login)
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($staff);
})->with(['EMAIL', '9876543210', '+91 98765 43210', '09876543210', '+919876543210']);

it('gives the same generic failure for every way sign-in can fail', function () {
    $good = $this->staff();
    $good->update(['email' => 'good@example.com', 'phone_e164' => '+919876500010']);

    $revoked = $this->staff();
    $revoked->update(['email' => 'revoked@example.com']);
    $revoked->memberships()->update(['status' => 'revoked', 'revoked_at' => now()]);

    $disabled = $this->staff();
    $disabled->update(['email' => 'disabled@example.com', 'status' => 'disabled']);

    $elsewhere = Identity::factory()->create(['email' => 'elsewhere@example.com']);
    $this->grantStaffMembership($elsewhere, otherSchool());

    $guardianOnly = Identity::factory()->create(['email' => 'guardian@example.com']);
    Membership::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'identity_id' => $guardianOnly->id,
        'tenant_id' => $this->school->id, 'kind' => Membership::KIND_GUARDIAN,
    ]);

    $attempts = [
        ['good@example.com', 'wrong-password'],
        ['nobody@example.com', 'password'],
        ['+919000099999', 'password'],
        ['not a login', 'password'],
        ['revoked@example.com', 'password'],
        ['disabled@example.com', 'password'],
        ['elsewhere@example.com', 'password'],
        ['guardian@example.com', 'password'],
    ];

    $messages = [];

    foreach ($attempts as [$login, $password]) {
        $response = signIn($login, $password);
        $this->assertGuest();
        $response->assertSessionHasErrors('login');
        $messages[] = session('errors')->first('login');
    }

    expect(array_unique($messages))->toHaveCount(1);
});

it('signs a person out of a school once their membership is revoked', function () {
    $staff = $this->staff();

    $this->actingAs($staff)->get(route('dashboard'))->assertOk();

    app(RevokeMembership::class)->handle($staff->memberships()->sole());

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('does not let a membership of one school open another school', function () {
    $staff = $this->staff();

    otherSchool()->makeCurrent();

    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('signs out a disabled identity on the next request', function () {
    $staff = $this->staff();
    $staff->update(['status' => 'disabled']);

    $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('login'));
});

it('audits membership grants and revocations in the platform log', function () {
    $identity = Identity::factory()->create();

    $membership = app(GrantMembership::class)->handle($identity, $this->school);
    app(RevokeMembership::class)->handle($membership);

    expect($membership->refresh()->status)->toBe('revoked')
        ->and($membership->revoked_at)->not->toBeNull();

    $events = PlatformAuditEvent::query()->whereIn('action', ['membership.granted', 'membership.revoked'])->orderBy('id')->get();

    expect($events->pluck('action')->all())->toBe(['membership.granted', 'membership.revoked'])
        ->and($events->pluck('tenant_id')->unique()->all())->toBe([$this->school->id])
        ->and($events->last()->before)->toBe(['status' => 'active']);
});

it('re-activates a revoked membership instead of duplicating it', function () {
    $identity = Identity::factory()->create();

    $membership = app(GrantMembership::class)->handle($identity, $this->school);
    app(RevokeMembership::class)->handle($membership);
    $again = app(GrantMembership::class)->handle($identity, $this->school);

    expect($again->is($membership))->toBeTrue()
        ->and($again->status)->toBe('active')
        ->and(Membership::query()->where('identity_id', $identity->id)->count())->toBe(1);
});

it('answers a reset-link request the same for known and unknown emails', function () {
    $staff = $this->staff();

    $known = $this->post(route('password.email'), ['email' => $staff->email]);
    $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

    $known->assertSessionHasNoErrors();
    $unknown->assertSessionHasNoErrors();

    expect(session('status'))->toBe(__(Password::RESET_LINK_SENT));
});

it('keeps names and other personal data out of the landlord identity tables', function () {
    expect(Schema::connection('landlord')->getColumnListing('identities'))
        ->not->toContain('name', 'first_name', 'last_name', 'address', 'date_of_birth');
});

it('shares only a safe shape of the signed-in identity with the browser', function () {
    $staff = $this->staff();

    $this->actingAs($staff)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('auth.user.ulid', $staff->ulid)
        ->missing('auth.user.id')
        ->missing('auth.user.password')
        ->missing('auth.user.two_factor_secret'));
});
