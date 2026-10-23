<?php

namespace App\Domain\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * The daily register of one section on one school day. Corrected in place with
 * a reason and an audit event; never deleted.
 *
 * @property int $id
 * @property string $ulid
 * @property int $section_id
 * @property string $attendance_on
 * @property int|null $saved_by_staff_id
 * @property Carbon $first_saved_at
 * @property Carbon $last_saved_at
 * @property string|null $last_reason
 */
class AttendanceRegister extends Model
{
    use UsesTenantConnection;

    protected $table = 'attendance_registers';

    /** @var list<string> */
    protected $fillable = ['ulid', 'section_id', 'attendance_on', 'saved_by_staff_id', 'first_saved_at', 'last_saved_at', 'last_reason'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['attendance_on' => 'string', 'first_saved_at' => 'datetime', 'last_saved_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<AttendanceMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(AttendanceMark::class, 'attendance_register_id');
    }
}
