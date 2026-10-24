<?php

namespace App\Http\Requests\Exams;

use App\Domain\Exams\Actions\ReopenMarkSheet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReopenMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('marks.reopen');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:'.ReopenMarkSheet::MAX_REASON_LENGTH]];
    }
}
