<?php

namespace App\Domain\Fees\Models;

use App\Domain\Fees\Enums\PaymentChannel;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * Money received (or attempted) for one or more installments. Never deleted.
 *
 * @property int $id
 * @property string $ulid
 * @property int $student_id
 * @property PaymentMode $mode
 * @property PaymentStatus $status
 * @property PaymentChannel $channel
 * @property int $amount_paise
 * @property Carbon $paid_at
 * @property string|null $reference
 * @property string|null $gateway
 * @property string|null $receipt_number
 * @property int|null $collected_by_staff_id
 * @property string|null $received_from
 * @property string|null $note
 * @property string|null $failure_reason
 * @property array<string, mixed>|null $details
 * @property string|null $idempotency_key
 * @property bool $recovered_on_recheck
 * @property Carbon|null $settled_at
 * @property Carbon|null $link_sent_at
 */
class FeePayment extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_payments';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'student_id', 'mode', 'status', 'channel', 'amount_paise', 'paid_at', 'reference', 'gateway',
        'receipt_number', 'collected_by_staff_id', 'received_from', 'note', 'failure_reason', 'details',
        'idempotency_key', 'recovered_on_recheck', 'settled_at', 'link_sent_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'mode' => PaymentMode::class,
            'status' => PaymentStatus::class,
            'channel' => PaymentChannel::class,
            'paid_at' => 'datetime',
            'details' => 'array',
            'recovered_on_recheck' => 'boolean',
            'settled_at' => 'datetime',
            'link_sent_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<FeePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class, 'fee_payment_id');
    }
}
