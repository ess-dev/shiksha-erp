<?php

namespace App\Domain\Audit\Contracts;

use App\Domain\Audit\Data\AuditActor;

interface ResolvesAuditActor
{
    public function resolve(): AuditActor;
}
