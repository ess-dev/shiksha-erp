<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

final readonly class ModuleData implements JsonSerializable
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}

    /**
     * @return array{key: string, label: string}
     */
    public function jsonSerialize(): array
    {
        return ['key' => $this->key, 'label' => $this->label];
    }
}
