<?php

namespace App\Http\Requests\Admissions;

use App\Domain\Admissions\Actions\UploadAdmissionDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadAdmissionDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('students.documents');
    }

    /**
     * The type is checked from the file's content (mimetypes), not its name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(UploadAdmissionDocument::MAX_BYTES / 1024), 'mimetypes:'.implode(',', array_keys(UploadAdmissionDocument::ALLOWED_TYPES))],
        ];
    }
}
