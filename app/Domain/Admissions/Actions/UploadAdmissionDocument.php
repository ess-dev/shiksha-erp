<?php

namespace App\Domain\Admissions\Actions;

use App\Domain\Admissions\AdmissionGuard;
use App\Domain\Admissions\AdmissionVisibility;
use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadAdmissionDocument extends Action
{
    /** Types are read from the file's content, never its extension. */
    public const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** illustrative — verify: 5 MB per document, the same as student documents. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly AdmissionGuard $guard,
        private readonly AdmissionVisibility $visibility,
    ) {}

    /**
     * Stores a scan on the tenant disk under the school's prefix and marks the
     * document received. A new upload of the same kind replaces the earlier
     * one on the application (the earlier file is kept on disk). Needs
     * `admissions.manage` and `students.documents` over the application.
     */
    public function handle(Identity $actor, AdmissionApplication $application, AdmissionDocumentKind $kind, UploadedFile $file): AdmissionDocument
    {
        $this->guard->assertMayOn($actor, 'admissions.manage', $application);
        $this->guard->assertMayAt($actor, 'students.documents', $this->visibility->target($application));

        if ($application->isLocked()) {
            throw AdmissionException::closed();
        }

        $mime = (string) $file->getMimeType();
        $size = $file->getSize();

        if (! isset(self::ALLOWED_TYPES[$mime]) || $size === false || $size > self::MAX_BYTES) {
            throw AdmissionException::invalidDocument();
        }

        $tenant = Tenant::current() ?? throw AdmissionException::notAllowed();
        $path = "tenants/{$tenant->ulid}/admissions/{$application->ulid}/".strtolower((string) Str::ulid()).'.'.self::ALLOWED_TYPES[$mime];

        Storage::disk('tenant')->putFileAs(dirname($path), $file, basename($path));

        try {
            return $this->transaction(function () use ($actor, $application, $kind, $file, $mime, $size, $path) {
                $document = AdmissionDocument::query()->firstOrNew(['admission_application_id' => $application->id, 'kind' => $kind]);
                $document->fill([
                    'is_received' => true,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                    'path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => (int) $size,
                    'uploaded_on' => CalendarDate::today(),
                    'uploaded_by_identity_ulid' => $actor->ulid,
                ]);
                $document->save();

                $this->audit(new AuditEntry('admissions.document_uploaded', 'admission_application', $application->ulid, null, [
                    'kind' => $kind->value,
                    'type' => $mime,
                    'size_bytes' => (int) $size,
                ]));

                return $document;
            });
        } catch (Throwable $e) {
            Storage::disk('tenant')->delete($path);

            throw $e;
        }
    }
}
