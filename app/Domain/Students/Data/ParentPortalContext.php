<?php

namespace App\Domain\Students\Data;

/**
 * Who is looking at what in the parent portal: the guardian, the session being
 * viewed and the selected child. Server side only; pages get `$shell`.
 */
final readonly class ParentPortalContext
{
    /**
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     */
    public function __construct(
        public ParentShellData $shell,
        public ?GuardianChildRef $child,
        public array $session,
    ) {}
}
