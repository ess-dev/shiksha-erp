<?php

namespace App\Http\Requests\Platform;

use App\Tenancy\TenantStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSchoolsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_map(fn (TenantStatus $status) => $status->value, TenantStatus::cases()))],
            'plan' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function status(): ?string
    {
        return $this->filled('status') ? (string) $this->input('status') : null;
    }

    public function planUlid(): ?string
    {
        return $this->filled('plan') ? (string) $this->input('plan') : null;
    }

    public function search(): string
    {
        return (string) $this->input('q', '');
    }
}
