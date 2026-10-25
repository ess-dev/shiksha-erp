<?php

namespace App\Http\Requests\Fees;

use App\Domain\Fees\CounterPayment;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Shared\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CollectFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.collect');
    }

    /**
     * The amount is in whole rupees; leaving it empty collects everything selected.
     * The Action checks the installments, their balances and the person's reach.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student' => ['required', 'string', 'max:26'],
            'invoices' => ['required', 'array', 'min:1', 'max:12'],
            'invoices.*' => ['required', 'string', 'max:26'],
            'amount' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'mode' => ['required', Rule::in(array_map(fn (PaymentMode $mode) => $mode->value, array_filter(PaymentMode::cases(), fn (PaymentMode $mode) => $mode->isCounterMode())))],
            'reference' => ['nullable', 'string', 'max:60'],
            'cheque_number' => ['nullable', 'string', 'max:40'],
            'bank' => ['nullable', 'string', 'max:60'],
            'cheque_date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:300'],
            'received_from' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:80'],
        ];
    }

    public function payment(): CounterPayment
    {
        return new CounterPayment(
            studentUlid: $this->string('student')->toString(),
            invoiceUlids: array_values(array_map(strval(...), (array) $this->input('invoices', []))),
            amountPaise: $this->filled('amount') ? Money::fromRupees($this->integer('amount'))->paise : null,
            mode: PaymentMode::from($this->string('mode')->toString()),
            reference: $this->filled('reference') ? $this->string('reference')->toString() : null,
            chequeNumber: $this->filled('cheque_number') ? $this->string('cheque_number')->toString() : null,
            bank: $this->filled('bank') ? $this->string('bank')->toString() : null,
            chequeDate: $this->filled('cheque_date') ? $this->string('cheque_date')->toString() : null,
            note: $this->filled('note') ? $this->string('note')->toString() : null,
            receivedFrom: $this->filled('received_from') ? $this->string('received_from')->toString() : null,
            idempotencyKey: $this->string('idempotency_key')->toString(),
        );
    }
}
