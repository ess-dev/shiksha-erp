<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DocumentRowData extends Data
{
    public function __construct(
        public string $kind,
        public string $label,
        public bool $is_required,
        public bool $is_received,
        public ?string $file_name,
        public ?string $uploaded_on,
        public ?string $view_url,
    ) {}
}
