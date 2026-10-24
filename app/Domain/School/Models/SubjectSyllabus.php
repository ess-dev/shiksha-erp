<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * The textbook, chapter list and assessment of one subject in one class for
 * one academic year. Every section of the class shares it.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $school_class_id
 * @property int $subject_id
 * @property string|null $book
 * @property list<string> $chapters
 * @property string|null $assessment
 * @property int|null $updated_by_staff_id
 * @property Carbon|null $updated_at
 */
class SubjectSyllabus extends Model
{
    use UsesTenantConnection;

    protected $table = 'subject_syllabi';

    /** @var list<string> */
    protected $fillable = ['academic_year_id', 'school_class_id', 'subject_id', 'book', 'chapters', 'assessment', 'updated_by_staff_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['chapters' => 'array'];
    }
}
