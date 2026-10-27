<?php

namespace App\Domain\Fees\Models;

use App\Domain\Fees\Enums\RefundMethod;
use App\Domain\Fees\Enums\RefundSource;
use App\Domain\Fees\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Money going back to a family. Never deleted; once decided it is not edited.
 *
 * @property int $id
 * @property string $ulid
 * @property int $student_id
 * @property int|null $fee_payment_id
 * @property int $amount_paise
 * @property string $reason
 * @property RefundSource $source
 * @property int|null $requested_by_staff_id
 * @property string $requested_on
 * @property RefundStatus $status
 * @property int|null $decided_by_staff_id
 * @property Carbon|null $decided_at
 * @property RefundMethod|null $method
 * @property string|null $reference
 * @property string|null $reject_reason
 * @property bool $principal_signed_off
 * @property list<array{invoice: string, amount_paise: int}>|null $applied_to
 */
class FeeRefund extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_refunds';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'student_id', 'fee_payment_id', 'amount_paise', 'reason', 'source', 'requested_by_staff_id',
        'requested_on', 'status', 'decided_by_staff_id', 'decided_at', 'method', 'reference', 'reject_reason',
        'principal_signed_off', 'applied_to',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => RefundSource::class,
            'status' => RefundStatus::class,
            'method' => RefundMethod::class,
            'requested_on' => 'string',
            'decided_at' => 'datetime',
            'principal_signed_off' => 'boolean',
            'applied_to' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
