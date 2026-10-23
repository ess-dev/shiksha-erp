<?php

namespace App\Http\Requests\Settings;

use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Phone or email, at least one; both must stay unique across identities.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $identity = $this->user();

        return [
            'email' => [
                'nullable', 'string', 'email', 'max:255', 'required_without:phone',
                Rule::unique(Identity::class, 'email')->ignore($identity?->getKey()),
            ],
            'phone' => [
                'nullable', 'string', 'required_without:email',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && PhoneNumber::tryParse($value) === null) {
                        $fail(__('The phone number is not valid.'));
                    }
                },
                function (string $attribute, mixed $value, Closure $fail): void {
                    $phone = is_string($value) ? PhoneNumber::tryParse($value) : null;

                    if ($phone !== null && Identity::query()
                        ->where('phone_e164', $phone->e164)
                        ->whereKeyNot($this->user()?->getKey())
                        ->exists()) {
                        $fail(__('The phone number has already been taken.'));
                    }
                },
            ],
        ];
    }
}
