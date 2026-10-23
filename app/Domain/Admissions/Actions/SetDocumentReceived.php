<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;

class SetDocumentReceived extends Action
{
    public function __construct(private readonly AdmissionGuard $guard) {}

    /**
     * Ticks or unticks a document as received (a paper copy may have been
     * handed in with no scan). Needs `admissions.manage` over the application.
     */
    public function handle(Identity $actor, AdmissionApplication $application, AdmissionDocumentKind $kind, bool $received): AdmissionDocument
    {
        $this->guard->assertMayOn($actor, 'admissions.manage', $application);

        if ($application->isLocked()) {
            throw AdmissionException::closed();
        }

        return $this->transaction(function () use ($application, $kind, $received) {
            $document = AdmissionDocument::query()->firstOrNew(['admission_application_id' => $application->id, 'kind' => $kind]);
            $document->is_received = $received;
            $document->save();

            $this->audit(new AuditEntry('admissions.document_ticked', 'admission_application', $application->ulid, null, [
                'kind' => $kind->value,
                'received' => $received,
            ]));

            return $document;
        });
    }
}
