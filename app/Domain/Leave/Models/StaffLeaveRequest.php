<?php

namespace App\Domain\Leave\Models;

use App\Domain\Leave\Enums\LeaveStatus;
use App\Domain\Leave\Enums\StaffLeaveType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Leave a member of staff asked for. Never deleted: status moves on and the
 * approver and the cover are kept. `half_days` is 2 per school day.
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property int $staff_id
 * @property int|null $section_id
 * @property StaffLeaveType $leave_type
 * @property string $from_on
 * @property string $to_on
 * @property int $half_days
 * @property string $reason
 * @property string|null $suggested_cover
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 * @property int|null $attachment_size
 * @property LeaveStatus $status
 * @property Carbon $applied_at
 * @property int|null $decided_by_staff_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property int|null $cover_staff_id
 * @property Carbon|null $cancelled_at
 */
class StaffLeaveRequest extends Model
{
    use UsesTenantConnection;

    protected $table = 'staff_leave_requests';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'academic_year_id', 'staff_id', 'section_id', 'leave_type', 'from_on', 'to_on', 'half_days', 'reason',
        'suggested_cover', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'status',
        'applied_at', 'decided_by_staff_id', 'decided_at', 'decision_note', 'cover_staff_id', 'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_on' => 'string',
            'to_on' => 'string',
            'leave_type' => StaffLeaveType::class,
            'status' => LeaveStatus::class,
            'applied_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
