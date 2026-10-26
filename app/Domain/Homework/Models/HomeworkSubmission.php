<?php

namespace App\Domain\Homework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One student's work on one assignment: uploaded by a guardian (`submitted_on`)
 * and/or marked checked by the teacher (`checked_on`, the way notebook work is
 * handed in), with optional feedback the parent can read. A row exists only
 * once one of the two has happened.
 *
 * @property int $id
 * @property string $ulid
 * @property int $homework_assignment_id
 * @property int $student_id
 * @property string|null $submitted_on
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by_guardian_id
 * @property string|null $checked_on
 * @property int|null $checked_by_staff_id
 * @property string|null $feedback
 * @property int|null $feedback_by_staff_id
 * @property Carbon|null $feedback_at
 */
class HomeworkSubmission extends Model
{
    use UsesTenantConnection;

    protected $table = 'homework_submissions';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'homework_assignment_id', 'student_id', 'submitted_on', 'submitted_at', 'submitted_by_guardian_id',
        'checked_on', 'checked_by_staff_id', 'feedback', 'feedback_by_staff_id', 'feedback_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_on' => 'string',
            'checked_on' => 'string',
            'submitted_at' => 'datetime',
            'feedback_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<HomeworkAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HomeworkAssignment::class, 'homework_assignment_id');
    }

    /**
     * @return HasMany<HomeworkSubmissionFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(HomeworkSubmissionFile::class)->orderBy('position');
    }
}
