<?php

namespace App\Http\Controllers\Homework;

use App\Domain\Homework\Actions\OpenHomeworkSubmissionFile;
use App\Domain\Homework\Queries\HomeworkFileQuery;
use App\Domain\Identity\Models\Identity;
use App\Http\Controllers\Concerns\StreamsHomeworkFiles;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HomeworkFileController extends Controller
{
    use StreamsHomeworkFiles;

    /**
     * A teacher's attachment, for anyone who may see the assignment, from a
     * short-lived signed link.
     */
    public function attachment(Request $request, string $file, HomeworkFileQuery $files): StreamedResponse
    {
        $found = $files->attachmentForStaff($this->actor($request), $file);

        return $this->streamHomeworkFile($found->file_path, $found->file_name, $found->mime_type);
    }

    /**
     * A student's uploaded work. Every opening is audited.
     */
    public function submission(Request $request, string $file, HomeworkFileQuery $files, OpenHomeworkSubmissionFile $open): StreamedResponse
    {
        $found = $files->submissionFileForStaff($this->actor($request), $file);
        $path = $open->handle($found);

        return $this->streamHomeworkFile($path, $found->file_name, $found->mime_type);
    }

    private function actor(Request $request): Identity
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $identity;
    }
}
