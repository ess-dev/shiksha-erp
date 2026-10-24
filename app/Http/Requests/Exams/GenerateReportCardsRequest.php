<?php

namespace App\Http\Requests\Exams;

use App\Domain\Exams\Actions\GenerateReportCards;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GenerateReportCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('reportcards.prepare');
    }

    /**
     * `remarks` maps a student's ulid to the remark, or null for the suggested one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:26'],
            'remarks' => ['required', 'array', 'min:1', 'max:200'],
            'remarks.*' => ['nullable', 'string', 'max:'.GenerateReportCards::MAX_REMARK_LENGTH],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function remarks(): array
    {
        $remarks = [];

        foreach ((array) $this->input('remarks', []) as $studentUlid => $remark) {
            $remarks[(string) $studentUlid] = $remark === null ? null : (string) $remark;
        }

        return $remarks;
    }
}
