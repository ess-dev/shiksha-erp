<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\Data\ApplicationDetailsUpdate;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\HousesQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use Carbon\CarbonImmutable;

class UpdateApplicationDetails extends Action
{
    public function __construct(
        private readonly AdmissionGuard $guard,
        private readonly HousesQuery $houses,
    ) {}

    /**
     * Saves the child's own details (date of birth, blood group, address,
     * house, parent email) collected at the Application stage. Needs
     * `admissions.manage` over the application; closed and enrolled
     * applications are read-only.
     */
    public function handle(Identity $actor, AdmissionApplication $application, ApplicationDetailsUpdate $input): AdmissionApplication
    {
        $this->guard->assertMayOn($actor, 'admissions.manage', $application);

        if ($application->isLocked()) {
            throw AdmissionException::closed();
        }

        $bornOn = $input->bornOn !== null && $input->bornOn !== '' ? CalendarDate::parse($input->bornOn) : null;

        // illustrative — verify: a child must be at least 2 years old to apply.
        if ($bornOn !== null && $bornOn > CarbonImmutable::parse(CalendarDate::today())->subYears(2)->toDateString()) {
            throw AdmissionException::invalidBornOn();
        }

        $houseKey = $input->houseUlid === null || $input->houseUlid === '' ? null : $this->houses->activeKeyByUlid($input->houseUlid);

        return $this->transaction(function () use ($application, $input, $bornOn, $houseKey) {
            $before = [
                'born_on' => $application->born_on,
                'blood_group' => $application->blood_group,
                'house_id' => $application->house_id,
            ];

            $application->update([
                'born_on' => $bornOn,
                'blood_group' => $input->bloodGroup !== null && $input->bloodGroup !== '' ? $input->bloodGroup : null,
                'address' => $input->address !== null && trim($input->address) !== '' ? trim($input->address) : null,
                'house_id' => $houseKey,
                'email' => $input->parentEmail !== null && trim($input->parentEmail) !== '' ? trim($input->parentEmail) : null,
            ]);

            $this->audit(new AuditEntry('admissions.details_updated', 'admission_application', $application->ulid, $before, [
                'born_on' => $application->born_on,
                'blood_group' => $application->blood_group,
                'house_id' => $application->house_id,
            ]));

            return $application;
        });
    }
}
