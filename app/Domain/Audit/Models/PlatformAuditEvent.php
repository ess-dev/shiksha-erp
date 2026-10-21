<?php

namespace App\Domain\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * A row in the landlord's append-only platform_audit_events table.
 */
class PlatformAuditEvent extends Model
{
    use UsesLandlordConnection;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
