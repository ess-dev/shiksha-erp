<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileChannelsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'push' => ['sometimes', 'boolean'],
            'sms' => ['sometimes', 'boolean'],
            'whatsapp' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
        ];
    }
}
