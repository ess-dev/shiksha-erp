<?php

namespace Database\Seeders;

use App\Domain\Admissions\Actions\AddEnquiry;
use App\Domain\Admissions\Actions\EnrollApplicant;
use App\Domain\Admissions\Actions\MoveApplication;
use App\Domain\Admissions\Actions\RecordDecision;
use App\Domain\Admissions\Actions\SetDocumentReceived;
use App\Domain\Admissions\Actions\UpdateApplicationDetails;
use App\Domain\Admissions\Data\ApplicationDetailsUpdate;
use App\Domain\Admissions\Data\NewEnquiry;
use App\Domain\Admissions\Enums\AdmissionDecision;
use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional admission applications for the two demo schools, created through
 * the real Actions by each school's demo principal, spread over the pipeline
 * as in the prototype (5 enquiries, 3 applications, 2 verified, 2 at decision,
 * 2 enrolled). Safe to re-run (a school that already has applications is left
 * alone). Local only. illustrative — verify: names and counts are placeholders.
 */
class DemoAdmissionsSeeder extends Seeder
{
    private const SCHOOLS = [
        'greenfield' => 'principal@greenfield.test',
        'riverside' => 'principal@riverside.test',
    ];

    /** first, last, gender, class level, campus index, source, stage, days ago */
    private const APPLICANTS = [
        ['Advika', 'Bansal', 'female', 1, 0, AdmissionSource::Website, 'enquiry', 1],
        ['Shaurya', 'Grewal', 'male', 3, 0, AdmissionSource::WalkIn, 'enquiry', 2],
        ['Inaaya', 'Khan', 'female', 2, 0, AdmissionSource::Referral, 'enquiry', 3],
        ['Mihir', 'Joshi', 'male', 1, 1, AdmissionSource::FacebookAd, 'enquiry', 4],
        ['Ekam', 'Dhillon', 'male', 2, 1, AdmissionSource::WalkIn, 'enquiry', 6],
        ['Zoya', 'Mirza', 'female', 3, 0, AdmissionSource::Website, 'application', 10],
        ['Arnav', 'Chopra', 'male', 2, 0, AdmissionSource::Referral, 'application', 12],
        ['Gauri', 'Nair', 'female', 1, 1, AdmissionSource::Newspaper, 'application', 15],
        ['Yash', 'Malhotra', 'male', 3, 0, AdmissionSource::Website, 'verified', 19],
        ['Sia', 'Kapoor', 'female', 2, 0, AdmissionSource::WalkIn, 'verified', 21],
        ['Devansh', 'Rao', 'male', 2, 0, AdmissionSource::Referral, 'decision', 25],
        ['Mehar', 'Sandhu', 'female', 3, 0, AdmissionSource::Website, 'decision', 27],
        ['Aryan', 'Saini', 'male', 3, 1, AdmissionSource::WalkIn, 'enrolled', 35],
        ['Kiara', 'Bhatia', 'female', 1, 0, AdmissionSource::Website, 'enrolled', 40],
    ];

    private const PARENTS = ['Rakesh', 'Neetu', 'Sandeep', 'Ritu', 'Imran', 'Komal'];

    public function run(
        AddEnquiry $addEnquiry,
        UpdateApplicationDetails $updateDetails,
        SetDocumentReceived $tick,
        MoveApplication $move,
        RecordDecision $decide,
        EnrollApplicant $enroll,
        SectionDirectoryQuery $sections,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo admissions must never be seeded in production.');
        }

        foreach (self::SCHOOLS as $subdomain => $email) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();
            $principal = Identity::query()->where('email', $email)->first();

            if ($tenant === null || $principal === null) {
                continue;
            }

            $tenant->execute(function () use ($principal, $subdomain, $addEnquiry, $updateDetails, $tick, $move, $decide, $enroll, $sections) {
                $places = $sections->currentYear();

                if ($places === [] || AdmissionApplication::query()->exists()) {
                    return;
                }

                $campuses = array_values(array_unique(array_map(fn (SectionRefData $s) => $s->campusUlid, $places)));
                $levels = array_values(array_unique(array_map(fn (SectionRefData $s) => $s->classLevel, $places)));
                sort($levels);
                $today = CarbonImmutable::parse(CalendarDate::today());
                $phone = $subdomain === 'greenfield' ? 9876530000 : 9876540000;

                foreach (self::APPLICANTS as $index => [$first, $last, $gender, $level, $campusIndex, $source, $stage, $daysAgo]) {
                    $campusUlid = $campuses[$campusIndex % count($campuses)];
                    $classLevel = $levels[($level - 1) % count($levels)];
                    $place = collect($places)->first(fn (SectionRefData $s) => $s->campusUlid === $campusUlid && $s->classLevel === $classLevel)
                        ?? collect($places)->first(fn (SectionRefData $s) => $s->classLevel === $classLevel);

                    if ($place === null) {
                        continue;
                    }

                    $application = $addEnquiry->handle($principal, new NewEnquiry(
                        firstName: $first,
                        lastName: $last,
                        gender: $gender,
                        bornOn: null,
                        classUlid: $place->classUlid,
                        campusUlid: $place->campusUlid,
                        parentName: self::PARENTS[$index % count(self::PARENTS)].' '.$last,
                        parentRelation: $index % 2 === 0 ? 'Mother' : 'Father',
                        phone: (string) ($phone + $index),
                        source: $source,
                        notes: null,
                    ));

                    $position = AdmissionStage::from($stage)->position();

                    if ($position >= AdmissionStage::Application->position()) {
                        $move->handle($principal, $application, AdmissionStage::Application);
                        $tick->handle($principal, $application, AdmissionDocumentKind::Photo, true);
                    }

                    if ($position >= AdmissionStage::Verified->position()) {
                        $updateDetails->handle($principal, $application, new ApplicationDetailsUpdate(
                            bornOn: $today->subYears(4 + $classLevel)->subDays($index * 37)->toDateString(),
                            bloodGroup: 'O+',
                            address: 'Sector '.(60 + $index).', Demo Nagar',
                            houseUlid: null,
                            parentEmail: null,
                        ));

                        foreach ([AdmissionDocumentKind::BirthCertificate, AdmissionDocumentKind::Aadhaar, AdmissionDocumentKind::TransferCertificate] as $kind) {
                            $tick->handle($principal, $application, $kind, true);
                        }

                        $move->handle($principal, $application, AdmissionStage::Verified);
                    }

                    if ($position >= AdmissionStage::Decision->position()) {
                        $move->handle($principal, $application, AdmissionStage::Decision);
                    }

                    if ($stage === 'enrolled') {
                        $decide->handle($principal, $application, AdmissionDecision::Accepted);

                        $target = collect($places)->first(fn (SectionRefData $s) => $s->classUlid === $place->classUlid && $s->campusUlid === $place->campusUlid && ! $s->isFull());
                        $enroll->handle($principal, $application, ($target ?? $place)->ulid);
                    }

                    AdmissionApplication::query()->whereKey($application->id)->update(['enquired_on' => $today->subDays($daysAgo)->toDateString()]);
                }
            });
        }
    }
}
