<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A row in the current school's append-only audit_events table.
 */
class AuditEvent extends Model
{
    use UsesTenantConnection;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'actor_role_keys' => 'array',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are append-only.'));
        static::deleting(fn () => throw new LogicException('Audit events are append-only.'));
    }
}
