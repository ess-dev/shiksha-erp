<?php

namespace App\Domain\Leave\Data;

/**
 * What the signed-in staff member may do with one leave request. Server side
 * only; the pages get the three flags on the item.
 */
final readonly class LeaveAbilities
{
    public function __construct(
        public bool $approve = false,
        public bool $reject = false,
        public bool $recommend = false,
    ) {}

    public function any(): bool
    {
        return $this->approve || $this->reject || $this->recommend;
    }
}
