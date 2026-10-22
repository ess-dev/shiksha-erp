<?php

namespace App\Domain\Students\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StudentAbilitiesData extends Data
{
    public function __construct(
        public bool $manage,
        public bool $issue_tc,
        public bool $documents,
        public bool $link_siblings,
        public bool $view_sensitive,
    ) {}
}
