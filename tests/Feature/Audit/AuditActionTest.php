<?php

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Audit\Exceptions\MissingAuditEvent;
use App\Domain\Audit\Models\AuditEvent;
use App\Domain\Audit\Models\PlatformAuditEvent;
use App\Domain\Shared\Action;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

afterEach(function () {
    Tenant::forgetCurrent();

    foreach (Tenant::query()->pluck('database_name') as $database) {
        DB::connection('landlord')->statement('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ?', [$database]);
        DB::connection('landlord')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
    }
});

/**
 * A school with a scratch data table, current for the test.
 */
function schoolWithProbeTable(string $subdomain = 'auditschool'): Tenant
{
    $tenant = app(ProvisionTenant::class)->handle('Audit School', $subdomain);

    $tenant->execute(fn () => DB::connection('tenant')->statement('CREATE TABLE probe (name text)'));
    $tenant->makeCurrent();

    return $tenant;
}

function probeAction(Closure $work): Action
{
    return new class($work) extends Action
    {
        public function __construct(private readonly Closure $work) {}

        public function handle(): mixed
        {
            return $this->transaction(fn () => ($this->work)(fn (AuditEntry $entry) => $this->audit($entry)));
        }
    };
}

it('writes the change and its audit event together', function () {
    schoolWithProbeTable();

    probeAction(function (Closure $audit) {
        DB::connection('tenant')->table('probe')->insert(['name' => 'first']);
        $audit(new AuditEntry('probe.created', 'probe', '01hprobe', null, ['name' => 'first']));
    })->handle();

    expect(DB::connection('tenant')->table('probe')->count())->toBe(1);

    $event = AuditEvent::query()->sole();

    expect($event->action)->toBe('probe.created')
        ->and($event->subject_type)->toBe('probe')
        ->and($event->subject_ulid)->toBe('01hprobe')
        ->and($event->after)->toBe(['name' => 'first'])
        ->and($event->actor_type)->toBe('system');
});

it('rolls back the change and the audit event when the action fails', function () {
    schoolWithProbeTable();

    expect(fn () => probeAction(function (Closure $audit) {
        DB::connection('tenant')->table('probe')->insert(['name' => 'lost']);
        $audit(new AuditEntry('probe.created', 'probe'));

        throw new RuntimeException('boom');
    })->handle())->toThrow(RuntimeException::class);

    expect(DB::connection('tenant')->table('probe')->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe(0);
});

it('refuses an action that writes without an audit event', function () {
    schoolWithProbeTable();

    expect(fn () => probeAction(function () {
        DB::connection('tenant')->table('probe')->insert(['name' => 'unaudited']);
    })->handle())->toThrow(MissingAuditEvent::class);

    expect(DB::connection('tenant')->table('probe')->count())->toBe(0);
});

it('keeps audit events append-only in the database', function () {
    schoolWithProbeTable();

    probeAction(fn (Closure $audit) => $audit(new AuditEntry('probe.created', 'probe')))->handle();

    $connection = DB::connection('tenant');

    expect(fn () => $connection->statement('UPDATE audit_events SET action = \'tampered\''))->toThrow(Exception::class, 'append-only')
        ->and(fn () => $connection->statement('DELETE FROM audit_events'))->toThrow(Exception::class, 'append-only')
        ->and(fn () => $connection->statement('TRUNCATE audit_events'))->toThrow(Exception::class, 'append-only');

    expect(AuditEvent::query()->sole()->action)->toBe('probe.created');
});

it('refuses to update or delete an audit event through the model', function () {
    schoolWithProbeTable();

    probeAction(fn (Closure $audit) => $audit(new AuditEntry('probe.created', 'probe')))->handle();

    $event = AuditEvent::query()->sole();

    expect(fn () => $event->update(['action' => 'tampered']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('keeps one school\'s audit events out of another school', function () {
    schoolWithProbeTable('school-a');

    probeAction(fn (Closure $audit) => $audit(new AuditEntry('probe.created', 'probe')))->handle();

    expect(AuditEvent::query()->count())->toBe(1);

    $other = app(ProvisionTenant::class)->handle('School B', 'school-b');
    $other->makeCurrent();

    expect(AuditEvent::query()->count())->toBe(0);
});

it('writes platform-scope actions to the landlord audit table', function () {
    $tenant = app(ProvisionTenant::class)->handle('Platform School', 'platformschool');

    $action = new class extends Action
    {
        protected AuditScope $auditScope = AuditScope::Platform;

        public function handle(): void
        {
            $this->transaction(fn () => $this->audit(new AuditEntry('platform.support_access_started', 'tenant', 'ulid-1')));
        }
    };

    $action->handle();

    expect(PlatformAuditEvent::query()->where('action', 'platform.support_access_started')->count())->toBe(1);
    expect($tenant->exists)->toBeTrue();
});

it('audits provisioning in the platform log with the school', function () {
    $tenant = app(ProvisionTenant::class)->handle('Logged School', 'loggedschool');

    $event = PlatformAuditEvent::query()->where('action', 'platform.tenant_provisioned')->sole();

    expect($event->tenant_id)->toBe($tenant->id)
        ->and($event->subject_ulid)->toBe($tenant->ulid)
        ->and($event->after['subdomain'])->toBe('loggedschool');
});
