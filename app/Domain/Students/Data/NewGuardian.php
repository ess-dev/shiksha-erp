<?php

namespace App\Domain\Students\Data;

/**
 * A guardian as given when a student is created (a screen, an import, a seeder).
 * The phone is raw text: the Action parses it and applies the one-phone-one-guardian rule.
 */
final readonly class NewGuardian
{
    public function __construct(
        public string $name,
        public string $relation,
        public string $phone,
        public ?string $email = null,
        public ?string $occupation = null,
        public bool $isPrimary = false,
        public bool $hasPortalAccess = true,
    ) {}
}
