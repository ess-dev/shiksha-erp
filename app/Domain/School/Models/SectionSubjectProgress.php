<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * How far one section has got in a subject, with the teacher's note for its
 * parents. `done_chapters` holds the positions (from 0) of finished chapters.
 *
 * @property int $id
 * @property int $section_id
 * @property int $subject_id
 * @property list<int> $done_chapters
 * @property int|null $current_chapter
 * @property int|null $periods_per_week
 * @property string|null $note
 * @property int|null $updated_by_staff_id
 * @property Carbon|null $updated_at
 */
class SectionSubjectProgress extends Model
{
    use UsesTenantConnection;

    protected $table = 'section_subject_progress';

    /** @var list<string> */
    protected $fillable = ['section_id', 'subject_id', 'done_chapters', 'current_chapter', 'periods_per_week', 'note', 'updated_by_staff_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['done_chapters' => 'array', 'current_chapter' => 'integer', 'periods_per_week' => 'integer'];
    }
}
