<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A private note for one student's parents under one subject, in one
 * academic year. Clearing the note deletes the row.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $student_id
 * @property int $subject_id
 * @property string $note
 * @property int|null $updated_by_staff_id
 */
class StudentSubjectNote extends Model
{
    use UsesTenantConnection;

    protected $table = 'student_subject_notes';

    /** @var list<string> */
    protected $fillable = ['academic_year_id', 'student_id', 'subject_id', 'note', 'updated_by_staff_id'];
}
