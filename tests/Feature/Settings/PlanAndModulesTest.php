<?php

use App\Domain\Platform\Models\Plan;
use App\Domain\Platform\Models\PlanModule;
use App\Domain\Platform\Models\PlatformModule;
use App\Tenancy\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\HasCurrentSchool;
use Tests\Concerns\ManagesSchoolPlan;

uses(RefreshDatabase::class, HasCurrentSchool::class, BuildsStudents::class, ManagesSchoolPlan::class);

beforeEach(function () {
    $this->setUpStudentSchool();

    foreach (['fees' => 'Fees', 'homework' => 'Homework', 'transport' => 'Transport'] as $key => $label) {
        PlatformModule::query()->updateOrCreate(['key' => $key], ['label' => $label, 'sort_order' => 90]);
    }

    $plan = Plan::query()->create([
        'ulid' => strtolower((string) Str::ulid()), 'key' => 'growth', 'name' => 'Growth', 'description' => '',
        'price_per_student_year_paise' => 75000, 'min_students' => 100, 'sort_order' => 1,
    ]);
    PlanModule::query()->create(['plan_id' => $plan->id, 'module_key' => 'fees']);
    PlanModule::query()->create(['plan_id' => $plan->id, 'module_key' => 'transport']);

    $this->school->forceFill(['plan_id' => $plan->id, 'status' => 'active', 'renews_on' => '2027-03-31', 'student_count' => 412])->save();
    $this->enableModules(['fees', 'homework']);
    $this->disableModule('transport');
});

it('shows the owner the school plan and where each module stands', function () {
    $this->actingAs($this->owner)->get(route('plan.show'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/plan')
            ->where('plan.plan_name', 'Growth')
            ->where('plan.status', 'active')
            ->where('plan.renews_on', '2027-03-31')
            ->where('plan.students', 412)
            ->where('plan.plan_min_students', 100)
            ->where('plan.modules', fn ($modules) => collect($modules)->pluck('state', 'key')->only(['fees', 'homework', 'transport'])->all() === [
                'fees' => 'in_plan', 'homework' => 'add_on', 'transport' => 'turned_off',
            ])
            ->missing('plan.id'));
});

it('lists a module outside the plan as not in plan and never lists core', function () {
    $this->school->forceFill(['plan_id' => null])->save();

    $this->actingAs($this->owner)->get(route('plan.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('plan.plan_name', null)
            ->where('plan.modules', fn ($modules) => collect($modules)->pluck('state', 'key')->all()['fees'] === 'add_on'
                && ! collect($modules)->pluck('key')->contains('core')));
});

it('refuses anyone who is not the owner', function () {
    $this->actingAs($this->staffWith(['settings.manage', 'students.view']))->get(route('plan.show'))->assertForbidden();
});

it('requires sign-in', function () {
    $this->get(route('plan.show'))->assertRedirect(route('login'));
});

it('is blocked while the school is suspended', function () {
    $this->setSchoolStatus(TenantStatus::Suspended);

    $this->actingAs($this->owner)->get(route('plan.show'))->assertForbidden();
});
