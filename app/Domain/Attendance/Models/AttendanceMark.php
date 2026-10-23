<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Attendance\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One student's status on one register.
 *
 * @property int $id
 * @property int $attendance_register_id
 * @property int $student_id
 * @property AttendanceStatus $status
 */
class AttendanceMark extends Model
{
    use UsesTenantConnection;

    protected $table = 'attendance_marks';

    /** @var list<string> */
    protected $fillable = ['attendance_register_id', 'student_id', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => AttendanceStatus::class];
    }

    /**
     * @return BelongsTo<AttendanceRegister, $this>
     */
    public function register(): BelongsTo
    {
        return $this->belongsTo(AttendanceRegister::class, 'attendance_register_id');
    }
}
