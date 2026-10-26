<?php

namespace App\Domain\Leave;

use App\Domain\Leave\Exceptions\LeaveException;
use App\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Checks and stores the optional medical certificate or letter on the school's
 * `tenant` disk under its own prefix, never in `public/`. The type is read from
 * the file's content, not its name.
 */
class LeaveAttachmentStore
{
    /** illustrative — verify: one photo or PDF of at most 5 MB, like the student documents. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Content type => extension. */
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{mime: string, ext: string}
     *
     * @throws LeaveException when the file is empty, too big or not a photo or PDF
     */
    public function check(UploadedFile $file): array
    {
        $name = mb_substr($file->getClientOriginalName(), 0, 200);
        $size = $file->getSize();
        $mime = (string) $file->getMimeType();

        if (! $file->isValid() || $size === false || $size === 0 || $size > self::MAX_BYTES || ! isset(self::TYPES[$mime])) {
            throw LeaveException::badAttachment($name);
        }

        return ['mime' => $mime, 'ext' => self::TYPES[$mime]];
    }

    /**
     * Stores a checked file under `tenants/<school>/leave/<request>.<ext>`. The
     * caller deletes it again if its transaction fails.
     *
     * @param  array{mime: string, ext: string}  $checked
     * @return array{path: string, name: string, mime: string, size: int}
     */
    public function store(UploadedFile $file, array $checked, string $leaveUlid): array
    {
        $tenant = Tenant::current() ?? throw LeaveException::notAllowed();
        $path = "tenants/{$tenant->ulid}/leave/{$leaveUlid}.{$checked['ext']}";

        Storage::disk('tenant')->putFileAs(dirname($path), $file, basename($path));

        return [
            'path' => $path,
            'name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $checked['mime'],
            'size' => (int) $file->getSize(),
        ];
    }

    public function delete(string $path): void
    {
        Storage::disk('tenant')->delete($path);
    }
}
