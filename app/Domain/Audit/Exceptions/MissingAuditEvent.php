<?php

namespace App\Domain\Audit\Exceptions;

use RuntimeException;

class MissingAuditEvent extends RuntimeException
{
    public static function forAction(string $action): self
    {
        return new self("Action [{$action}] finished without writing an audit event.");
    }
}
