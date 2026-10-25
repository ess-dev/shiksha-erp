<?php

namespace App\Http\Requests\Fees;

use App\Domain\Fees\Enums\GatewayReport;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CheckPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('fees.reconcile');
    }

    /**
     * `report` is what a test gateway should say; real gateways ignore it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'report' => ['nullable', Rule::in(array_map(fn (GatewayReport $report) => $report->value, GatewayReport::cases()))],
        ];
    }

    public function simulatedReport(): ?GatewayReport
    {
        $report = $this->input('report');

        return is_string($report) ? GatewayReport::tryFrom($report) : null;
    }
}
