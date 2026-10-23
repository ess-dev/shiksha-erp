<?php

namespace App\Domain\Audit\Enums;

enum AuditActorType: string
{
    case User = 'user';
    case Staff = 'staff';
    case Guardian = 'guardian';
    case Platform = 'platform';
    case System = 'system';
}
