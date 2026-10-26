<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Homework\Exceptions\HomeworkException;
use App\Domain\Homework\HomeworkFileStore;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsHomeworkFiles
{
    /**
     * Streams a stored homework file from the tenant disk: photos and PDFs open
     * in the browser, everything else downloads. Never cached, never sniffed.
     */
    protected function streamHomeworkFile(string $path, string $name, string $mime): StreamedResponse
    {
        $disk = Storage::disk('tenant');

        if (! $disk->exists($path)) {
            throw HomeworkException::fileMissing();
        }

        $headers = ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'];

        return app(HomeworkFileStore::class)->isInline($mime)
            ? $disk->response($path, $name, $headers)
            : $disk->download($path, $name, $headers);
    }
}
