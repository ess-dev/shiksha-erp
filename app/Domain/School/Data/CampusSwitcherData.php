<?php

namespace App\Domain\School\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class CampusSwitcherData extends Data
{
    /**
     * @param  list<CampusOptionData>  $campuses  The campuses the person can reach
     * @param  string|null  $selected  The campus chosen in the top bar; null means all of them
     */
    public function __construct(
        public array $campuses,
        public ?string $selected,
    ) {}
}
