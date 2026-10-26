<?php

namespace App\Domain\Homework\Models;

use App\Domain\Homework\Enums\SubmissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Homework one teacher set for one section and subject. Soft-deleted when the
 * teacher deletes it (the rows stay for the audit trail and for submissions).
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property int $section_id
 * @property int $subject_id
 * @property string $title
 * @property string|null $instructions
 * @property string $assigned_on
 * @property string $due_on
 * @property SubmissionType $submission_type
 * @property int $created_by_staff_id
 * @property Carbon|null $deleted_at
 */
class HomeworkAssignment extends Model
{
    use SoftDeletes;
    use UsesTenantConnection;

    protected $table = 'homework_assignments';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'academic_year_id', 'section_id', 'subject_id', 'title', 'instructions', 'assigned_on', 'due_on',
        'submission_type', 'created_by_staff_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_type' => SubmissionType::class,
            'assigned_on' => 'string',
            'due_on' => 'string',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<HomeworkFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(HomeworkFile::class)->orderBy('position');
    }

    /**
     * @return HasMany<HomeworkSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }
}
