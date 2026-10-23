<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One school-configurable value, stored as JSON under a dotted key.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 */
class SchoolSetting extends Model
{
    use UsesTenantConnection;

    protected $table = 'school_settings';

    /** @var list<string> */
    protected $fillable = ['key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
