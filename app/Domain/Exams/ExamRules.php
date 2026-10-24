<?php

namespace App\Domain\Exams;

use App\Domain\Exams\Exceptions\ExamException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolDayQuery;
use App\Domain\Shared\CalendarDate;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The rules a new or changed exam must meet, checked in one place for both
 * Actions: the classes and campuses are real and inside the person's reach,
 * the exam falls on school days, the name is free, and the date sheet only
 * lists examined subjects of the chosen classes, once each, inside the dates.
 */
class ExamRules
{
    public function __construct(
        private readonly ExamReach $reach,
        private readonly SchoolDayQuery $days,
    ) {}

    /**
     * @return array{
     *     class_ids: list<int>,
     *     campus_ids: list<int>,
     *     all_classes: bool,
     *     all_campuses: bool,
     *     sheet: list<array{subject_id: int, held_on: string, begins_at: string|null}>
     * }
     */
    public function resolve(Identity $actor, ExamDetails $details, ?Exam $existing, int $academicYearId): array
    {
        $this->checkName($details->name, $existing, $academicYearId);
        $this->checkDates($details, $existing === null);

        $allClasses = array_column($this->reach->classes(), 'id');
        $allCampuses = array_column($this->reach->campuses(), 'id');

        $classIds = $details->allClasses ? $allClasses : $this->idsByUlid($this->reach->classes(), $details->classUlids);
        $campusIds = $details->allCampuses ? $allCampuses : $this->idsByUlid($this->reach->campuses(), $details->campusUlids);

        if ($classIds === [] || $campusIds === []) {
            throw ExamException::noSection();
        }

        if (! $this->reach->covers($this->reach->scopeFor($actor, 'exams.schedule'), $classIds, $campusIds)) {
            throw ExamException::outsideReach();
        }

        if (! $this->reach->hasSection($classIds, $campusIds)) {
            throw ExamException::noSection();
        }

        return [
            'class_ids' => $classIds,
            'campus_ids' => $campusIds,
            'all_classes' => $details->allClasses,
            'all_campuses' => $details->allCampuses,
            'sheet' => $this->resolveSheet($details, $classIds),
        ];
    }

    private function checkName(string $name, ?Exam $existing, int $academicYearId): void
    {
        $taken = Exam::query()
            ->where('academic_year_id', $academicYearId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->where('status', '!=', 'cancelled')
            ->when($existing !== null, fn ($query) => $query->where('id', '!=', $existing?->id))
            ->exists();

        if ($taken) {
            throw ExamException::duplicateName();
        }
    }

    private function checkDates(ExamDetails $details, bool $isNew): void
    {
        try {
            $starts = CalendarDate::parse($details->startsOn);
            $ends = CalendarDate::parse($details->endsOn);
        } catch (InvalidArgumentException) {
            throw ExamException::badDateSheet('Pick real dates.');
        }

        if ($ends < $starts) {
            throw ExamException::badDateSheet('The exam ends before it starts.');
        }

        if ($isNew && $starts < CalendarDate::today()) {
            throw ExamException::pastStart();
        }

        $this->assertSchoolDay($starts);
    }

    private function assertSchoolDay(string $date): void
    {
        $check = $this->days->check($date);

        if (! $check->isSchoolDay) {
            throw ExamException::closedDay($date, (string) $check->reason);
        }
    }

    /**
     * @param  list<int>  $classIds
     * @return list<array{subject_id: int, held_on: string, begins_at: string|null}>
     */
    private function resolveSheet(ExamDetails $details, array $classIds): array
    {
        if ($details->dateSheet === []) {
            return [];
        }

        $examined = DB::connection('tenant')->table('school_class_subject')
            ->join('subjects', 'subjects.id', '=', 'school_class_subject.subject_id')
            ->whereIn('school_class_subject.school_class_id', $classIds)
            ->where('subjects.status', 'active')
            ->where('subjects.type', '!=', 'co_scholastic')
            ->distinct()
            ->pluck('subjects.name', 'subjects.ulid')
            ->all();

        $ids = DB::connection('tenant')->table('subjects')->whereIn('ulid', array_keys($examined))->pluck('id', 'ulid')->all();

        $rows = [];
        $seen = [];

        foreach ($details->dateSheet as $entry) {
            $name = $examined[$entry['subject_ulid']] ?? null;

            if ($name === null) {
                throw ExamException::badDateSheet('A subject on the date sheet is not an examined subject of the chosen classes.');
            }

            if (isset($seen[$entry['subject_ulid']])) {
                throw ExamException::badDateSheet("{$name} is listed twice.");
            }

            $seen[$entry['subject_ulid']] = true;

            try {
                $heldOn = CalendarDate::parse($entry['held_on']);
            } catch (InvalidArgumentException) {
                throw ExamException::badDateSheet("Give {$name} a date.");
            }

            if ($heldOn < $details->startsOn || $heldOn > $details->endsOn) {
                throw ExamException::badDateSheet("{$name} falls outside the exam dates.");
            }

            $check = $this->days->check($heldOn);

            if (! $check->isSchoolDay) {
                throw ExamException::badDateSheet("{$name}: {$heldOn} is closed ({$check->reason}).");
            }

            $time = $entry['begins_at'];

            if ($time !== null && $time !== '' && DateTimeImmutable::createFromFormat('!H:i', $time) === false) {
                throw ExamException::badDateSheet("{$name} has an invalid time.");
            }

            $rows[] = [
                'subject_id' => (int) $ids[$entry['subject_ulid']],
                'held_on' => $heldOn,
                'begins_at' => $time === '' ? null : $time,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{id: int, ulid: string, name: string}>  $options
     * @param  list<string>  $ulids
     * @return list<int>
     */
    private function idsByUlid(array $options, array $ulids): array
    {
        $byUlid = array_column($options, 'id', 'ulid');
        $ids = [];

        foreach (array_unique($ulids) as $ulid) {
            if (! isset($byUlid[$ulid])) {
                throw ExamException::noSection();
            }

            $ids[] = $byUlid[$ulid];
        }

        return $ids;
    }
}
