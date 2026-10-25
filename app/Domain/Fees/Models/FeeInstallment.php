<?php

namespace App\Domain\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One installment (term) of an academic year's fee schedule, the same for every fee group.
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property int $position
 * @property string $label
 * @property string $due_on
 * @property string $ends_on
 */
class FeeInstallment extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_installments';

    /** @var list<string> */
    protected $fillable = ['ulid', 'academic_year_id', 'position', 'label', 'due_on', 'ends_on'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['due_on' => 'string', 'ends_on' => 'string'];
    }
}
