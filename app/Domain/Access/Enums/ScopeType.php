<?php

namespace App\Domain\Access\Enums;

enum ScopeType: string
{
    case School = 'school';
    case Campus = 'campus';
    case Wing = 'wing';
    case Sections = 'sections';
}
