<?php

namespace App\Domain\Leave\Models;

use App\Domain\Leave\Enums\LeaveReason;
use App\Domain\Leave\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Leave a guardian asked for. Never deleted: status moves on and the people
 * who recommended or decided are kept.
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property int $student_id
 * @property int $section_id
 * @property int $applied_by_guardian_id
 * @property string $from_on
 * @property string $to_on
 * @property int $school_days
 * @property LeaveReason $reason_type
 * @property string $reason
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property string|null $attachment_mime
 * @property int|null $attachment_size
 * @property LeaveStatus $status
 * @property bool $needs_coordinator
 * @property Carbon $applied_at
 * @property int|null $recommended_by_staff_id
 * @property Carbon|null $recommended_at
 * @property string|null $recommendation_note
 * @property int|null $decided_by_staff_id
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $cancelled_at
 */
class StudentLeaveRequest extends Model
{
    use UsesTenantConnection;

    protected $table = 'student_leave_requests';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'academic_year_id', 'student_id', 'section_id', 'applied_by_guardian_id', 'from_on', 'to_on', 'school_days',
        'reason_type', 'reason', 'attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'status',
        'needs_coordinator', 'applied_at', 'recommended_by_staff_id', 'recommended_at', 'recommendation_note',
        'decided_by_staff_id', 'decided_at', 'decision_note', 'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_on' => 'string',
            'to_on' => 'string',
            'reason_type' => LeaveReason::class,
            'status' => LeaveStatus::class,
            'needs_coordinator' => 'boolean',
            'applied_at' => 'datetime',
            'recommended_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
