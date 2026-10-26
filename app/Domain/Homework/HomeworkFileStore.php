<?php

namespace App\Domain\Homework;

use App\Domain\Homework\Exceptions\HomeworkException;
use App\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Checks and stores homework files (teacher attachments and parent uploads) on
 * the school's `tenant` disk under its own prefix, never in `public/`. The type
 * is read from the file's content, never its name; the name only breaks a tie
 * for Office files, which some servers report as a generic container.
 */
class HomeworkFileStore
{
    /** illustrative — verify: 5 files of 10 MB each, as the prototype states. */
    public const MAX_FILES = 5;

    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Content type => extension. */
    public const TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
    ];

    /** What the content check reports for an Office file it cannot name exactly. */
    private const CONTAINER_TYPES = ['application/zip', 'application/x-ole-storage', 'application/CDFV2', 'application/x-cfb'];

    /** Types shown in the browser; everything else downloads. */
    private const INLINE_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{file: UploadedFile, mime: string, ext: string}>
     *
     * @throws HomeworkException when there are too many files or one is not allowed
     */
    public function check(array $files, int $alreadyKept = 0): array
    {
        if (count($files) + $alreadyKept > self::MAX_FILES) {
            throw HomeworkException::tooManyFiles(self::MAX_FILES);
        }

        $checked = [];

        foreach ($files as $file) {
            $name = mb_substr($file->getClientOriginalName(), 0, 200);
            $size = $file->getSize();

            if (! $file->isValid() || $size === false || $size > self::MAX_BYTES || $size === 0) {
                throw HomeworkException::badFile($name);
            }

            $type = $this->detect($file);

            if ($type === null) {
                throw HomeworkException::badFile($name);
            }

            $checked[] = ['file' => $file, 'mime' => $type['mime'], 'ext' => $type['ext']];
        }

        return $checked;
    }

    /**
     * Stores checked files under `tenants/<school>/homework/<folder>/` and
     * returns what the rows need. The caller deletes them again if its
     * transaction fails.
     *
     * @param  list<array{file: UploadedFile, mime: string, ext: string}>  $checked
     * @return list<array{ulid: string, path: string, name: string, mime: string, size: int}>
     */
    public function store(array $checked, string $folder): array
    {
        $tenant = Tenant::current() ?? throw HomeworkException::notAllowed();
        $stored = [];

        foreach ($checked as $item) {
            $ulid = strtolower((string) Str::ulid());
            $path = "tenants/{$tenant->ulid}/homework/{$folder}/{$ulid}.{$item['ext']}";

            Storage::disk('tenant')->putFileAs(dirname($path), $item['file'], basename($path));

            $stored[] = [
                'ulid' => $ulid,
                'path' => $path,
                'name' => mb_substr($item['file']->getClientOriginalName(), 0, 200),
                'mime' => $item['mime'],
                'size' => (int) $item['file']->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  list<string>  $paths
     */
    public function delete(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('tenant')->delete($paths);
        }
    }

    public function isInline(string $mime): bool
    {
        return in_array($mime, self::INLINE_TYPES, true);
    }

    /**
     * @return array{mime: string, ext: string}|null
     */
    private function detect(UploadedFile $file): ?array
    {
        $mime = (string) $file->getMimeType();

        if (isset(self::TYPES[$mime])) {
            return ['mime' => $mime, 'ext' => self::TYPES[$mime]];
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $byExtension = array_search($ext, self::TYPES, true);

        if (in_array($mime, self::CONTAINER_TYPES, true) && is_string($byExtension) && ! in_array($byExtension, self::INLINE_TYPES, true) && $byExtension !== 'text/plain') {
            return ['mime' => $byExtension, 'ext' => $ext];
        }

        return null;
    }
}
