<?php

namespace App\Http\Requests\Students;

use App\Domain\Students\Queries\StudentImportQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ImportStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('students.import');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.(StudentImportQuery::MAX_BYTES / 1024), 'extensions:csv,txt', 'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => __('That file is over 2 MB.'),
            'file.extensions' => __('Choose a .csv file. In Excel: File, Save as, CSV UTF-8.'),
            'file.mimetypes' => __('Choose a .csv file. In Excel: File, Save as, CSV UTF-8.'),
        ];
    }
}
