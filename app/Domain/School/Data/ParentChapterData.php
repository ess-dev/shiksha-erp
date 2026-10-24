<?php

namespace App\Domain\School\Data;

use App\Domain\School\Enums\ChapterState;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ParentChapterData extends Data
{
    public function __construct(
        public int $number,
        public string $title,
        public ChapterState $state,
    ) {}
}
