<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\Storage;

class OpenAdmissionDocument extends Action
{
    public function __construct(private readonly AdmissionGuard $guard) {}

    /**
     * Checks that the person may see this scan and records the opening
     * (downloads are audited). Needs `students.view_sensitive` and
     * `admissions.view` over the application. Returns the path on the tenant disk.
     */
    public function handle(Identity $actor, AdmissionDocument $document): string
    {
        $application = $document->application;

        $this->guard->assertMayOn($actor, 'admissions.view', $application);
        $this->guard->assertMayOn($actor, 'students.view_sensitive', $application);

        return $this->transaction(function () use ($document, $application) {
            if ($document->path === null || ! Storage::disk('tenant')->exists($document->path)) {
                throw AdmissionException::documentMissing();
            }

            $this->audit(new AuditEntry('admissions.document_opened', 'admission_application', $application->ulid, null, [
                'kind' => $document->kind->value,
            ]));

            return $document->path;
        });
    }
}
