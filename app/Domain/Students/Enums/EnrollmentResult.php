<?php

namespace App\Domain\Students\Enums;

enum EnrollmentResult: string
{
    case InProgress = 'in_progress';
    case Promoted = 'promoted';
    case Moved = 'moved';
    case Left = 'left';
}
