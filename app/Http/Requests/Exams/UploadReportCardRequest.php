<?php

namespace App\Http\Requests\Exams;

use App\Domain\Exams\Actions\UploadReportCard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UploadReportCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reportcards.prepare');
    }

    /**
     * The type is checked from the file's content (mimetypes), not its name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:26'],
            'student' => ['required', 'string', 'max:26'],
            'file' => ['required', 'file', 'max:'.(UploadReportCard::MAX_BYTES / 1024), 'mimetypes:'.implode(',', array_keys(UploadReportCard::ALLOWED_TYPES))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimetypes' => __('Upload a PDF, JPEG or PNG file of at most 5 MB.'),
            'file.max' => __('Upload a PDF, JPEG or PNG file of at most 5 MB.'),
        ];
    }
}
