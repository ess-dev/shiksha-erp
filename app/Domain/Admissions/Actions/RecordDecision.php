<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\ApplicationHistory;
use App\Domain\Admissions\Enums\AdmissionDecision;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class RecordDecision extends Action
{
    public function __construct(
        private readonly AdmissionGuard $guard,
        private readonly ApplicationHistory $history,
    ) {}

    /**
     * Accepts, waitlists or refuses an application at the Decision stage. A
     * waitlisted application can be decided again; "not admitted" closes it.
     * Needs `admissions.manage` over the application.
     */
    public function handle(Identity $actor, AdmissionApplication $application, AdmissionDecision $decision): AdmissionApplication
    {
        $this->guard->assertMayOn($actor, 'admissions.manage', $application);

        if ($application->isLocked()) {
            throw AdmissionException::closed();
        }

        if ($application->stage !== AdmissionStage::Decision) {
            throw AdmissionException::decisionStage();
        }

        return $this->transaction(function () use ($actor, $application, $decision) {
            $before = $application->decision?->value;

            $application->update([
                'decision' => $decision,
                'is_closed' => $decision === AdmissionDecision::Rejected,
            ]);

            $this->history->record($application, AdmissionStage::Decision, $actor, $decision->historyNote());

            $this->audit(new AuditEntry('admissions.decision_recorded', 'admission_application', $application->ulid, ['decision' => $before], ['decision' => $decision->value]));

            return $application;
        });
    }
}
