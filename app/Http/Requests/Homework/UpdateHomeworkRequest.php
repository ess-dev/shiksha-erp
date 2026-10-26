<?php

namespace App\Http\Requests\Homework;

use App\Domain\Homework\Enums\SubmissionType;
use App\Domain\Homework\HomeworkFileStore;
use App\Domain\Homework\HomeworkRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateHomeworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('homework.assign');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.HomeworkRules::TITLE_MAX],
            'instructions' => ['nullable', 'string', 'max:'.HomeworkRules::INSTRUCTIONS_MAX],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'submission_type' => ['required', Rule::enum(SubmissionType::class)],
            'files' => ['nullable', 'array', 'max:'.HomeworkFileStore::MAX_FILES],
            'files.*' => ['file', 'max:'.(HomeworkFileStore::MAX_BYTES / 1024)],
            'remove_files' => ['nullable', 'array'],
            'remove_files.*' => ['string', 'max:26'],
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function attachments(): array
    {
        return array_values(array_filter((array) $this->file('files'), fn ($file) => $file instanceof UploadedFile));
    }

    /**
     * @return list<string>
     */
    public function removedFiles(): array
    {
        return array_values(array_map('strval', (array) $this->input('remove_files', [])));
    }
}
