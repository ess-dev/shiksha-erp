<?php

namespace App\Http\Requests\Leave;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Who may take which decision is the Action's rule.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject', 'recommend'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
