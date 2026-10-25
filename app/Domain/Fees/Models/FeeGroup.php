<?php

namespace App\Domain\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * The fee heads charged per installment to the classes that use this group.
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property string $name
 * @property list<array{name: string, amount_paise: int}> $heads
 */
class FeeGroup extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_groups';

    /** @var list<string> */
    protected $fillable = ['ulid', 'academic_year_id', 'name', 'heads'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['heads' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function termPaise(): int
    {
        return array_sum(array_map(fn (array $head) => (int) $head['amount_paise'], $this->heads));
    }
}
