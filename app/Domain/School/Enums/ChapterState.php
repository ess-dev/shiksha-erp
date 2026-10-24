<?php

namespace App\Domain\School\Enums;

enum ChapterState: string
{
    case Done = 'done';
    case Current = 'current';
    case Upcoming = 'upcoming';
}
