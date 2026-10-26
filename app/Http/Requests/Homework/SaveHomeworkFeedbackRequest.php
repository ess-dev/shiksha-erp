<?php

namespace App\Http\Requests\Homework;

use App\Domain\Homework\HomeworkRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveHomeworkFeedbackRequest extends FormRequest
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
            'feedback' => ['required', 'array', 'max:200'],
            'feedback.*' => ['nullable', 'string', 'max:'.HomeworkRules::FEEDBACK_MAX],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function feedback(): array
    {
        $feedback = [];

        foreach ((array) $this->input('feedback') as $student => $text) {
            $feedback[(string) $student] = $text === null ? null : (string) $text;
        }

        return $feedback;
    }
}
