<?php

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\AttendanceAccess;
use App\Domain\Identity\Models\Identity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveRegisterRequest extends FormRequest
{
    /**
     * The class teacher marks, a corrector changes, the named cover marks
     * while the class teacher is on leave; the Action checks which of them the
     * person is for this class and day.
     */
    public function authorize(): bool
    {
        $identity = $this->user();

        return Gate::any(['attendance.mark', 'attendance.correct'])
            || ($identity instanceof Identity && app(AttendanceAccess::class)->coversAnyClass($identity));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:26'],
            'date' => ['required', 'date_format:Y-m-d'],
            'marks' => ['required', 'array', 'min:1', 'max:500'],
            'marks.*' => ['required', 'string', Rule::in(['present', 'absent', 'late'])],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
