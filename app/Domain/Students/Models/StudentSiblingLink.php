<?php

namespace App\Domain\Students\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A staff-made sibling link between two students whose guardians differ. It
 * only labels them as siblings for staff; it gives parents no extra access.
 * The lower student id is always `student_id`.
 *
 * @property int $id
 * @property int $student_id
 * @property int $sibling_id
 */
class StudentSiblingLink extends Model
{
    use UsesTenantConnection;

    protected $table = 'student_sibling_links';

    /** @var list<string> */
    protected $fillable = ['student_id', 'sibling_id'];
}
