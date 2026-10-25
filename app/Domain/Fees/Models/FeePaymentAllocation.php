<?php

namespace App\Domain\Fees\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * How much of a payment went to one invoice.
 *
 * @property int $id
 * @property int $fee_payment_id
 * @property int $fee_invoice_id
 * @property int $amount_paise
 */
class FeePaymentAllocation extends Model
{
    use UsesTenantConnection;

    protected $table = 'fee_payment_allocations';

    /** @var list<string> */
    protected $fillable = ['fee_payment_id', 'fee_invoice_id', 'amount_paise'];
}
