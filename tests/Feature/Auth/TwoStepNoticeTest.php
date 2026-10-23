<?php

use App\Domain\Access\Actions\SetUpSchoolOwner;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\HasCurrentSchool;

uses(RefreshDatabase::class, HasCurrentSchool::class);

it('tells an owner without two-step why they are stuck, and stops once it is on', function () {
    $this->seed(AccessCatalogSeeder::class);

    $owner = test()->staff();
    app(SetUpSchoolOwner::class)->handle($this->school, $owner, 'Meera Nair');

    $this->actingAs($owner)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.two_step_required', true));

    $withTwoStep = test()->staff(twoFactor: true);
    app(SetUpSchoolOwner::class)->handle($this->school, $withTwoStep, 'Arjun Mehta');

    $this->actingAs($withTwoStep)
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.two_step_required', false));
});

it('does not show the notice to staff whose role does not need two-step', function () {
    $this->actingAs(test()->staff())
        ->get(route('password.confirm'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.two_step_required', false));
});
