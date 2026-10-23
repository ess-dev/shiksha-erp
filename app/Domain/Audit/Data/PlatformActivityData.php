<?php

namespace App\Domain\Audit\Data;

/**
 * One platform audit event about a school, as other modules may read it.
 */
final readonly class PlatformActivityData
{
    /**
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public string $action,
        public array $after,
        public string $occurredAt,
    ) {}
}
