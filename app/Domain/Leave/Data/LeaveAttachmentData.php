<?php

namespace App\Domain\Leave\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The medical certificate or letter of a request, with a short-lived signed link.
 */
#[TypeScript]
class LeaveAttachmentData extends Data
{
    public function __construct(
        public string $name,
        public string $mime_type,
        public int $size_bytes,
        public string $url,
        public bool $is_image,
    ) {}
}
