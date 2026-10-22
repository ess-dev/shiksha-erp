<?php

namespace App\Domain\Students\Enums;

enum ImportRowStatus: string
{
    case Ready = 'ready';
    case Warning = 'warning';
    case Fix = 'fix';
}
