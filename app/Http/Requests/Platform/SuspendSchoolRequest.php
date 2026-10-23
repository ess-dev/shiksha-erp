<?php

namespace App\Http\Requests\Platform;

use App\Domain\Platform\Enums\SuspensionReason;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuspendSchoolRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::enum(SuspensionReason::class)],
        ];
    }

    public function reason(): SuspensionReason
    {
        return SuspensionReason::from((string) $this->input('reason'));
    }
}
