<?php

namespace App\Http\Requests\Exams;

use App\Domain\Exams\GradeScale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateGradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('settings.manage');
    }

    /**
     * The order and overlaps are checked by GradeScale.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:'.GradeScale::MIN_GRADES, 'max:'.GradeScale::MAX_GRADES],
            'grades.*.grade' => ['required', 'string', 'max:4'],
            'grades.*.min_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'grades.*.grade_point' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return list<array{grade: string, min_percent: int, grade_point: int}>
     */
    public function grades(): array
    {
        return array_values(array_map(fn (array $row) => [
            'grade' => (string) $row['grade'],
            'min_percent' => (int) $row['min_percent'],
            'grade_point' => (int) $row['grade_point'],
        ], (array) $this->input('grades')));
    }
}
