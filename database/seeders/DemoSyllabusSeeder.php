<?php

namespace Database\Seeders;

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Section;
use App\Domain\School\Models\SectionSubjectProgress;
use App\Domain\School\Models\Subject;
use App\Domain\School\Models\SubjectSyllabus;
use App\Tenancy\Tenant;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fictional textbooks, chapters and progress for the demo schools, for local
 * development only. Written straight to the tables (not through the Actions,
 * which need a signed-in teacher) and only where nothing exists yet, so it is
 * safe to re-run and never overwrites what a teacher saved.
 * illustrative — verify: book names and chapter titles are placeholders.
 */
class DemoSyllabusSeeder extends Seeder
{
    private const BOOKS = [
        'ENG' => 'Mridang (NCERT)',
        'HIN' => 'Sarangi (NCERT)',
        'MAT' => 'Joyful Mathematics (NCERT)',
        'SCI' => 'Curiosity (NCERT)',
        'SST' => 'Exploring Society (NCERT)',
    ];

    private const ASSESSMENT = 'Periodic tests 20% · Half-yearly 30% · Annual 50%';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo syllabus data must never be seeded in production.');
        }

        foreach (['greenfield', 'riverside'] as $subdomain) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            $tenant?->execute(function () {
                $year = AcademicYear::query()->where('is_current', true)->first();

                if ($year === null) {
                    return;
                }

                foreach (SchoolClass::query()->with('subjects')->orderBy('level')->get() as $class) {
                    foreach ($class->subjects as $subject) {
                        $this->seedClass($year, $class, $subject);
                    }
                }
            });
        }
    }

    private function seedClass(AcademicYear $year, SchoolClass $class, Subject $subject): void
    {
        if (SubjectSyllabus::query()->where(['academic_year_id' => $year->id, 'school_class_id' => $class->id, 'subject_id' => $subject->id])->exists()) {
            return;
        }

        $chapters = array_map(fn (int $number) => "Chapter {$number}", range(1, 8));

        SubjectSyllabus::query()->create([
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'subject_id' => $subject->id,
            'book' => (self::BOOKS[$subject->code] ?? 'Textbook').', '.$class->name,
            'chapters' => $chapters,
            'assessment' => self::ASSESSMENT,
        ]);

        $sections = Section::query()->where('academic_year_id', $year->id)->where('school_class_id', $class->id)->where('status', 'active')->get();

        foreach ($sections as $section) {
            SectionSubjectProgress::query()->firstOrCreate(
                ['section_id' => $section->id, 'subject_id' => $subject->id],
                ['done_chapters' => [0, 1, 2, 3], 'current_chapter' => 4, 'periods_per_week' => 6],
            );
        }
    }
}
