<?php

namespace App\Domain\Fees\Models;

use App\Domain\Fees\Enums\InvoiceStatus;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * What one student owes for one installment. Never deleted or rewritten
 * once billed; only `paid_paise` moves, when a payment is confirmed.
 *
 * @property int $id
 * @property string $ulid
 * @property int $student_id
 * @property int $academic_year_id
 * @property int $installment_id
 * @property int $fee_group_id
 * @property string $due_on
 * @property list<array{name: string, amount_paise: int}> $heads
 * @property int $gross_paise
 * @property int $concession_paise
 * @property string|null $concession_note
 * @property int $net_paise
 * @property int $paid_paise
 * @property string|null $last_reminded_on
 */
class FeeInvoice extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_invoices';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'student_id', 'academic_year_id', 'installment_id', 'fee_group_id', 'due_on', 'heads',
        'gross_paise', 'concession_paise', 'concession_note', 'net_paise', 'paid_paise', 'last_reminded_on',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['due_on' => 'string', 'last_reminded_on' => 'string', 'heads' => 'array'];
    }

    public function balancePaise(): int
    {
        return max(0, $this->net_paise - $this->paid_paise);
    }

    public function status(?string $today = null): InvoiceStatus
    {
        return self::statusFor($this->net_paise, $this->paid_paise, $this->due_on, $today ?? CalendarDate::today());
    }

    public static function statusFor(int $netPaise, int $paidPaise, string $dueOn, string $today): InvoiceStatus
    {
        if ($netPaise - $paidPaise <= 0) {
            return InvoiceStatus::Paid;
        }

        if ($dueOn < $today) {
            return $paidPaise > 0 ? InvoiceStatus::PartialOverdue : InvoiceStatus::Overdue;
        }

        if ($paidPaise > 0) {
            return InvoiceStatus::Partial;
        }

        $horizon = (new DateTimeImmutable($today))->modify('+'.InvoiceStatus::DUE_SOON_DAYS.' days')->format('Y-m-d');

        return $dueOn <= $horizon ? InvoiceStatus::Due : InvoiceStatus::Upcoming;
    }
}
