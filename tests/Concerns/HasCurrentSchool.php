<?php

namespace Tests\Concerns;

use App\Domain\Identity\Models\Identity;
use App\Domain\Identity\Models\Membership;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes an active school current, as the host finder does for a real request.
 *
 * Requires the landlord database to be migrated first (RefreshDatabase). The
 * school uses one shared, real tenant database that is created and migrated
 * once; its data tables are emptied before and after every test. (A
 * transaction would not survive the connection switch a tenant change makes.)
 */
trait HasCurrentSchool
{
    public const SHARED_SCHOOL_DATABASE = 'shiksha_t_testshared';

    public Tenant $school;

    private static bool $sharedSchoolMigrated = false;

    protected function setUpHasCurrentSchool(): void
    {
        self::prepareSharedSchoolDatabase();
        self::resetSharedSchoolDatabase();

        $this->school = Tenant::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => 'Test Public School',
            'subdomain' => 'testschool',
            'database_name' => self::SHARED_SCHOOL_DATABASE,
            'status' => 'active',
        ]);

        $this->school->makeCurrent();

        $this->beforeApplicationDestroyed(fn () => self::resetSharedSchoolDatabase());
    }

    /**
     * A staff identity (password "password") with an active membership of the current school.
     */
    public function staff(bool $twoFactor = false): Identity
    {
        $factory = Identity::factory();

        $identity = ($twoFactor ? $factory->withTwoFactor() : $factory)->create();

        $this->grantStaffMembership($identity);

        return $identity;
    }

    protected function grantStaffMembership(Identity $identity, ?Tenant $school = null): Membership
    {
        return Membership::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'identity_id' => $identity->id,
            'tenant_id' => ($school ?? $this->school)->id,
            'kind' => Membership::KIND_STAFF,
        ]);
    }

    /**
     * Creates the shared tenant database if needed and brings it up to date,
     * once per test run. CREATE DATABASE cannot run inside a transaction, so it
     * uses its own short-lived connection.
     */
    private static function prepareSharedSchoolDatabase(): void
    {
        if (self::$sharedSchoolMigrated) {
            return;
        }

        $admin = DB::build([...config('database.connections.landlord'), 'database' => 'postgres']);

        $exists = $admin->table('pg_database')->where('datname', self::SHARED_SCHOOL_DATABASE)->exists();

        if (! $exists) {
            $admin->statement('CREATE DATABASE "'.self::SHARED_SCHOOL_DATABASE.'"');
        }

        $admin->disconnect();

        $shared = new Tenant(['database_name' => self::SHARED_SCHOOL_DATABASE]);
        $shared->id = -1; // an unsaved model with a null key would count as "already current"
        app(ProvisionTenant::class)->migrate($shared);

        self::$sharedSchoolMigrated = true;
    }

    /**
     * Empties the shared database's data. Audit rows are append-only, so the
     * protecting triggers are switched off for the cleanup only.
     */
    private static function resetSharedSchoolDatabase(): void
    {
        $shared = DB::build([...config('database.connections.landlord'), 'database' => self::SHARED_SCHOOL_DATABASE]);

        $shared->unprepared(<<<'SQL'
            TRUNCATE admission_documents, admission_application_events, admission_applications, student_documents, student_sibling_links, enrollments, guardian_student, students, guardians, houses,
                school_settings, number_sequences, school_class_subject, sections, subjects, school_classes, academic_years, campuses,
                role_assignments, role_permissions, roles, staff, attendance_marks, attendance_registers, school_holidays RESTART IDENTITY CASCADE;
            ALTER TABLE audit_events DISABLE TRIGGER USER;
            DELETE FROM audit_events;
            ALTER TABLE audit_events ENABLE TRIGGER USER;
        SQL);

        $shared->disconnect();
    }
}
