<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DocumentSettingsData extends Data
{
    /**
     * @param  list<DocumentRuleData>  $rules
     * @param  list<ClassOptionData>  $classes
     */
    public function __construct(
        public array $rules,
        public array $classes,
        public bool $is_default,
    ) {}
}
