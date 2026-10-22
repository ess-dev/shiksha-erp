<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A module the platform sells (admissions, fees, ...). The key is what plans
 * and a school's entitlements refer to.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property int $sort_order
 */
class PlatformModule extends Model
{
    use UsesLandlordConnection;

    protected $table = 'platform_modules';

    /** @var list<string> */
    protected $fillable = ['key', 'label', 'sort_order'];
}
