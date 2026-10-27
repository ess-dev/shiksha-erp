<?php

namespace App\Http\Requests\Settings;

use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /** illustrative — verify: relations offered for the emergency contact. */
    public const RELATIONS = ['Spouse', 'Father', 'Mother', 'Brother', 'Sister', 'Son', 'Daughter', 'Friend', 'Other'];

    /**
     * The sign-in mobile must stay unique across identities. Work email and
     * name are set by the school and are not accepted here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $identity = $this->user();
        $hasEmail = $identity instanceof Identity && $identity->email !== null;

        return [
            'phone' => [
                $hasEmail ? 'nullable' : 'required', 'string',
                $this->phoneIsValid(...),
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
            'personal_email' => ['nullable', 'string', 'email', 'max:255'],
            'qualifications' => ['nullable', 'string', 'max:100'],
            'languages' => ['nullable', 'string', 'max:80'],
            'home_address' => ['nullable', 'string', 'max:200'],
            'about' => ['nullable', 'string', 'max:240'],
            'emergency_name' => ['nullable', 'string', 'min:3', 'max:60', 'required_with:emergency_phone'],
            'emergency_relation' => ['nullable', 'string', 'in:'.implode(',', self::RELATIONS)],
            'emergency_phone' => ['nullable', 'string', $this->phoneIsValid(...), 'required_with:emergency_name'],
        ];
    }

    private function phoneIsValid(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && PhoneNumber::tryParse($value) === null) {
            $fail(__('The phone number is not valid.'));
        }
    }
}
