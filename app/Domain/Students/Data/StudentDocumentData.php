<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentDocumentData extends Data
{
    public function __construct(
        public string $ulid,
        public string $kind,
        public string $label,
        public string $original_name,
        public bool $is_verified,
        public ?string $verified_by,
        public string $uploaded_on,
        public string $source,
        public ?string $view_url,
    ) {}
}
