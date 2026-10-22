<?php

namespace App\Domain\Students\Data;

/**
 * New details for one existing guardian of a student. Name, phone, email and
 * occupation belong to the guardian (and so to every child); relation, primary
 * contact and portal access belong to this child's link.
 */
final readonly class GuardianUpdate
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $relation,
        public string $phone,
        public ?string $email,
        public ?string $occupation,
        public bool $isPrimary,
        public bool $hasPortalAccess,
    ) {}
}
