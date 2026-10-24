<?php

namespace App\Http\Requests\Exams;

use App\Domain\Exams\Enums\ExamType;
use App\Domain\Exams\ExamDetails;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveExamRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'min:3', 'max:60'],
            'type' => ['required', Rule::enum(ExamType::class)],
            'max_marks' => ['required', 'integer', 'min:1', 'max:200'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
            'all_classes' => ['required', 'boolean'],
            'class_ulids' => ['array'],
            'class_ulids.*' => ['string', 'max:40'],
            'all_campuses' => ['required', 'boolean'],
            'campus_ulids' => ['array'],
            'campus_ulids.*' => ['string', 'max:40'],
            'date_sheet' => ['array', 'max:60'],
            'date_sheet.*.subject_ulid' => ['required', 'string', 'max:40'],
            'date_sheet.*.held_on' => ['required', 'date_format:Y-m-d'],
            'date_sheet.*.begins_at' => ['nullable', 'date_format:H:i'],
            'instructions' => ['nullable', 'string', 'max:300'],
            'show_on_calendar' => ['required', 'boolean'],
            'notify_parents' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give it a name.',
            'name.min' => 'Give it a name.',
            'max_marks.*' => 'Whole number from 1 to 200.',
            'starts_on.*' => 'Pick a start date.',
            'ends_on.date_format' => 'Pick a real end date.',
        ];
    }

    public function details(): ExamDetails
    {
        $startsOn = $this->string('starts_on')->toString();
        $endsOn = $this->filled('ends_on') ? $this->string('ends_on')->toString() : $startsOn;

        return new ExamDetails(
            name: trim($this->string('name')->toString()),
            type: ExamType::from($this->string('type')->toString()),
            maxMarks: $this->integer('max_marks'),
            startsOn: $startsOn,
            endsOn: $endsOn,
            allClasses: $this->boolean('all_classes'),
            classUlids: array_values(array_map(strval(...), (array) $this->input('class_ulids', []))),
            allCampuses: $this->boolean('all_campuses'),
            campusUlids: array_values(array_map(strval(...), (array) $this->input('campus_ulids', []))),
            dateSheet: array_values(array_map(fn (array $entry) => [
                'subject_ulid' => (string) $entry['subject_ulid'],
                'held_on' => (string) $entry['held_on'],
                'begins_at' => isset($entry['begins_at']) && $entry['begins_at'] !== '' ? (string) $entry['begins_at'] : null,
            ], (array) $this->input('date_sheet', []))),
            instructions: $this->filled('instructions') ? trim($this->string('instructions')->toString()) : null,
            showOnCalendar: $this->boolean('show_on_calendar'),
            notifyParents: $this->boolean('notify_parents'),
        );
    }
}
