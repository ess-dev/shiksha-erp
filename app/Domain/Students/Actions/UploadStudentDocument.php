<?php

namespace App\Domain\Students\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Students\Enums\DocumentKind;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Student;
use App\Domain\Students\Models\StudentDocument;
use App\Domain\Students\StudentGuard;
use App\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadStudentDocument extends Action
{
    /** Types are read from the file's content, never its extension. */
    public const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** illustrative — verify: 5 MB per document. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly StudentGuard $guard) {}

    /**
     * Stores a scan of a student's document on the tenant disk, under the
     * school's prefix. Staff uploads are verified straight away (as in the
     * prototype). A new document of the same kind replaces the earlier one on
     * the profile; the earlier row and file are kept. Needs
     * `students.documents` over the student.
     */
    public function handle(Identity $actor, Student $student, DocumentKind $kind, UploadedFile $file): StudentDocument
    {
        $this->guard->assertMayOn($actor, 'students.documents', $student);

        $mime = (string) $file->getMimeType();

        if (! isset(self::ALLOWED_TYPES[$mime])) {
            throw new StudentException('Upload a PDF, JPEG or PNG file.');
        }

        if ($file->getSize() === false || $file->getSize() > self::MAX_BYTES) {
            throw new StudentException('A document can be at most 5 MB.');
        }

        $tenant = Tenant::current() ?? throw StudentException::notAllowed();
        $ulid = strtolower((string) Str::ulid());
        $path = "tenants/{$tenant->ulid}/students/{$student->ulid}/{$ulid}.".self::ALLOWED_TYPES[$mime];

        Storage::disk('tenant')->putFileAs(dirname($path), $file, basename($path));

        try {
            return $this->transaction(function () use ($actor, $student, $kind, $file, $mime, $path, $ulid) {
                if ($kind->replacesEarlier()) {
                    StudentDocument::query()
                        ->where('student_id', $student->id)
                        ->where('kind', $kind->value)
                        ->whereNull('superseded_at')
                        ->update(['superseded_at' => now()]);
                }

                $document = StudentDocument::query()->create([
                    'ulid' => $ulid,
                    'student_id' => $student->id,
                    'kind' => $kind,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                    'path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => (int) $file->getSize(),
                    'source' => 'school',
                    'uploaded_by_identity_ulid' => $actor->ulid,
                    'verified_at' => now(),
                    'verified_by_identity_ulid' => $actor->ulid,
                ]);

                $this->audit(new AuditEntry('students.document_uploaded', 'student', $student->ulid, null, [
                    'document' => $document->ulid,
                    'kind' => $kind->value,
                ]));

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk('tenant')->delete($path);

            throw $e;
        }
    }
}
