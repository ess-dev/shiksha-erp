<?php

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $code
 * @property string $status
 */
class Subject extends Model
{
    use UsesTenantConnection;

    protected $table = 'subjects';

    /** @var list<string> */
    protected $fillable = ['ulid', 'name', 'code', 'status'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
