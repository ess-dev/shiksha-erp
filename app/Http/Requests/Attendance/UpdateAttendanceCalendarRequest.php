<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateAttendanceCalendarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('settings.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'working_days' => ['required', 'array', 'min:1', 'max:7'],
            'working_days.*' => ['integer', 'between:1,7'],
            'correction_window_days' => ['required', 'integer', 'between:0,60'],
        ];
    }
}
