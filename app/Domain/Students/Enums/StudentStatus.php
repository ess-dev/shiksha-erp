<?php

namespace App\Domain\Students\Enums;

enum StudentStatus: string
{
    case Enrolled = 'enrolled';
    case Left = 'left';
    case Transferred = 'transferred';
    case Alumni = 'alumni';

    /**
     * Whether the student is still at the school.
     */
    public function isCurrent(): bool
    {
        return $this === self::Enrolled;
    }

    /**
     * The statuses the "Left the school" filter covers.
     *
     * @return list<self>
     */
    public static function leftSchool(): array
    {
        return [self::Left, self::Transferred];
    }
}
