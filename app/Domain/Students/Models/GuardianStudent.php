<?php

namespace App\Domain\Students\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * The link between a guardian and a child: how they are related, whether they
 * are the primary contact and whether they have portal / app access.
 *
 * @property int $id
 * @property int $guardian_id
 * @property int $student_id
 * @property string $relation
 * @property bool $is_primary
 * @property bool $has_portal_access
 */
class GuardianStudent extends Pivot
{
    use UsesTenantConnection;

    protected $table = 'guardian_student';

    public $incrementing = true;

    /** @var list<string> */
    protected $fillable = ['guardian_id', 'student_id', 'relation', 'is_primary', 'has_portal_access'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'has_portal_access' => 'boolean'];
    }
}
