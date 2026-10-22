<?php

namespace App\Domain\Students\Models;

use App\Domain\Students\Enums\EnrollmentResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A student's place in one section for part of an academic year. A section
 * change closes the current row and opens a new one, so earlier attendance and
 * marks keep pointing at the old section.
 *
 * @property int $id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int $section_id
 * @property int|null $roll_number
 * @property string $started_on
 * @property string|null $ended_on
 * @property EnrollmentResult $result
 * @property string|null $note
 * @property bool $is_current
 */
class Enrollment extends Model
{
    use UsesTenantConnection;

    protected $table = 'enrollments';

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'academic_year_id', 'section_id', 'roll_number', 'started_on', 'ended_on', 'result', 'note', 'is_current',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'result' => EnrollmentResult::class,
            'is_current' => 'boolean',
            'started_on' => 'string',
            'ended_on' => 'string',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
