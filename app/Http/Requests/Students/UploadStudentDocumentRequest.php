<?php

namespace App\Http\Requests\Students;

use App\Domain\Students\Actions\UploadStudentDocument;
use App\Domain\Students\Enums\DocumentKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UploadStudentDocumentRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(DocumentKind::class)],
            'file' => ['required', 'file', 'max:'.(UploadStudentDocument::MAX_BYTES / 1024), 'mimetypes:'.implode(',', array_keys(UploadStudentDocument::ALLOWED_TYPES))],
        ];
    }
}
