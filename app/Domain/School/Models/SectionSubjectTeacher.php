<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * The teacher chosen for one subject in one section. Without a row the
 * section's class teacher is the automatic subject teacher.
 *
 * @property int $id
 * @property int $section_id
 * @property int $subject_id
 * @property int $staff_id
 */
class SectionSubjectTeacher extends Model
{
    use UsesTenantConnection;

    protected $table = 'section_subject_teachers';

    /** @var list<string> */
    protected $fillable = ['section_id', 'subject_id', 'staff_id'];
}
