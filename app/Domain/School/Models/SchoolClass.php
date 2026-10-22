<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A grade definition shared by all campuses ("Class 5"). Its level orders
 * classes and is what a wing's class range refers to.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property int $level
 */
class SchoolClass extends Model
{
    use UsesTenantConnection;

    protected $table = 'school_classes';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'level'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'school_class_subject', 'school_class_id', 'subject_id');
    }
}
