<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\StudentDocument;
use App\Domain\Students\StudentGuard;

class VerifyStudentDocument extends Action
{
    public function __construct(private readonly StudentGuard $guard) {}

    /**
     * Marks a document as checked against the original. Needs
     * `students.documents` over the student the document belongs to.
     */
    public function handle(Identity $actor, StudentDocument $document): StudentDocument
    {
        $student = $document->student;

        $this->guard->assertMayOn($actor, 'students.documents', $student);

        return $this->transaction(function () use ($actor, $document, $student) {
            $document->refresh();

            if ($document->isVerified()) {
                throw StudentException::documentAlreadyVerified();
            }

            $document->update(['verified_at' => now(), 'verified_by_identity_ulid' => $actor->ulid]);

            $this->audit(new AuditEntry('students.document_verified', 'student', $student->ulid, null, [
                'document' => $document->ulid,
                'kind' => $document->kind->value,
            ]));

            return $document;
        });
    }
}
