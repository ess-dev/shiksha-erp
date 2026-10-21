<?php

namespace App\Domain\Audit\Enums;

/**
 * Where an event is written: the current school's database, or the landlord's
 * platform audit table.
 */
enum AuditScope: string
{
    case Tenant = 'tenant';
    case Platform = 'platform';
}
