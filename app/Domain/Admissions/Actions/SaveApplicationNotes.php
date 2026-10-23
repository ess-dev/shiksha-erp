<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class SaveApplicationNotes extends Action
{
    public function __construct(private readonly AdmissionGuard $guard) {}

    /**
     * Saves the interaction notes, also on closed and enrolled applications
     * (as in the prototype). Needs `admissions.manage` over the application.
     */
    public function handle(Identity $actor, AdmissionApplication $application, ?string $notes): AdmissionApplication
    {
        $this->guard->assertMayOn($actor, 'admissions.manage', $application);

        $notes = $notes !== null && trim($notes) !== '' ? trim($notes) : null;

        if ($notes === $application->notes) {
            return $application;
        }

        return $this->transaction(function () use ($application, $notes) {
            $application->update(['notes' => $notes]);

            // The notes themselves can hold private details about a child, so the audit row only records that they changed.
            $this->audit(new AuditEntry('admissions.notes_updated', 'admission_application', $application->ulid));

            return $application;
        });
    }
}
