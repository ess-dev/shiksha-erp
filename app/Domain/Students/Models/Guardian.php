<?php

namespace App\Domain\Students\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A parent or guardian. One phone number is one guardian record, so a family
 * with several children has one row here linked to each child.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $phone
 * @property string|null $email
 * @property string|null $occupation
 * @property string|null $identity_ulid
 */
class Guardian extends Model
{
    use UsesTenantConnection;

    protected $table = 'guardians';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'phone', 'email', 'occupation', 'identity_ulid'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsToMany<Student, $this, GuardianStudent>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->using(GuardianStudent::class)
            ->withPivot(['relation', 'is_primary', 'has_portal_access']);
    }
}
