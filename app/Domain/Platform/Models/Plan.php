<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A sellable plan (landlord): price per student per year in paise, a minimum
 * billable student count, and the modules it includes.
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property string $name
 * @property string $description
 * @property string|null $badge
 * @property int $price_per_student_year_paise
 * @property int $min_students
 * @property int $sort_order
 */
class Plan extends Model
{
    use UsesLandlordConnection;

    protected $table = 'plans';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'key', 'name', 'description', 'badge',
        'price_per_student_year_paise', 'min_students', 'sort_order',
    ];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<PlanModule, $this>
     */
    public function planModules(): HasMany
    {
        return $this->hasMany(PlanModule::class, 'plan_id');
    }
}
