<?php

namespace App\Domain\Access\Data;

use JsonSerializable;

/**
 * What the signed-in person may do, as sent to the browser. The UI hides
 * what the server would refuse; the server still checks every request.
 */
final readonly class AbilitiesData implements JsonSerializable
{
    /**
     * @param  list<string>  $permissions  Permission keys held in at least one scope
     */
    public function __construct(
        public array $permissions,
        public bool $isOwner,
    ) {}

    /**
     * @return array{permissions: list<string>, is_owner: bool}
     */
    public function jsonSerialize(): array
    {
        return ['permissions' => $this->permissions, 'is_owner' => $this->isOwner];
    }
}
