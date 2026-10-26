<?php

namespace App\Http\Requests\Homework;

use App\Domain\Homework\HomeworkFileStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class SubmitHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The parent portal checks the child and the homework in the Action; file
     * types are checked there from the content.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'child' => ['nullable', 'string', 'max:26'],
            'files' => ['required', 'array', 'min:1', 'max:'.HomeworkFileStore::MAX_FILES],
            'files.*' => ['file', 'max:'.(HomeworkFileStore::MAX_BYTES / 1024)],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        return array_values(array_filter((array) $this->file('files'), fn ($file) => $file instanceof UploadedFile));
    }
}
