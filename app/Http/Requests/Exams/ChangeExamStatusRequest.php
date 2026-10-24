<?php

namespace App\Http\Requests\Exams;

use App\Domain\Exams\Enums\ExamStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeExamStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('exams.schedule');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(ExamStatus::class)]];
    }

    public function status(): ExamStatus
    {
        return ExamStatus::from($this->string('status')->toString());
    }
}
