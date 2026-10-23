<?php

namespace App\Domain\Admissions\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class DocumentRuleData extends Data
{
    /**
     * @param  list<string>  $class_ulids
     */
    public function __construct(
        public string $kind,
        public string $label,
        public bool $is_required,
        public bool $all_classes,
        public array $class_ulids,
    ) {}
}
