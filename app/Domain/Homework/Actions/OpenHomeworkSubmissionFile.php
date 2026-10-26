<?php

namespace App\Domain\Homework\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\Models\HomeworkSubmissionFile;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\Storage;

class OpenHomeworkSubmissionFile extends Action
{
    /**
     * Records that a student's uploaded work was opened (downloads of children's
     * work are audited). The caller has already checked who may see it.
     */
    public function handle(HomeworkSubmissionFile $file): string
    {
        $path = $file->file_path;

        return $this->transaction(function () use ($file, $path) {
            if (! Storage::disk('tenant')->exists($path)) {
                throw HomeworkException::fileMissing();
            }

            $this->audit(new AuditEntry('homework.submission_file_opened', 'homework_submission', $file->submission->ulid, null, ['file' => $file->ulid]));

            return $path;
        });
    }
}
