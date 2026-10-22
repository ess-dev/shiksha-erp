<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * One module included in one plan (composite key, no timestamps).
 *
 * @property int $plan_id
 * @property string $module_key
 */
class PlanModule extends Model
{
    use UsesLandlordConnection;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'plan_modules';

    /** @var list<string> */
    protected $fillable = ['plan_id', 'module_key'];
}
