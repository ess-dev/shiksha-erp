<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Tenancy\Tenant;
use Database\Seeders\PlanCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    url()->forceRootUrl('http://'.config('shiksha.platform_host'));
    $this->seed(PlanCatalogSeeder::class);
});

function plansAdmin(): PlatformUser
{
    $user = PlatformUser::factory()->withTwoFactor()->create();

    test()->withSession(['platform.two_step_passed_for' => $user->ulid])->actingAs($user, 'platform');

    return $user;
}

function plan(string $key): Plan
{
    return Plan::query()->where('key', $key)->firstOrFail();
}

function planModuleKeys(Plan $plan): array
{
    return $plan->planModules()->pluck('module_key')->sort()->values()->all();
}

function schoolOnPlan(Plan $plan, string $subdomain, array $modules = []): Tenant
{
    $tenant = Tenant::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'name' => ucfirst($subdomain), 'subdomain' => $subdomain,
        'database_name' => "shiksha_t_unused_{$subdomain}", 'status' => 'active',
    ]);
    $tenant->forceFill(['plan_id' => $plan->id])->save();

    foreach ($modules as $module) {
        DB::connection('landlord')->table('tenant_modules')->insert([
            'tenant_id' => $tenant->id, 'module' => $module, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $tenant;
}

function tenantModuleKeys(Tenant $tenant): array
{
    return DB::connection('landlord')->table('tenant_modules')
        ->where('tenant_id', $tenant->id)->pluck('module')->sort()->values()->all();
}

function savePlan(Plan $plan, array $overrides = [])
{
    return test()->patch(route('platform.plans.update', $plan), [
        'price' => $plan->price_per_student_year_paise / 100,
        'min_students' => $plan->min_students,
        'modules' => planModuleKeys($plan),
        ...$overrides,
    ]);
}

it('seeds the three plans with the prototype prices and minimums in paise', function () {
    expect(plan('starter')->only(['price_per_student_year_paise', 'min_students']))->toBe(['price_per_student_year_paise' => 45000, 'min_students' => 100])
        ->and(plan('growth')->only(['price_per_student_year_paise', 'min_students']))->toBe(['price_per_student_year_paise' => 75000, 'min_students' => 200])
        ->and(plan('premium')->only(['price_per_student_year_paise', 'min_students']))->toBe(['price_per_student_year_paise' => 110000, 'min_students' => 300]);

    expect(planModuleKeys(plan('starter')))->toBe(['attendance', 'communication', 'fees'])
        ->and(planModuleKeys(plan('growth')))->toHaveCount(11)
        ->and(planModuleKeys(plan('premium')))->toHaveCount(13)
        ->and(plan('growth')->badge)->toBe('Most popular');
});

it('does not overwrite an edited plan when the seeder runs again', function () {
    $growth = plan('growth');
    $growth->update(['price_per_student_year_paise' => 80000]);
    $growth->planModules()->where('module_key', 'homework')->delete();

    $this->seed(PlanCatalogSeeder::class);

    expect(plan('growth')->price_per_student_year_paise)->toBe(80000)
        ->and(planModuleKeys(plan('growth')))->not->toContain('homework')
        ->and(Plan::query()->count())->toBe(3);
});

it('lists plans, modules and school counts for a Super Admin who passed two-step', function () {
    schoolOnPlan(plan('growth'), 'alpha');
    schoolOnPlan(plan('growth'), 'beta');
    schoolOnPlan(plan('starter'), 'gamma');
    plansAdmin();

    $this->get(route('platform.plans.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('platform/plans')
        ->has('plans', 3)
        ->where('plans.0.name', 'Starter')
        ->where('plans.0.price_per_student_year_paise', 45000)
        ->where('plans.0.schools_count', 1)
        ->where('plans.1.name', 'Growth')
        ->where('plans.1.schools_count', 2)
        ->where('plans.1.badge', 'Most popular')
        ->where('plans.2.schools_count', 0)
        ->has('modules', 14)
        ->where('modules.0', ['key' => 'admissions', 'label' => 'Admissions'])
        ->missing('plans.0.id'));
});

it('keeps the Plans screen behind sign-in, two-step and the platform host', function () {
    $this->get(route('platform.plans.index'))->assertRedirect(route('platform.sign-in'));
    $this->patch(route('platform.plans.update', plan('growth')), [])->assertRedirect(route('platform.sign-in'));

    $user = PlatformUser::factory()->withTwoFactor()->create();
    $this->actingAs($user, 'platform')
        ->get(route('platform.plans.index'))->assertRedirect(route('platform.two-step.challenge'));

    $this->withSession(['platform.two_step_passed_for' => $user->ulid])
        ->get('http://greenfield.'.config('shiksha.base_domain').'/plans')->assertNotFound();
});

it('saves price in paise, minimum students and modules, with a toast and an audit event', function () {
    $user = plansAdmin();
    $growth = plan('growth');

    savePlan($growth, ['price' => 800, 'min_students' => 250, 'modules' => ['attendance', 'fees', 'whatsapp']])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $growth->refresh();
    expect($growth->price_per_student_year_paise)->toBe(80000)
        ->and($growth->min_students)->toBe(250)
        ->and(planModuleKeys($growth))->toBe(['attendance', 'fees', 'whatsapp']);

    $event = PlatformAuditEvent::query()->where('action', 'platform.plan_updated')->firstOrFail();
    expect($event->actor_identity_ulid)->toBe($user->ulid)
        ->and($event->subject_ulid)->toBe($growth->ulid)
        ->and($event->before['price_per_student_year_paise'])->toBe(75000)
        ->and($event->after)->toEqualCanonicalizing([
            'price_per_student_year_paise' => 80000, 'min_students' => 250, 'modules' => ['attendance', 'fees', 'whatsapp'],
        ]);
});

it('applies module changes at once to the schools on that plan and to nobody else', function () {
    plansAdmin();
    $growth = plan('growth');

    $onGrowth = schoolOnPlan($growth, 'alpha', ['attendance', 'fees', 'homework', 'students']);
    $onStarter = schoolOnPlan(plan('starter'), 'beta', ['attendance', 'fees', 'homework']);
    $noPlan = Tenant::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'name' => 'Gamma', 'subdomain' => 'gamma',
        'database_name' => 'shiksha_t_unused_gamma', 'status' => 'active',
    ]);

    $modules = array_values(array_diff(planModuleKeys($growth), ['homework']));
    savePlan($growth, ['modules' => [...$modules, 'library']])->assertSessionHasNoErrors();

    expect(tenantModuleKeys($onGrowth))->toBe(['attendance', 'fees', 'library', 'students'])
        ->and(tenantModuleKeys($onStarter))->toBe(['attendance', 'fees', 'homework'])
        ->and(tenantModuleKeys($noPlan))->toBe([]);
});

it('allows a plan with no modules and records an unchanged save without touching anything', function () {
    plansAdmin();
    $starter = plan('starter');

    savePlan($starter, ['modules' => []])->assertSessionHasNoErrors();
    expect(planModuleKeys($starter))->toBe([]);

    savePlan(plan('premium'))->assertSessionHasNoErrors();
    expect(planModuleKeys(plan('premium')))->toHaveCount(13);
});

it('rejects invalid plan input', function (array $overrides, string $field) {
    plansAdmin();
    $growth = plan('growth');

    savePlan($growth, $overrides)->assertSessionHasErrors($field);

    expect($growth->fresh()->price_per_student_year_paise)->toBe(75000)
        ->and(PlatformAuditEvent::query()->where('action', 'platform.plan_updated')->exists())->toBeFalse();
})->with([
    'zero price' => [['price' => 0], 'price'],
    'negative price' => [['price' => -5], 'price'],
    'fractional price' => [['price' => 450.5], 'price'],
    'text price' => [['price' => 'abc'], 'price'],
    'missing price' => [['price' => null], 'price'],
    'negative minimum' => [['min_students' => -1], 'min_students'],
    'missing minimum' => [['min_students' => null], 'min_students'],
    'unknown module' => [['modules' => ['fees', 'teleportation']], 'modules.1'],
    'duplicate module' => [['modules' => ['fees', 'fees']], 'modules.0'],
    'modules not sent' => [['modules' => null], 'modules'],
]);

it('accepts zero as the minimum billable students', function () {
    plansAdmin();

    savePlan(plan('starter'), ['min_students' => 0])->assertSessionHasNoErrors();

    expect(plan('starter')->min_students)->toBe(0);
});
