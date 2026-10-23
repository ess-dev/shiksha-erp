<?php

namespace App\Domain\Access\Data;

/**
 * A member of staff as other modules need them: the key a foreign key points
 * at, the ulid and the name. Server-side only, never sent to a page (it
 * carries the internal key).
 */
final readonly class StaffRefData
{
    public function __construct(
        public int $staffKey,
        public string $ulid,
        public string $fullName,
        public bool $isActive,
    ) {}
}
