<?php

namespace App\Domain\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Which fee group a class uses in an academic year.
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $school_class_id
 * @property int $fee_group_id
 */
class FeeGroupClass extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_group_classes';

    /** @var list<string> */
    protected $fillable = ['academic_year_id', 'school_class_id', 'fee_group_id'];
}
