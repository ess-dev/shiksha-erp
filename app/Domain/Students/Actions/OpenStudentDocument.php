<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\StudentDocument;
use App\Domain\Students\StudentGuard;
use Illuminate\Support\Facades\Storage;

class OpenStudentDocument extends Action
{
    public function __construct(private readonly StudentGuard $guard) {}

    /**
     * Checks that the person may see this document and records that it was
     * opened (downloads are audited). Needs `students.view_sensitive` over the
     * student. Returns the path on the tenant disk.
     */
    public function handle(Identity $actor, StudentDocument $document): string
    {
        $student = $document->student;

        $this->guard->assertMayOn($actor, 'students.view_sensitive', $student);

        return $this->transaction(function () use ($document, $student) {
            if (! Storage::disk('tenant')->exists($document->path)) {
                throw StudentException::documentMissing();
            }

            $this->audit(new AuditEntry('students.document_opened', 'student', $student->ulid, null, [
                'document' => $document->ulid,
                'kind' => $document->kind->value,
            ]));

            return $document->path;
        });
    }
}
