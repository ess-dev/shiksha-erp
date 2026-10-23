<?php

use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Platform\Actions\PurgeSchool;
use App\Domain\Platform\Jobs\PurgeSchoolData;
use App\Domain\Platform\Models\PlatformUser;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use Database\Seeders\PlanCatalogSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// DROP DATABASE cannot run inside the transaction RefreshDatabase wraps tests in.
uses(DatabaseMigrations::class);

beforeEach(function () {
    url()->forceRootUrl('http://'.config('shiksha.platform_host'));
    $this->seed(PlanCatalogSeeder::class);

    $user = PlatformUser::factory()->withTwoFactor()->create();
    $this->withSession(['platform.two_step_passed_for' => $user->ulid])->actingAs($user, 'platform');
});

function deletableSchool(string $name): Tenant
{
    return app(ProvisionTenant::class)->handle($name, Str::slug($name), 'active');
}

function databaseExists(Tenant $tenant): bool
{
    return DB::connection('landlord')->table('pg_database')->where('datname', $tenant->database_name)->exists();
}

function landlordRows(string $table, Tenant $tenant): int
{
    return DB::connection('landlord')->table($table)->where('tenant_id', $tenant->id)->count();
}

it('deletes a school with its landlord rows, identities and files once the name is typed', function () {
    Storage::fake('tenant');
    $school = deletableSchool('Alpha School');
    $other = deletableSchool('Beta School');
    $landlord = DB::connection('landlord');

    $landlord->table('tenant_modules')->insert(['tenant_id' => $school->id, 'module' => 'fees']);
    $landlord->table('tenant_domains')->insert(['tenant_id' => $school->id, 'domain' => 'alpha.example.org']);

    $identity = fn (string $email) => $landlord->table('identities')->insertGetId([
        'ulid' => strtolower((string) Str::ulid()), 'email' => $email, 'status' => 'active',
    ]);
    $onlyAlpha = $identity('a@example.org');
    $both = $identity('b@example.org');
    $membership = fn (int $identityId, Tenant $tenant) => $landlord->table('memberships')->insert([
        'ulid' => strtolower((string) Str::ulid()), 'identity_id' => $identityId, 'tenant_id' => $tenant->id, 'kind' => 'staff',
    ]);
    $membership($onlyAlpha, $school);
    $membership($both, $school);
    $membership($both, $other);

    expect(databaseExists($school))->toBeTrue();

    Storage::disk('tenant')->put("tenants/{$school->ulid}/students/x.pdf", 'x');
    Storage::disk('tenant')->put("tenants/{$other->ulid}/students/y.pdf", 'y');

    $this->delete(route('platform.schools.destroy', $school), ['confirmation' => 'delete Alpha School'])
        ->assertRedirect(route('platform.schools.index'));

    expect(Tenant::query()->whereKey($school->id)->exists())->toBeFalse()
        ->and(databaseExists($school))->toBeFalse()
        ->and(databaseExists($other))->toBeTrue()
        ->and(landlordRows('memberships', $school))->toBe(0)
        ->and(landlordRows('tenant_modules', $school))->toBe(0)
        ->and(landlordRows('tenant_domains', $school))->toBe(0)
        ->and($landlord->table('identities')->where('id', $onlyAlpha)->exists())->toBeFalse()
        ->and($landlord->table('identities')->where('id', $both)->exists())->toBeTrue()
        ->and(Storage::disk('tenant')->exists("tenants/{$school->ulid}/students/x.pdf"))->toBeFalse()
        ->and(Storage::disk('tenant')->exists("tenants/{$other->ulid}/students/y.pdf"))->toBeTrue()
        ->and(Tenant::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(PlatformAuditEvent::query()->where('action', 'platform.school_deleted')->where('subject_ulid', $school->ulid)->exists())->toBeTrue();
});

it('keeps the school when the confirmation text is wrong', function (string $typed) {
    $school = deletableSchool('Alpha School');

    $this->delete(route('platform.schools.destroy', $school), ['confirmation' => $typed])->assertRedirect();

    expect(Tenant::query()->whereKey($school->id)->exists())->toBeTrue()
        ->and(databaseExists($school))->toBeTrue()
        ->and(PlatformAuditEvent::query()->where('action', 'platform.school_deleted')->exists())->toBeFalse();
})->with(['', 'Alpha School', 'delete alpha school', 'delete Beta School']);

it('blocks the school at once, keeps it visible while the purge is pending and can retry', function () {
    Queue::fake();
    $school = deletableSchool('Alpha School');

    $this->delete(route('platform.schools.destroy', $school), ['confirmation' => 'delete Alpha School'])->assertRedirect();

    $school->refresh();
    expect($school->status)->toBe(TenantStatus::Deleting)
        ->and($school->isAccessible())->toBeFalse()
        ->and(databaseExists($school))->toBeTrue()
        ->and(PlatformAuditEvent::query()->where('action', 'platform.school_deletion_started')->exists())->toBeTrue();
    Queue::assertPushed(PurgeSchoolData::class, 1);

    $this->post(route('platform.schools.retry-deletion', $school))->assertRedirect();

    Queue::assertPushed(PurgeSchoolData::class, 2);
    expect(PlatformAuditEvent::query()->where('action', 'platform.school_deletion_retried')->exists())->toBeTrue();

    (new PurgeSchoolData($school->id))->handle(app(PurgeSchool::class));

    expect(Tenant::query()->whereKey($school->id)->exists())->toBeFalse()
        ->and(databaseExists($school))->toBeFalse();
});

it('refuses to retry a school that is not being deleted', function () {
    Queue::fake();
    $school = deletableSchool('Alpha School');

    $this->post(route('platform.schools.retry-deletion', $school))->assertRedirect();

    Queue::assertNothingPushed();
    expect(PlatformAuditEvent::query()->where('action', 'platform.school_deletion_retried')->exists())->toBeFalse();
});
