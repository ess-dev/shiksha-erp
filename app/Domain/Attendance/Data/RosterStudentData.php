<?php

namespace App\Domain\Attendance\Data;

use App\Domain\Attendance\Enums\AttendanceStatus;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class RosterStudentData extends Data
{
    public function __construct(
        public string $ulid,
        public string $name,
        public string $admission_number,
        public ?int $roll_number,
        public ?AttendanceStatus $status,
        public int $recent_absences,
    ) {}
}
