<?php

namespace App\Http\Requests\Students;

use App\Domain\Students\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LinkSiblingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('students.link_siblings');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sibling' => ['required', 'string', 'max:26', Rule::exists(Student::class, 'ulid')],
        ];
    }

    public function sibling(): Student
    {
        return Student::query()->where('ulid', $this->string('sibling')->toString())->firstOrFail();
    }
}
