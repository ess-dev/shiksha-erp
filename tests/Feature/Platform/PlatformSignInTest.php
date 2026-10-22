<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\Platform\Actions\CreatePlatformUser;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(fn () => url()->forceRootUrl('http://'.config('shiksha.platform_host')));

const PLATFORM_TEST_SECRET = 'JBSWY3DPEHPK3PXP';

function platformOtp(string $secret = PLATFORM_TEST_SECRET): string
{
    return (new Google2FA)->getCurrentOtp($secret);
}

function platformPasses(PlatformUser $user): array
{
    return ['platform.two_step_passed_for' => $user->ulid];
}

function platformAudit(string $action): ?PlatformAuditEvent
{
    return PlatformAuditEvent::query()->where('action', $action)->latest('id')->first();
}

function signInPlatform(string $email, string $password = 'password')
{
    return test()->post(route('platform.sign-in.store'), ['email' => $email, 'password' => $password]);
}

it('serves the Super Admin area only on the platform host', function () {
    $this->get(route('platform.sign-in'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('platform/auth/sign-in'),
    );

    $this->get('http://greenfield.'.config('shiksha.base_domain').'/sign-in')->assertNotFound();
    $this->get('http://'.config('shiksha.platform_host').'/login')->assertNotFound();
});

it('sends "/" to sign-in on the platform host and to the school dashboard elsewhere', function () {
    $this->get('http://'.config('shiksha.platform_host').'/')->assertRedirect(route('platform.sign-in'));
    $this->get('http://greenfield.'.config('shiksha.base_domain').'/')->assertRedirect(route('dashboard'));
});

it('keeps the platform host out of the reserved school subdomains', function () {
    $subdomain = explode('.', (string) config('shiksha.platform_host'))[0];

    expect(config('shiksha.subdomain.reserved'))->toContain($subdomain);
});

it('signs a Super Admin in and then requires two-step before anything else', function () {
    $user = PlatformUser::factory()->create(['email' => 'root@example.com']);

    signInPlatform('Root@Example.com')->assertRedirect(route('platform.dashboard'));

    $this->assertAuthenticatedAs($user, 'platform');
    $this->get(route('platform.dashboard'))->assertRedirect(route('platform.two-step.setup'));

    expect($user->fresh()->last_signed_in_at)->not->toBeNull();

    $event = platformAudit('platform.user_signed_in');
    expect($event)->not->toBeNull()
        ->and($event->actor_type)->toBe('platform')
        ->and($event->actor_identity_ulid)->toBe($user->ulid)
        ->and($event->subject_ulid)->toBe($user->ulid);
});

it('gives one generic failure for a wrong password, an unknown email and a disabled account', function () {
    PlatformUser::factory()->create(['email' => 'good@example.com']);
    PlatformUser::factory()->disabled()->create(['email' => 'off@example.com']);

    $messages = collect([
        ['good@example.com', 'wrong-password'],
        ['nobody@example.com', 'password'],
        ['off@example.com', 'password'],
    ])->map(function (array $credentials) {
        signInPlatform(...$credentials)->assertSessionHasErrors('email');

        $this->assertGuest('platform');

        return session('errors')->first('email');
    });

    expect($messages->unique())->toHaveCount(1);
    expect(platformAudit('platform.user_signed_in'))->toBeNull();
});

it('does not accept a school identity on the platform host', function () {
    $identity = Identity::factory()->create(['email' => 'principal@example.com']);

    signInPlatform($identity->email)->assertSessionHasErrors('email');

    $this->assertGuest('platform');
});

it('throttles repeated Super Admin sign-in attempts', function () {
    PlatformUser::factory()->create(['email' => 'root@example.com']);

    foreach (range(1, 5) as $_) {
        signInPlatform('root@example.com', 'wrong')->assertSessionHasErrors('email');
    }

    signInPlatform('root@example.com', 'wrong')->assertTooManyRequests();
});

it('sets up two-step: start, wrong code rejected, right code turns it on', function () {
    $user = PlatformUser::factory()->create();
    $this->actingAs($user, 'platform');

    $this->get(route('platform.two-step.setup'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('platform/auth/two-step-setup')->where('setup', null),
    );

    $this->post(route('platform.two-step.setup.begin'))->assertRedirect(route('platform.two-step.setup'));

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $this->get(route('platform.two-step.setup'))->assertInertia(
        fn (Assert $page) => $page->has('setup.qr_code_svg')->has('setup.secret_key'),
    );

    $this->post(route('platform.two-step.setup.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $secret = decrypt($user->two_factor_secret);
    $this->post(route('platform.two-step.setup.confirm'), ['code' => platformOtp($secret)])
        ->assertRedirect(route('platform.two-step.recovery-codes'));

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
    expect(platformAudit('platform.two_step_setup_started'))->not->toBeNull()
        ->and(platformAudit('platform.two_step_enabled'))->not->toBeNull();

    $this->get(route('platform.two-step.recovery-codes'))->assertOk()->assertInertia(
        fn (Assert $page) => $page->has('recoveryCodes', 8),
    );
    $this->get(route('platform.dashboard'))->assertOk();
});

it('never starts a second setup over a confirmed one', function () {
    $user = PlatformUser::factory()->withTwoFactor()->create();
    $before = $user->two_factor_secret;

    $this->actingAs($user, 'platform')->post(route('platform.two-step.setup.begin'));

    expect($user->fresh()->two_factor_secret)->toBe($before);
});

it('asks for the authenticator code and lets the right one through', function () {
    $user = PlatformUser::factory()->withTwoFactor()->create(['email' => 'root@example.com']);

    signInPlatform('root@example.com');

    $this->get(route('platform.dashboard'))->assertRedirect(route('platform.two-step.challenge'));
    $this->get(route('platform.two-step.challenge'))->assertOk();

    $this->post(route('platform.two-step.challenge.store'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->get(route('platform.dashboard'))->assertRedirect(route('platform.two-step.challenge'));

    $this->post(route('platform.two-step.challenge.store'), ['code' => platformOtp()])
        ->assertRedirect(route('platform.dashboard'));

    $this->get(route('platform.dashboard'))->assertOk();
    expect(platformAudit('platform.two_step_passed')->after)->toBe(['method' => 'authenticator']);
});

it('accepts each recovery code only once', function () {
    $user = PlatformUser::factory()->withTwoFactor()->create();
    $this->actingAs($user, 'platform');

    $this->post(route('platform.two-step.challenge.store'), ['recovery_code' => 'nope'])
        ->assertSessionHasErrors('recovery_code');

    $this->post(route('platform.two-step.challenge.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect(route('platform.dashboard'));
    $this->get(route('platform.dashboard'))->assertOk();

    expect(json_decode(decrypt($user->fresh()->two_factor_recovery_codes), true))->not->toContain('recovery-code-1');

    $this->flushSession();
    $this->actingAs($user->fresh(), 'platform')
        ->post(route('platform.two-step.challenge.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertSessionHasErrors('recovery_code');
});

it('needs a code or a recovery code at the challenge', function () {
    $this->actingAs(PlatformUser::factory()->withTwoFactor()->create(), 'platform')
        ->post(route('platform.two-step.challenge.store'), [])
        ->assertSessionHasErrors(['code', 'recovery_code']);
});

it('sends signed-out visitors to sign in from every platform page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('platform.sign-in'));
})->with([
    'platform.dashboard',
    'platform.two-step.challenge',
    'platform.two-step.setup',
    'platform.two-step.recovery-codes',
]);

it('does not let a two-step pass of one session carry to another Super Admin', function () {
    $first = PlatformUser::factory()->withTwoFactor()->create();
    $second = PlatformUser::factory()->withTwoFactor()->create();

    $this->withSession(platformPasses($first))
        ->actingAs($second, 'platform')
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('platform.two-step.challenge'));
});

it('signs a disabled Super Admin out on the next request', function () {
    $user = PlatformUser::factory()->withTwoFactor()->create();

    $this->withSession(platformPasses($user))->actingAs($user, 'platform');
    $user->update(['status' => 'disabled']);

    $this->get(route('platform.dashboard'))->assertRedirect(route('platform.sign-in'));
    $this->assertGuest('platform');
});

it('signs out, forgets the two-step pass and audits it', function () {
    $user = PlatformUser::factory()->withTwoFactor()->create();

    $this->withSession(platformPasses($user))->actingAs($user, 'platform')
        ->post(route('platform.sign-out'))
        ->assertRedirect(route('platform.sign-in'));

    $this->assertGuest('platform');
    $this->get(route('platform.dashboard'))->assertRedirect(route('platform.sign-in'));
    expect(platformAudit('platform.user_signed_out')->actor_identity_ulid)->toBe($user->ulid);
});

it('keeps Super Admin sessions and school sessions apart', function () {
    $admin = PlatformUser::factory()->withTwoFactor()->create();
    $this->withSession(platformPasses($admin))->actingAs($admin, 'platform');

    $this->get(route('platform.dashboard'))->assertOk();
    $this->get('http://greenfield.'.config('shiksha.base_domain').'/dashboard')->assertNotFound();

    $this->assertGuest('web');
});

it('shows the schools by status and shares only safe fields about the user', function () {
    $user = PlatformUser::factory()->withTwoFactor()->create(['name' => 'Asha Verma', 'email' => 'asha@example.com']);

    foreach (['active', 'active', 'trial', 'suspended'] as $i => $status) {
        Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()), 'name' => "School {$i}", 'subdomain' => "school{$i}",
            'database_name' => "shiksha_t_unused_{$i}", 'status' => $status,
        ]);
    }

    $this->withSession(platformPasses($user))->actingAs($user, 'platform')
        ->get(route('platform.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/dashboard')
            ->where('overview.schools_total', 4)
            ->where('overview.today', CalendarDate::today())
            ->where('overview.plan_names', [])
            ->where('overview.schools_by_status', ['trial' => 1, 'active' => 2, 'suspended' => 1, 'expired' => 0])
            ->where('platform.user', ['ulid' => $user->ulid, 'name' => 'Asha Verma', 'email' => 'asha@example.com']));
});

it('creates a Super Admin through the Action with an audit event and a hashed password', function () {
    $user = app(CreatePlatformUser::class)->handle('Meera Rao', 'Meera@Example.com', 'a-long-password-1');

    expect($user->email)->toBe('meera@example.com')
        ->and($user->password)->not->toBe('a-long-password-1')
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
    expect(platformAudit('platform.user_created')->subject_ulid)->toBe($user->ulid);
});
