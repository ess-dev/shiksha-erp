<?php

namespace App\Domain\Homework\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An attachment or an uploaded piece of work, with a short-lived signed link.
 */
#[TypeScript]
class HomeworkFileData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $mime_type,
        public int $size_bytes,
        public string $url,
        public bool $is_image,
    ) {}
}
