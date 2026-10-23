<?php

namespace App\Domain\Audit\Data;

use App\Domain\Audit\Enums\AuditActorType;

final readonly class AuditActor
{
    /**
     * @param  list<string>|null  $roleKeys
     */
    public function __construct(
        public AuditActorType $type,
        public ?string $identityUlid = null,
        public ?string $membershipUlid = null,
        public ?array $roleKeys = null,
    ) {}

    public static function system(): self
    {
        return new self(AuditActorType::System);
    }
}
