<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a whole class shares for one subject this year. `is_saved` is false
 * when nothing was saved this year yet; the fields then hold last year's
 * book and chapters (`copied_from` names that year) or are empty.
 */
#[TypeScript]
class SyllabusClassData extends Data
{
    /**
     * @param  list<string>  $chapters
     */
    public function __construct(
        public ?string $book,
        public array $chapters,
        public ?string $assessment,
        public bool $is_saved,
        public ?string $copied_from,
        public ?string $updated_on,
        public ?string $updated_by,
    ) {}
}
