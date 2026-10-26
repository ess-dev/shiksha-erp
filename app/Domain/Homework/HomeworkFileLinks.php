<?php

namespace App\Domain\Homework;

use App\Domain\Homework\Data\HomeworkFileData;
use App\Domain\Homework\Models\HomeworkFile;
use App\Domain\Homework\Models\HomeworkSubmissionFile;
use Illuminate\Support\Facades\URL;

/**
 * Builds the short-lived signed links pages use to open homework files. A link
 * only proves the page was allowed to show the file a moment ago; the file
 * route checks access again when it is opened.
 */
class HomeworkFileLinks
{
    /** illustrative — verify: how long a link stays valid. */
    private const MINUTES = 10;

    public function attachment(HomeworkFile $file, ?string $childUlid = null): HomeworkFileData
    {
        return $this->data($file, $file->mime_type, $this->signed($childUlid === null ? 'homework.files.show' : 'parent.homework.files.show', $file->ulid, $childUlid));
    }

    public function submission(HomeworkSubmissionFile $file, ?string $childUlid = null): HomeworkFileData
    {
        return $this->data($file, $file->mime_type, $this->signed($childUlid === null ? 'homework.submission-files.show' : 'parent.homework.submission-files.show', $file->ulid, $childUlid));
    }

    /**
     * @param  iterable<HomeworkFile>  $files
     * @return list<HomeworkFileData>
     */
    public function attachments(iterable $files, ?string $childUlid = null): array
    {
        $links = [];

        foreach ($files as $file) {
            $links[] = $this->attachment($file, $childUlid);
        }

        return $links;
    }

    /**
     * @param  iterable<HomeworkSubmissionFile>  $files
     * @return list<HomeworkFileData>
     */
    public function submissions(iterable $files, ?string $childUlid = null): array
    {
        $links = [];

        foreach ($files as $file) {
            $links[] = $this->submission($file, $childUlid);
        }

        return $links;
    }

    private function data(HomeworkFile|HomeworkSubmissionFile $file, string $mime, string $url): HomeworkFileData
    {
        return new HomeworkFileData($file->ulid, $file->file_name, $mime, $file->size_bytes, $url, str_starts_with($mime, 'image/'));
    }

    private function signed(string $route, string $fileUlid, ?string $childUlid): string
    {
        return URL::temporarySignedRoute($route, now()->addMinutes(self::MINUTES), array_filter(['file' => $fileUlid, 'child' => $childUlid]));
    }
}
