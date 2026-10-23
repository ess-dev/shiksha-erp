<?php

namespace Database\Seeders;

use App\Domain\Access\Actions\CreateStaffRecord;
use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Actions\SaveRegister;
use App\Domain\Attendance\Enums\AttendanceStatus;
use App\Domain\Attendance\Models\AttendanceRegister;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\SetClassTeacher;
use App\Domain\School\Models\Section;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional attendance for Greenfield, for local development only: a class
 * teacher (Neha Verma) for Class 1 A at the Main campus, and registers for the
 * last few school days, saved through the real Action by the school's demo
 * principal. The password is a public demo value; the seeder refuses to run in
 * production. Safe to re-run (a class that already has registers is left alone).
 * illustrative — verify: names and the absence pattern are placeholders.
 */
class DemoAttendanceSeeder extends Seeder
{
    private const EMAIL = 'teacher@greenfield.test';

    private const SCHOOL_DAYS = 6;

    public function run(
        CreateIdentity $createIdentity,
        GrantMembership $grantMembership,
        CreateStaffRecord $createStaff,
        StaffRefQuery $staffRefs,
        SetClassTeacher $setClassTeacher,
        SectionDirectoryQuery $sections,
        SchoolDayQuery $days,
        RosterQuery $roster,
        SaveRegister $saveRegister,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo staff accounts must never be seeded in production.');
        }

        $tenant = Tenant::query()->where('subdomain', 'greenfield')->first();
        $principal = Identity::query()->where('email', 'principal@greenfield.test')->first();

        if ($tenant === null || $principal === null) {
            return;
        }

        $teacher = Identity::query()->where('email', self::EMAIL)->first() ?? $createIdentity->handle(self::EMAIL, '9876500004', 'password');
        $grantMembership->handle($teacher, $tenant);

        $tenant->execute(function () use ($teacher, $principal, $createStaff, $staffRefs, $setClassTeacher, $sections, $days, $roster, $saveRegister) {
            $ref = collect($sections->currentYear(1))->first(fn ($section) => $section->name === 'A' && $section->campusName === 'Main Campus');

            if ($ref === null) {
                return;
            }

            $section = Section::query()->where('ulid', $ref->ulid)->firstOrFail();
            $staffUlid = $staffRefs->forIdentity($teacher)?->ulid ?? $createStaff->handle($teacher->ulid, 'Neha Verma')->ulid;
            $setClassTeacher->handle($principal, $section, $staffUlid);

            if (AttendanceRegister::query()->where('section_id', $section->id)->exists()) {
                return;
            }

            $date = new DateTimeImmutable(CalendarDate::today());
            $saved = 0;

            for ($back = 1; $saved < self::SCHOOL_DAYS && $back < 40; $back++) {
                $day = $date->modify("-{$back} days")->format('Y-m-d');

                if (! $days->check($day)->isSchoolDay) {
                    continue;
                }

                $marks = [];

                foreach ($roster->for($ref->sectionKey, $day) as $position => $student) {
                    $marks[$student->ulid] = match (true) {
                        ($saved * 7 + $position * 3) % 11 === 0 => AttendanceStatus::Absent,
                        ($saved * 5 + $position * 2) % 13 === 0 => AttendanceStatus::Late,
                        default => AttendanceStatus::Present,
                    };
                }

                if ($marks !== []) {
                    $saveRegister->handle($principal, $ref->ulid, $day, $marks, 'Demo data');
                }

                $saved++;
            }
        });
    }
}
