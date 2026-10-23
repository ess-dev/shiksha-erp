<?php

namespace App\Domain\School\Data;

use App\Domain\School\Enums\SubjectType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One subject on the "Subject list" tab.
 */
#[TypeScript]
class SubjectRowData extends Data
{
    /**
     * @param  list<string>  $taught_in  Names of the classes that study it, lowest level first
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $code,
        public SubjectType $type,
        public array $taught_in,
        public bool $can_change,
        public bool $can_delete,
    ) {}
}
