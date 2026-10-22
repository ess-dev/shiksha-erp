<?php

use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Access\Authorizer;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\Staff;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(DatabaseMigrations::class);

afterEach(function () {
    Tenant::forgetCurrent();

    foreach (Tenant::query()->pluck('database_name') as $database) {
        DB::connection('landlord')->statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$database]);
        DB::connection('landlord')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
    }
});

it('keeps one school\'s roles, staff and owner out of another school', function () {
    $this->seed(AccessCatalogSeeder::class);

    $a = app(ProvisionTenant::class)->handle('School A', 'school-a');
    $b = app(ProvisionTenant::class)->handle('School B', 'school-b');

    $owner = app(CreateIdentity::class)->handle('owner@example.com', null, 'password');
    app(SetUpSchoolOwner::class)->handle($a, $owner, 'Owner A');

    $a->execute(function () use ($owner) {
        expect(Role::query()->count())->toBe(1)
            ->and(Staff::query()->count())->toBe(1)
            ->and(Gate::forUser($owner)->allows('roles.manage'))->toBeTrue();
    });

    $b->execute(function () use ($owner) {
        app(Authorizer::class)->flush();

        expect(Role::query()->count())->toBe(0)
            ->and(Staff::query()->count())->toBe(0)
            ->and(Gate::forUser($owner)->allows('roles.manage'))->toBeFalse();
    });
});
