<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Platform\Actions\SetSchoolModule;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
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

function detailAdmin(): PlatformUser
{
    $user = PlatformUser::factory()->withTwoFactor()->create();

    test()->withSession(['platform.two_step_passed_for' => $user->ulid])->actingAs($user, 'platform');

    return $user;
}

function detailPlan(string $key): Plan
{
    return Plan::query()->where('key', $key)->firstOrFail();
}

function detailSchool(string $name, array $attributes = [], ?Plan $plan = null): Tenant
{
    $slug = Str::slug($name);

    $tenant = Tenant::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'name' => $name, 'subdomain' => $slug,
        'database_name' => "shiksha_t_unused_{$slug}", 'status' => 'active',
        'city' => 'Ahmedabad', 'state' => 'Gujarat', 'board' => 'GSEB', 'student_count' => 200,
        ...$attributes,
    ]);
    $tenant->forceFill(['plan_id' => $plan?->id])->save();

    return $tenant;
}

function detailEnabled(Tenant $tenant): array
{
    return DB::connection('landlord')->table('tenant_modules')
        ->where('tenant_id', $tenant->id)->pluck('module')->sort()->values()->all();
}

function detailEnable(Tenant $tenant, array $modules): void
{
    foreach ($modules as $module) {
        DB::connection('landlord')->table('tenant_modules')->insert([
            'tenant_id' => $tenant->id, 'module' => $module, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

it('requires a signed-in Super Admin and a known school', function () {
    $school = detailSchool('Alpha School');

    $this->get(route('platform.schools.show', $school))->assertRedirect();

    detailAdmin();

    $this->get(route('platform.schools.show', 'no-such-school'))->assertNotFound();
});

it('shows a school with its plan figures, module states and recent actions', function () {
    detailAdmin();
    $starter = detailPlan('starter');
    $school = detailSchool('Anand Bal Vidyalaya', ['student_count' => 470, 'contact_name' => 'Ms. Hetal Shah'], $starter);
    // attendance and fees are in the plan and on; communication is in the plan but off;
    // homework is on although the plan does not include it.
    detailEnable($school, ['attendance', 'fees', 'homework']);
    app(SetSchoolModule::class)->handle($school, 'exams', true);

    $this->get(route('platform.schools.show', $school))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($starter) {
            $page->component('platform/school-detail')
                ->where('school.name', 'Anand Bal Vidyalaya')
                ->where('school.plan_ulid', $starter->ulid)
                ->where('school.billable_students', 470)
                // 470 students x 45,000 paise a year, and a twelfth of it
                ->where('school.annual_paise', 21150000)
                ->where('school.mrr_paise', 1762500)
                ->where('school.contact_name', 'Ms. Hetal Shah')
                ->where('school.activity.0.summary', 'module on · Exams & report cards')
                ->missing('school.id');

            $badges = collect($page->toArray()['props']['school']['modules'])->pluck('badge', 'key');

            expect($badges['attendance'])->toBe('in_plan')
                ->and($badges['communication'])->toBe('turned_off')
                ->and($badges['homework'])->toBe('add_on')
                ->and($badges['library'])->toBeNull();
        });
});

it('bills a small school on the plan minimum', function () {
    detailAdmin();
    $school = detailSchool('Tiny School', ['student_count' => 40], detailPlan('starter'));

    $this->get(route('platform.schools.show', $school))
        ->assertInertia(fn (Assert $page) => $page
            ->where('school.billable_students', 100)
            ->where('school.annual_paise', 4500000));
});

it('suspends a school with a reason, records it and blocks access', function () {
    detailAdmin();
    $school = detailSchool('Alpha School');

    $this->post(route('platform.schools.suspend', $school), ['reason' => 'invoice_overdue'])->assertRedirect();

    $school->refresh();
    $event = PlatformAuditEvent::query()->where('action', 'platform.school_suspended')->firstOrFail();

    expect($school->status)->toBe(TenantStatus::Suspended)
        ->and($school->suspended_reason)->toBe('invoice_overdue')
        ->and($school->isAccessible())->toBeFalse()
        ->and($event->tenant_id)->toBe($school->id)
        ->and($event->before['status'])->toBe('active')
        ->and($event->after['reason'])->toBe('invoice_overdue');

    $this->get(route('platform.schools.show', $school))
        ->assertInertia(fn (Assert $page) => $page
            ->where('school.status', 'suspended')
            ->where('school.suspended_reason', 'Invoice overdue by 60 days'));
});

it('rejects a missing or unknown suspension reason', function () {
    detailAdmin();
    $school = detailSchool('Alpha School');

    $this->post(route('platform.schools.suspend', $school), [])->assertSessionHasErrors('reason');
    $this->post(route('platform.schools.suspend', $school), ['reason' => 'because'])->assertSessionHasErrors('reason');

    expect($school->refresh()->status)->toBe(TenantStatus::Active)
        ->and(PlatformAuditEvent::query()->count())->toBe(0);
});

it('does not suspend a school that is already suspended or expired', function (string $status) {
    detailAdmin();
    $school = detailSchool('Alpha School', ['status' => $status]);

    $this->post(route('platform.schools.suspend', $school), ['reason' => 'terms_review'])->assertRedirect();

    expect($school->refresh()->status->value)->toBe($status)
        ->and(PlatformAuditEvent::query()->count())->toBe(0);
})->with(['suspended', 'expired']);

it('reactivates a suspended school', function () {
    detailAdmin();
    $school = detailSchool('Alpha School', ['status' => 'suspended', 'suspended_reason' => 'terms_review']);

    $this->post(route('platform.schools.reactivate', $school))->assertRedirect();

    $school->refresh();

    expect($school->status)->toBe(TenantStatus::Active)
        ->and($school->suspended_reason)->toBeNull()
        ->and(PlatformAuditEvent::query()->where('action', 'platform.school_reactivated')->where('tenant_id', $school->id)->exists())->toBeTrue();
});

it('returns a suspended school to trial while its trial has not ended', function () {
    detailAdmin();
    $running = detailSchool('Alpha School', ['status' => 'suspended', 'trial_ends_on' => '2999-01-01']);
    $ended = detailSchool('Beta School', ['status' => 'suspended', 'trial_ends_on' => '2000-01-01']);

    $this->post(route('platform.schools.reactivate', $running));
    $this->post(route('platform.schools.reactivate', $ended));

    expect($running->refresh()->status)->toBe(TenantStatus::Trial)
        ->and($ended->refresh()->status)->toBe(TenantStatus::Active);
});

it('does not reactivate a school that is not suspended', function () {
    detailAdmin();
    $school = detailSchool('Alpha School');

    $this->post(route('platform.schools.reactivate', $school))->assertRedirect();

    expect(PlatformAuditEvent::query()->count())->toBe(0);
});

it('moves a school to another plan and resets its modules to the plan', function () {
    detailAdmin();
    $growth = detailPlan('growth');
    $school = detailSchool('Alpha School', [], detailPlan('starter'));
    // Starter modules, a hand-added add-on, and a key that is not a sellable module.
    detailEnable($school, ['attendance', 'fees', 'communication', 'library', 'students']);

    $this->patch(route('platform.schools.plan', $school), ['plan' => $growth->ulid])->assertRedirect();

    $expected = collect($growth->planModules()->pluck('module_key'))->push('students')->sort()->values()->all();

    expect($school->refresh()->plan_id)->toBe($growth->id)
        ->and(detailEnabled($school))->toBe($expected);

    $event = PlatformAuditEvent::query()->where('action', 'platform.school_plan_changed')->firstOrFail();

    expect($event->before['plan'])->toBe('starter')->and($event->after['plan'])->toBe('growth');
});

it('rejects an unknown plan', function () {
    detailAdmin();
    $school = detailSchool('Alpha School', [], detailPlan('starter'));

    $this->patch(route('platform.schools.plan', $school), ['plan' => 'nope'])->assertSessionHasErrors('plan');
});

it('switches a module on and off for one school only', function () {
    detailAdmin();
    $school = detailSchool('Alpha School', [], detailPlan('starter'));
    $other = detailSchool('Beta School', [], detailPlan('starter'));

    $this->patch(route('platform.schools.modules', $school), ['module' => 'library', 'enabled' => true])->assertRedirect();

    expect(detailEnabled($school))->toBe(['library'])->and(detailEnabled($other))->toBe([]);

    $this->patch(route('platform.schools.modules', $school), ['module' => 'library', 'enabled' => false]);

    expect(detailEnabled($school))->toBe([])
        ->and(PlatformAuditEvent::query()->whereIn('action', ['platform.school_module_enabled', 'platform.school_module_disabled'])->count())->toBe(2);
});

it('rejects a module that is not sellable', function () {
    detailAdmin();
    $school = detailSchool('Alpha School', [], detailPlan('starter'));

    $this->patch(route('platform.schools.modules', $school), ['module' => 'students', 'enabled' => true])->assertSessionHasErrors('module');
    $this->patch(route('platform.schools.modules', $school), ['module' => 'library'])->assertSessionHasErrors('enabled');

    expect(detailEnabled($school))->toBe([]);
});

it('keeps every school change behind sign-in', function () {
    $school = detailSchool('Alpha School');

    $this->post(route('platform.schools.suspend', $school), ['reason' => 'terms_review'])->assertRedirect();
    $this->post(route('platform.schools.reactivate', $school))->assertRedirect();
    $this->patch(route('platform.schools.plan', $school), ['plan' => 'x'])->assertRedirect();
    $this->patch(route('platform.schools.modules', $school), ['module' => 'library', 'enabled' => true])->assertRedirect();

    expect($school->refresh()->status)->toBe(TenantStatus::Active);
});
