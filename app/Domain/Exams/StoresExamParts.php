<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ExamDateSheetEntry;
use Illuminate\Support\Facades\DB;

/**
 * Writes the classes, campuses and date sheet of an exam from the output of
 * ExamRules::resolve(). Used inside the Create and Update Actions' transactions.
 */
trait StoresExamParts
{
    /**
     * @param  array{class_ids: list<int>, campus_ids: list<int>, all_classes: bool, all_campuses: bool, sheet: list<array{subject_id: int, held_on: string, begins_at: string|null}>}  $resolved
     */
    private function storeParts(Exam $exam, array $resolved): void
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));

        $connection->table('exam_school_class')->where('exam_id', $exam->id)->delete();
        $connection->table('campus_exam')->where('exam_id', $exam->id)->delete();
        ExamDateSheetEntry::query()->where('exam_id', $exam->id)->delete();

        if (! $resolved['all_classes']) {
            $connection->table('exam_school_class')->insert(array_map(
                fn (int $id) => ['exam_id' => $exam->id, 'school_class_id' => $id],
                $resolved['class_ids'],
            ));
        }

        if (! $resolved['all_campuses']) {
            $connection->table('campus_exam')->insert(array_map(
                fn (int $id) => ['exam_id' => $exam->id, 'campus_id' => $id],
                $resolved['campus_ids'],
            ));
        }

        foreach ($resolved['sheet'] as $entry) {
            ExamDateSheetEntry::query()->create(['exam_id' => $exam->id] + $entry);
        }
    }

    /**
     * @param  array{class_ids: list<int>, campus_ids: list<int>, all_classes: bool, all_campuses: bool, sheet: list<array{subject_id: int, held_on: string, begins_at: string|null}>}  $resolved
     * @return array<string, mixed>
     */
    private function summary(Exam $exam, array $resolved): array
    {
        return [
            'name' => $exam->name,
            'type' => $exam->type->value,
            'max_marks' => $exam->max_marks,
            'starts_on' => $exam->starts_on,
            'ends_on' => $exam->ends_on,
            'all_classes' => $resolved['all_classes'],
            'class_count' => count($resolved['class_ids']),
            'all_campuses' => $resolved['all_campuses'],
            'campus_count' => count($resolved['campus_ids']),
            'date_sheet_subjects' => count($resolved['sheet']),
            'show_on_calendar' => $exam->show_on_calendar,
        ];
    }
}
