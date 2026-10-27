<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfilePhotoRequest extends FormRequest
{
    /**
     * Type is read from the file's content; the Action checks it again.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048'],
        ];
    }
}
