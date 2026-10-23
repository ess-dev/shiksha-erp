<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\ApplicationHistory;
use App\Domain\Admissions\Data\NewEnquiry;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddEnquiry extends Action
{
    public function __construct(
        private readonly AdmissionGuard $guard,
        private readonly SectionDirectoryQuery $sections,
        private readonly ApplicationHistory $history,
    ) {}

    /**
     * Records a new enquiry at the Enquiry stage, for the current academic
     * year. The campus must run a section of the class. Only what the front
     * desk knows is asked; the rest is collected at the Application stage.
     * Needs `admissions.manage` over the campus and class.
     */
    public function handle(Identity $actor, NewEnquiry $input): AdmissionApplication
    {
        $year = $this->sections->currentYearRef() ?? throw AdmissionException::noCurrentYear();

        $place = $this->placeOf($input->campusUlid, $input->classUlid);

        if ($place === null) {
            $names = DB::connection(config('multitenancy.tenant_database_connection_name'));
            $campus = (string) $names->table('campuses')->where('ulid', $input->campusUlid)->value('name');
            $class = (string) $names->table('school_classes')->where('ulid', $input->classUlid)->value('name');

            throw AdmissionException::classNotAtCampus($campus !== '' ? $campus : 'That campus', $class !== '' ? $class : 'that class');
        }

        $this->guard->assertMayAt($actor, 'admissions.manage', $place->scopeTarget());

        $phone = PhoneNumber::tryParse($input->phone)->e164 ?? throw AdmissionException::invalidPhone();

        if ($input->bornOn !== null && $input->bornOn !== '') {
            $this->assertBornOn($input->bornOn);
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $classId = (int) $connection->table('school_classes')->where('ulid', $input->classUlid)->value('id');
        $campusId = (int) $connection->table('campuses')->where('ulid', $input->campusUlid)->value('id');

        return $this->transaction(function () use ($actor, $input, $phone, $year, $classId, $campusId) {
            $application = AdmissionApplication::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'first_name' => trim($input->firstName),
                'last_name' => trim($input->lastName),
                'gender' => $input->gender,
                'born_on' => $input->bornOn !== null && $input->bornOn !== '' ? CalendarDate::parse($input->bornOn) : null,
                'school_class_id' => $classId,
                'campus_id' => $campusId,
                'academic_year_id' => $year['key'],
                'parent_name' => trim($input->parentName),
                'parent_relation' => trim($input->parentRelation),
                'phone' => $phone,
                'source' => $input->source,
                'notes' => $input->notes !== null && trim($input->notes) !== '' ? trim($input->notes) : null,
                'stage' => AdmissionStage::Enquiry,
                'enquired_on' => CalendarDate::today(),
                'created_by_identity_ulid' => $actor->ulid,
            ]);

            $this->history->record($application, AdmissionStage::Enquiry, $actor);

            $this->audit(new AuditEntry('admissions.enquiry_added', 'admission_application', $application->ulid, null, [
                'class' => $input->classUlid,
                'campus' => $input->campusUlid,
                'source' => $input->source->value,
            ]));

            return $application;
        });
    }

    private function placeOf(string $campusUlid, string $classUlid): ?SectionRefData
    {
        foreach ($this->sections->currentYear() as $section) {
            if ($section->campusUlid === $campusUlid && $section->classUlid === $classUlid) {
                return $section;
            }
        }

        return null;
    }

    private function assertBornOn(string $value): void
    {
        $parsed = CalendarDate::parse($value);
        $today = CarbonImmutable::parse(CalendarDate::today());

        // illustrative — verify: a child must be at least 2 years old to apply.
        if ($parsed > $today->toDateString() || $parsed > $today->subYears(2)->toDateString()) {
            throw AdmissionException::invalidBornOn();
        }
    }
}
