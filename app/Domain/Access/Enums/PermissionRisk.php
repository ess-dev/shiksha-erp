<?php

namespace App\Domain\Access\Enums;

enum PermissionRisk: string
{
    case Normal = 'normal';
    case Sensitive = 'sensitive';
    case Critical = 'critical';
}
