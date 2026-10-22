<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlatformUser;
use App\Domain\Platform\MonthlyRevenue;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    url()->forceRootUrl('http://'.config('shiksha.platform_host'));
});

function schoolsAdmin(): PlatformUser
{
    $user = PlatformUser::factory()->withTwoFactor()->create();

    test()->withSession(['platform.two_step_passed_for' => $user->ulid])->actingAs($user, 'platform');

    return $user;
}

function directoryPlan(string $key = 'growth', int $pricePaise = 75000, int $minStudents = 100): Plan
{
    return Plan::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'key' => $key, 'name' => ucfirst($key), 'description' => '',
        'price_per_student_year_paise' => $pricePaise, 'min_students' => $minStudents, 'sort_order' => 1,
    ]);
}

function directorySchool(string $name, array $attributes = [], ?Plan $plan = null): Tenant
{
    $slug = Str::slug($name);

    $tenant = Tenant::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'name' => $name, 'subdomain' => $slug,
        'database_name' => "shiksha_t_unused_{$slug}", 'status' => 'active',
        'city' => 'Mohali', 'state' => 'Punjab', 'board' => 'CBSE', 'student_count' => 200,
        ...$attributes,
    ]);
    $tenant->forceFill(['plan_id' => $plan?->id])->save();

    return $tenant;
}

it('requires a signed-in Super Admin', function () {
    $this->get(route('platform.schools.index'))->assertRedirect();
    $this->get(route('platform.schools.export'))->assertRedirect();
});

it('is not served on a school host', function () {
    schoolsAdmin();
    url()->forceRootUrl('http://greenfield.'.config('shiksha.base_domain', 'shiksha.localhost'));

    $this->get('/schools')->assertNotFound();
});

it('lists schools by name with plan, students and monthly revenue', function () {
    schoolsAdmin();
    $plan = directoryPlan();
    directorySchool('Zenith School', plan: $plan);
    directorySchool('Anand Bal Vidyalaya', ['student_count' => 470, 'renews_on' => '2026-12-09'], $plan);

    $this->get(route('platform.schools.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('platform/schools')
            ->where('schools.total_schools', 2)
            ->where('schools.total_students', 670)
            ->where('schools.rows.0.name', 'Anand Bal Vidyalaya')
            ->where('schools.rows.0.plan_name', 'Growth')
            ->where('schools.rows.0.students', 470)
            // 470 students x 75,000 paise a year / 12, half-up.
            ->where('schools.rows.0.mrr_paise', 2937500)
            ->where('schools.rows.0.renews_on', '2026-12-09')
            ->where('schools.rows.1.name', 'Zenith School')
            ->missing('schools.rows.0.id'));
});

it('counts every status regardless of the other filters', function () {
    schoolsAdmin();
    directorySchool('Alpha School', ['status' => 'active']);
    directorySchool('Beta School', ['status' => 'trial']);
    directorySchool('Gamma School', ['status' => 'trial']);

    $this->get(route('platform.schools.index', ['status' => 'trial', 'q' => 'Beta']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('schools.matching', 1)
            ->where('schools.status_counts.all', 3)
            ->where('schools.status_counts.active', 1)
            ->where('schools.status_counts.trial', 2)
            ->where('schools.status_counts.suspended', 0)
            ->where('filters.status', 'trial'));
});

it('filters by status, plan and search', function () {
    schoolsAdmin();
    $growth = directoryPlan('growth');
    $starter = directoryPlan('starter');
    directorySchool('Alpha School', ['city' => 'Pune', 'status' => 'suspended'], $growth);
    directorySchool('Beta School', ['city' => 'Lucknow', 'contact_name' => 'Ravi Sharma'], $starter);
    directorySchool('Gamma Academy', ['city' => 'Lucknow'], $starter);

    $names = fn (array $query) => collect($this->get(route('platform.schools.index', $query))->viewData('page')['props']['schools']['rows'])->pluck('name')->all();

    expect($names(['status' => 'suspended']))->toBe(['Alpha School'])
        ->and($names(['plan' => $starter->ulid]))->toBe(['Beta School', 'Gamma Academy'])
        ->and($names(['q' => 'lucknow']))->toBe(['Beta School', 'Gamma Academy'])
        ->and($names(['q' => 'sharma']))->toBe(['Beta School'])
        ->and($names(['q' => 'school', 'plan' => $starter->ulid]))->toBe(['Beta School'])
        ->and($names(['plan' => 'no-such-plan']))->toBe([]);
});

it('treats percent and underscore in the search as plain text', function () {
    schoolsAdmin();
    directorySchool('Alpha School');

    $this->get(route('platform.schools.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('schools.matching', 0));
});

it('rejects an unknown status filter', function () {
    schoolsAdmin();

    $this->get(route('platform.schools.index', ['status' => 'bogus']))->assertSessionHasErrors('status');
});

it('pages through a long list', function () {
    schoolsAdmin();

    foreach (range(1, 27) as $number) {
        directorySchool(sprintf('School %02d', $number));
    }

    $this->get(route('platform.schools.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('schools.page', 2)
            ->where('schools.last_page', 2)
            ->where('schools.matching', 27)
            ->has('schools.rows', 2)
            ->where('schools.rows.0.name', 'School 26'));
});

it('exports the filtered list as CSV and audits it', function () {
    $admin = schoolsAdmin();
    $plan = directoryPlan();
    directorySchool('Alpha School', ['contact_name' => '=HYPERLINK("http://x")', 'contact_email' => 'a@alpha.test', 'student_count' => 470], $plan);
    directorySchool('Beta School', ['status' => 'trial']);

    $response = $this->get(route('platform.schools.export', ['status' => 'active']))->assertOk();
    $csv = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toContain('Alpha School')
        ->and($csv)->not->toContain('Beta School')
        ->and($csv)->toContain('29375.00')
        ->and($csv)->toContain("'=HYPERLINK");

    $event = PlatformAuditEvent::query()->where('action', 'platform.schools_exported')->firstOrFail();

    expect($event->after['schools'])->toBe(1)
        ->and($event->after['status'])->toBe('active');
});

it('computes monthly revenue only for active schools on a plan', function (?int $price, TenantStatus $status, int $students, int $min, int $expected) {
    $plan = $price === null ? null : new Plan(['price_per_student_year_paise' => $price, 'min_students' => $min]);

    expect(MonthlyRevenue::paise($plan, $status, $students))->toBe($expected);
})->with([
    'exact' => [75000, TenantStatus::Active, 470, 100, 2937500],
    'billed on the plan minimum' => [75000, TenantStatus::Active, 50, 100, 625000],
    'half-up at .5' => [1002, TenantStatus::Active, 1, 0, 84],
    'rounds down below .5' => [1001, TenantStatus::Active, 1, 0, 83],
    'trial earns nothing' => [75000, TenantStatus::Trial, 470, 100, 0],
    'suspended earns nothing' => [75000, TenantStatus::Suspended, 470, 100, 0],
    'no plan earns nothing' => [null, TenantStatus::Active, 470, 100, 0],
]);
