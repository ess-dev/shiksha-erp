<?php

use App\Domain\Access\Models\RoleAssignment;
use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\School\Actions\SetUpInitialStructure;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Campus;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use Database\Seeders\AccessCatalogSeeder;
use Database\Seeders\PlanCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(DatabaseMigrations::class);

beforeEach(function () {
    url()->forceRootUrl('http://'.config('shiksha.platform_host'));
    $this->seed([AccessCatalogSeeder::class, PlanCatalogSeeder::class]);

    $admin = PlatformUser::factory()->withTwoFactor()->create();
    $this->withSession(['platform.two_step_passed_for' => $admin->ulid])->actingAs($admin, 'platform');
});

afterEach(function () {
    foreach (Tenant::query()->pluck('database_name') as $database) {
        DB::connection('landlord')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
    }
});

function onboardingPayload(array $overrides = []): array
{
    $growth = DB::connection('landlord')->table('plans')->where('key', 'growth')->first();
    $modules = DB::connection('landlord')->table('plan_modules')->where('plan_id', $growth->id)->pluck('module_key')->all();

    $payload = array_replace_recursive([
        'org' => [
            'name' => 'Holy Heart Convent School', 'subdomain' => 'holyheart', 'board' => 'CBSE', 'city' => 'Patiala',
            'state' => 'Punjab', 'contact_name' => 'Sr. Mary Joseph', 'contact_phone' => '9876543210',
            'contact_email' => 'principal@holyheart.test',
        ],
        'subscription' => ['plan' => $growth->ulid, 'start' => 'trial'],
        'campuses' => [['name' => 'Main campus', 'address' => 'Model Town, Patiala'], ['name' => 'Annex', 'address' => '']],
        'academic_year' => ['name' => '2026–27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31'],
        'classes' => [['level' => 1, 'sections' => ['A', 'B']], ['level' => 2, 'sections' => ['A']]],
        'staff' => [
            ['name' => 'Sr. Mary Joseph', 'email' => 'principal@holyheart.test', 'role' => 'principal'],
            ['name' => 'Gurpreet Kaur', 'email' => 'accounts@holyheart.test', 'role' => 'accountant'],
            ['name' => 'Rohit Verma', 'email' => 'rohit@holyheart.test', 'role' => 'teacher'],
        ],
        'modules' => $modules,
    ], $overrides);

    // A list given in the overrides replaces the default list instead of merging into it.
    foreach (['classes', 'campuses', 'modules'] as $list) {
        if (array_key_exists($list, $overrides)) {
            $payload[$list] = $overrides[$list];
        }
    }

    return $payload;
}

function schoolDatabaseCount(): int
{
    return DB::connection('landlord')->table('pg_database')->where('datname', 'like', 'shiksha_t_0%')->count();
}

it('creates a school with its subscription, modules, people and structure', function () {
    $payload = onboardingPayload();

    $response = $this->post(route('platform.schools.onboard.store'), $payload);

    $tenant = Tenant::query()->where('subdomain', 'holyheart')->firstOrFail();
    $response->assertRedirect(route('platform.schools.onboarded', $tenant));

    expect($tenant->status->value)->toBe('trial')
        ->and($tenant->trial_ends_on)->toBe((new DateTimeImmutable(CalendarDate::today()))->modify('+30 days')->format('Y-m-d'))
        ->and($tenant->renews_on)->toBeNull()
        ->and($tenant->city)->toBe('Patiala')
        ->and($tenant->contact_phone)->toBe('+919876543210')
        ->and($tenant->campus_count)->toBe(2)
        ->and($tenant->student_count)->toBe(0)
        ->and(DB::connection('landlord')->table('tenant_modules')->where('tenant_id', $tenant->id)->count())->toBe(count($payload['modules']))
        ->and(Membership::query()->where('tenant_id', $tenant->id)->count())->toBe(3)
        ->and(Identity::query()->whereIn('email', ['principal@holyheart.test', 'accounts@holyheart.test', 'rohit@holyheart.test'])->count())->toBe(3);

    $tenant->execute(function () {
        expect(Staff::query()->count())->toBe(3)
            ->and(Campus::query()->orderBy('id')->pluck('code')->all())->toBe(['MAIN', 'ANNEX'])
            ->and(Campus::query()->where('code', 'MAIN')->value('address'))->toBe('Model Town, Patiala')
            ->and(AcademicYear::query()->where('is_current', true)->value('name'))->toBe('2026–27')
            // (2 + 1 sections) at each of 2 campuses
            ->and(Section::query()->count())->toBe(6);

        $roles = RoleAssignment::query()->with('role')->get()->map(fn ($assignment) => $assignment->role->source_template_key)->sort()->values()->all();

        // The teacher gets a staff record but no role yet.
        expect($roles)->toBe(['accountant', 'principal']);
    });

    $event = PlatformAuditEvent::query()->where('action', 'platform.school_onboarded')->firstOrFail();

    expect($event->tenant_id)->toBe($tenant->id)
        ->and($event->after['sections'])->toBe(6)
        ->and(PlatformAuditEvent::query()->where('action', 'platform.tenant_provisioned')->count())->toBe(0);

    $onboarded = session('onboarded');

    expect($onboarded['ulid'])->toBe($tenant->ulid)
        ->and($onboarded['invites'])->toHaveCount(3)
        ->and($onboarded['invites'][0]['link'])->toStartWith('http://holyheart.'.config('shiksha.base_domain'))
        ->and($onboarded['invites'][0]['link'])->toContain('/reset-password/');
});

it('shows the set-password links once on the success page', function () {
    $this->post(route('platform.schools.onboard.store'), onboardingPayload());
    $tenant = Tenant::query()->where('subdomain', 'holyheart')->firstOrFail();

    $this->get(route('platform.schools.onboarded', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/school-onboarded')
            ->where('onboarded.name', 'Holy Heart Convent School')
            ->has('onboarded.invites', 3)
            ->missing('onboarded.id'));

    // A reload no longer has the links.
    $this->get(route('platform.schools.onboarded', $tenant))->assertRedirect(route('platform.schools.show', $tenant));
});

it('starts an active subscription renewing at the end of the academic year', function () {
    $this->post(route('platform.schools.onboard.store'), onboardingPayload(['subscription' => ['start' => 'paid']]));

    $tenant = Tenant::query()->where('subdomain', 'holyheart')->firstOrFail();

    expect($tenant->status->value)->toBe('active')
        ->and($tenant->trial_ends_on)->toBeNull()
        ->and($tenant->renews_on)->toBe('2027-03-31');
});

it('can skip the classes and sections', function () {
    $this->post(route('platform.schools.onboard.store'), onboardingPayload(['classes' => []]))->assertRedirect();

    Tenant::query()->where('subdomain', 'holyheart')->firstOrFail()->execute(function () {
        expect(Section::query()->count())->toBe(0)->and(Campus::query()->count())->toBe(2);
    });
});

it('reuses an existing sign-in and gives no link for it', function () {
    Identity::factory()->create(['email' => 'accounts@holyheart.test']);

    $this->post(route('platform.schools.onboard.store'), onboardingPayload());

    $invites = collect(session('onboarded')['invites'])->keyBy('email');

    expect(Identity::query()->where('email', 'accounts@holyheart.test')->count())->toBe(1)
        ->and($invites['accounts@holyheart.test']['link'])->toBeNull()
        ->and($invites['principal@holyheart.test']['link'])->not->toBeNull();
});

it('discards the half-built school when a step fails', function () {
    $databasesBefore = schoolDatabaseCount();
    $this->mock(SetUpInitialStructure::class)->shouldReceive('handle')->andThrow(new RuntimeException('boom'));
    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('platform.schools.onboard.store'), onboardingPayload()))->toThrow(RuntimeException::class);

    expect(Tenant::query()->count())->toBe(0)
        ->and(Identity::query()->count())->toBe(0)
        ->and(Membership::query()->count())->toBe(0)
        ->and(PlatformAuditEvent::query()->where('action', 'platform.school_onboarded')->count())->toBe(0)
        ->and(schoolDatabaseCount())->toBe($databasesBefore);

    // The same details can be submitted again once the step works.
    $this->app->forgetInstance(SetUpInitialStructure::class);
});

it('rejects invalid onboarding details', function (array $overrides, string $errorKey) {
    $this->post(route('platform.schools.onboard.store'), onboardingPayload($overrides))->assertSessionHasErrors($errorKey);

    expect(Tenant::query()->count())->toBe(0);
})->with([
    'short name' => [['org' => ['name' => 'AB']], 'org.name'],
    'reserved web address' => [['org' => ['subdomain' => 'admin']], 'org.subdomain'],
    'web address with spaces' => [['org' => ['subdomain' => 'holy heart']], 'org.subdomain'],
    'unknown board' => [['org' => ['board' => 'Nonsense']], 'org.board'],
    'landline phone' => [['org' => ['contact_phone' => '1234567890']], 'org.contact_phone'],
    'bad email' => [['org' => ['contact_email' => 'nope']], 'org.contact_email'],
    'unknown plan' => [['subscription' => ['plan' => 'nope']], 'subscription.plan'],
    'unknown start' => [['subscription' => ['start' => 'free']], 'subscription.start'],
    'year ends before it starts' => [['academic_year' => ['ends_on' => '2026-03-31']], 'academic_year.ends_on'],
    'no principal' => [['staff' => [0 => ['role' => 'coordinator']]], 'staff'],
    'unknown role' => [['staff' => [1 => ['role' => 'wizard']]], 'staff.1.role'],
    'automatic teacher role' => [['staff' => [1 => ['role' => 'class_teacher']]], 'staff.1.role'],
    'repeated email' => [['staff' => [1 => ['email' => 'PRINCIPAL@holyheart.test']]], 'staff.1.email'],
    'module not in plan' => [['modules' => ['whatsapp']], 'modules'],
    'no modules' => [['modules' => []], 'modules'],
    'lowercase section' => [['classes' => [['level' => 1, 'sections' => ['a']]]], 'classes.0.sections.0'],
    'repeated section' => [['classes' => [['level' => 1, 'sections' => ['A', 'A']]]], 'classes.0.sections.1'],
    'repeated class' => [['classes' => [['level' => 1, 'sections' => ['A']], ['level' => 1, 'sections' => ['B']]]], 'classes.1.level'],
    'no campus' => [['campuses' => []], 'campuses'],
]);

it('rejects a name or web address that is already taken', function () {
    $this->post(route('platform.schools.onboard.store'), onboardingPayload());

    $this->post(route('platform.schools.onboard.store'), onboardingPayload(['org' => ['name' => 'holy heart convent school']]))
        ->assertSessionHasErrors('org.name');

    $this->post(route('platform.schools.onboard.store'), onboardingPayload(['org' => ['name' => 'Another School']]))
        ->assertSessionHasErrors('org.subdomain');

    expect(Tenant::query()->count())->toBe(1);
});

it('serves the wizard with plans, modules and choices', function () {
    $this->get(route('platform.schools.onboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/onboard-school')
            ->has('options.plans', 3)
            ->has('options.modules', 14)
            ->where('options.trial_days', 30)
            ->where('options.boards.0', 'CBSE')
            ->where('options.staff_roles.0.key', 'principal')
            ->where('options.staff_roles.0.requires_two_step', true)
            ->where('options.staff_roles', fn ($roles) => collect($roles)->firstWhere('key', 'teacher')['requires_two_step'] === false)
            ->where('options.staff_roles', fn ($roles) => collect($roles)->pluck('key')->contains('teacher') && ! collect($roles)->pluck('key')->contains('class_teacher')));
});

it('keeps onboarding behind sign-in', function () {
    auth('platform')->logout();
    $this->flushSession();

    $this->get(route('platform.schools.onboard'))->assertRedirect();
    $this->post(route('platform.schools.onboard.store'), onboardingPayload())->assertRedirect();

    expect(Tenant::query()->count())->toBe(0);
});
