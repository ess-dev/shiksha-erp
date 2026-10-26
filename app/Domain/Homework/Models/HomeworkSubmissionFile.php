<?php

namespace App\Domain\Homework\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A photo or document a guardian uploaded as their child's homework.
 *
 * @property int $id
 * @property string $ulid
 * @property int $homework_submission_id
 * @property string $file_path
 * @property string $file_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $position
 */
class HomeworkSubmissionFile extends Model
{
    use UsesTenantConnection;

    protected $table = 'homework_submission_files';

    /** @var list<string> */
    protected $fillable = ['ulid', 'homework_submission_id', 'file_path', 'file_name', 'mime_type', 'size_bytes', 'position'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<HomeworkSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(HomeworkSubmission::class, 'homework_submission_id');
    }
}
