<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\ApplicationHistory;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class MoveApplication extends Action
{
    public function __construct(
        private readonly AdmissionGuard $guard,
        private readonly ApplicationHistory $history,
    ) {}

    /**
     * Moves an open application to another stage (forward or back). Enrolling
     * is its own step (EnrollApplicant). From "Documents verified" on, the
     * child's details must be complete; moving to "Documents verified" with
     * required documents unticked needs `$acceptMissingDocuments` (the screen's
     * "Move anyway"). Moving back out of Decision clears the decision.
     * Needs `admissions.manage` over the application.
     */
    public function handle(Identity $actor, AdmissionApplication $application, AdmissionStage $to, bool $acceptMissingDocuments = false): AdmissionApplication
    {
        $this->guard->assertMayOn($actor, 'admissions.manage', $application);

        if ($application->isLocked()) {
            throw AdmissionException::closed();
        }

        if ($to === AdmissionStage::Enrolled) {
            throw AdmissionException::useEnrol();
        }

        if ($application->stage === $to) {
            return $application;
        }

        if ($to->needsCompleteDetails() && ! $this->history->detailsComplete($application)) {
            throw AdmissionException::detailsIncomplete();
        }

        $missing = $to === AdmissionStage::Verified ? $this->history->missingDocuments($application) : 0;

        if ($missing > 0 && ! $acceptMissingDocuments) {
            throw AdmissionException::documentsMissing($missing);
        }

        return $this->transaction(function () use ($actor, $application, $to, $missing) {
            $from = $application->stage;
            $clearsDecision = $to->position() < AdmissionStage::Decision->position() && $application->decision !== null;

            $application->update([
                'stage' => $to,
                'decision' => $clearsDecision ? null : $application->decision,
            ]);

            $this->history->record($application, $to, $actor, $missing > 0 ? "{$missing} document(s) still missing" : null);

            $this->audit(new AuditEntry('admissions.application_moved', 'admission_application', $application->ulid, ['stage' => $from->value], [
                'stage' => $to->value,
                'missing_documents' => $missing,
            ]));

            return $application;
        });
    }
}
