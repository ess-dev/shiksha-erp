<?php

namespace App\Domain\Exams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * The marks of one subject in one section for an exam, as a whole: whether
 * parents can see them and who last reopened them. Created the first time
 * marks are saved.
 *
 * @property int $id
 * @property int $exam_id
 * @property int $section_id
 * @property int $subject_id
 * @property Carbon|null $published_at
 * @property int|null $published_by_staff_id
 * @property Carbon|null $reopened_at
 * @property int|null $reopened_by_staff_id
 * @property string|null $reopen_reason
 */
class ExamMarkSheet extends Model
{
    use UsesTenantConnection;

    protected $table = 'exam_mark_sheets';

    /** @var list<string> */
    protected $fillable = [
        'exam_id', 'section_id', 'subject_id', 'published_at', 'published_by_staff_id',
        'reopened_at', 'reopened_by_staff_id', 'reopen_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'reopened_at' => 'datetime'];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
