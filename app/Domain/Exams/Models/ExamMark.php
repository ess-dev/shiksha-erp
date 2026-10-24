<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\MarkValue;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One student's marks in one subject of an exam, in whole half-marks, or
 * absent. A blank mark has no row.
 *
 * @property int $id
 * @property int $exam_id
 * @property int $student_id
 * @property int $subject_id
 * @property int $section_id The section the student was in when the marks were entered
 * @property int|null $half_marks
 * @property bool $is_absent
 * @property int|null $entered_by_staff_id
 */
class ExamMark extends Model
{
    use UsesTenantConnection;

    protected $table = 'exam_marks';

    /** @var list<string> */
    protected $fillable = ['exam_id', 'student_id', 'subject_id', 'section_id', 'half_marks', 'is_absent', 'entered_by_staff_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['half_marks' => 'integer', 'is_absent' => 'boolean'];
    }

    public function value(): MarkValue
    {
        if ($this->is_absent) {
            return MarkValue::absent();
        }

        return $this->half_marks === null ? MarkValue::blank() : MarkValue::halves($this->half_marks);
    }
}
