<?php

namespace App\Domain\Attendance;

/**
 * Whether a person may change one register and whether the change needs a reason.
 */
final readonly class EditRights
{
    public function __construct(
        public bool $canEdit,
        public bool $needsReason,
    ) {}
}
