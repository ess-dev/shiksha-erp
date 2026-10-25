<?php

namespace App\Domain\Fees\Models;

use App\Domain\Fees\Enums\ConcessionKind;
use App\Domain\Fees\Enums\ConcessionType;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A discount on one fee head. `value` is a whole percent, or paise for a fixed amount.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property ConcessionKind $kind
 * @property ConcessionType $value_type
 * @property int $value
 * @property string $applies_to_head
 * @property bool $needs_approval
 */
class FeeConcession extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_concessions';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'kind', 'value_type', 'value', 'applies_to_head', 'needs_approval'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => ConcessionKind::class,
            'value_type' => ConcessionType::class,
            'needs_approval' => 'boolean',
        ];
    }
}
