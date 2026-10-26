<?php

namespace Database\Seeders;

use App\Domain\Homework\Models\HomeworkAssignment;
use App\Domain\Homework\Models\HomeworkSubmission;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\Section;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fictional homework for the demo schools, for local development only: three
 * assignments for each section that has a class teacher, with a few checked
 * and uploaded hand-ins. Written straight to the tables (not through the
 * Actions, which need a signed-in teacher) and only where a school has no
 * homework yet, so it is safe to re-run. Uploaded work has no files (nothing
 * is written to the disk). illustrative — verify: titles are placeholders.
 */
class DemoHomeworkSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo homework must never be seeded in production.');
        }

        foreach (['greenfield', 'riverside'] as $subdomain) {
            Tenant::query()->where('subdomain', $subdomain)->first()?->execute(function () {
                if (HomeworkAssignment::withTrashed()->exists()) {
                    return;
                }

                $year = AcademicYear::query()->where('is_current', true)->first();

                if ($year === null) {
                    return;
                }

                foreach (Section::query()->where('academic_year_id', $year->id)->where('status', 'active')->whereNotNull('class_teacher_staff_id')->get() as $section) {
                    $this->seedSection($year, $section);
                }
            });
        }
    }

    private function seedSection(AcademicYear $year, Section $section): void
    {
        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $subjects = $connection->table('school_class_subject as link')
            ->join('subjects', 'subjects.id', '=', 'link.subject_id')
            ->where('link.school_class_id', $section->school_class_id)
            ->where('subjects.status', 'active')
            ->orderBy('subjects.id')
            ->pluck('subjects.id')
            ->all();

        if ($subjects === []) {
            return;
        }

        $students = $connection->table('enrollments')->where('section_id', $section->id)->where('is_current', true)->orderBy('roll_number')->pluck('student_id')->all();
        $today = CalendarDate::today();
        $work = [
            ['Fractions — Exercise 7.3, Q1–12', 'Solve in the homework notebook. Show all steps.', 'notebook', -2, 2],
            ['Draw and label a plant cell', 'A4 sheet, coloured pencils.', 'upload', -3, 4],
            ['Diary entry: a day at the fair', '120–150 words.', 'upload', -10, -6],
        ];

        foreach ($work as $index => [$title, $instructions, $type, $assigned, $due]) {
            $assignment = HomeworkAssignment::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'academic_year_id' => $section->academic_year_id,
                'section_id' => $section->id,
                'subject_id' => $subjects[$index % count($subjects)],
                'title' => $title,
                'instructions' => $instructions,
                'assigned_on' => $this->shift($today, $assigned),
                'due_on' => $this->shift($today, $due),
                'submission_type' => $type,
                'created_by_staff_id' => $section->class_teacher_staff_id,
            ]);

            foreach (array_slice($students, 0, $index === 0 ? 3 : 4) as $position => $studentKey) {
                HomeworkSubmission::query()->create([
                    'ulid' => strtolower((string) Str::ulid()),
                    'homework_assignment_id' => $assignment->id,
                    'student_id' => $studentKey,
                    'submitted_on' => $type === 'upload' ? $this->shift($assignment->due_on, $position === 3 ? 1 : 0) : null,
                    'submitted_at' => $type === 'upload' ? now() : null,
                    'checked_on' => $type === 'notebook' ? $today : null,
                    'checked_by_staff_id' => $type === 'notebook' ? $section->class_teacher_staff_id : null,
                    'feedback' => $position === 0 ? 'Good work. Keep it up.' : null,
                    'feedback_by_staff_id' => $position === 0 ? $section->class_teacher_staff_id : null,
                    'feedback_at' => $position === 0 ? now() : null,
                ]);
            }
        }
    }

    private function shift(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }
}
