<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\SchoolSettingsData;
use App\Domain\School\Models\SchoolSetting;

class SchoolSettingsQuery
{
    public const ADMISSION_PREFIX = 'students.admission_prefix';

    public const TC_PREFIX = 'students.tc_prefix';

    public const ADMISSION_DOCUMENT_RULES = 'admissions.document_rules';

    public const ATTENDANCE_WORKING_DAYS = 'attendance.working_days';

    public const ATTENDANCE_CORRECTION_DAYS = 'attendance.correction_window_days';

    public const GRADE_SCALE = 'exams.grade_scale';

    public const REPORT_CARD_APPROVAL = 'exams.report_card_approval';

    /** illustrative — verify: schools choose their own prefixes. */
    private const DEFAULTS = [
        self::ADMISSION_PREFIX => 'ADM',
        self::TC_PREFIX => 'TC',
    ];

    /** illustrative — verify: Monday to Friday, as in the prototype; many schools also work Saturdays. */
    private const DEFAULT_WORKING_DAYS = [1, 2, 3, 4, 5];

    /** illustrative — verify: the prototype locks a register after 7 days. */
    private const DEFAULT_CORRECTION_DAYS = 7;

    /**
     * The weekdays the school is open, as ISO numbers (1 Monday to 7 Sunday).
     *
     * @return list<int>
     */
    public function attendanceWorkingDays(): array
    {
        $value = SchoolSetting::query()->where('key', self::ATTENDANCE_WORKING_DAYS)->value('value');

        if (! is_array($value) || $value === []) {
            return self::DEFAULT_WORKING_DAYS;
        }

        return array_values(array_map(intval(...), $value));
    }

    /**
     * How many days after a school day its class teacher may still change the
     * register (with a reason once the day has passed).
     */
    public function attendanceCorrectionWindowDays(): int
    {
        $value = SchoolSetting::query()->where('key', self::ATTENDANCE_CORRECTION_DAYS)->value('value');

        return is_int($value) && $value >= 0 ? $value : self::DEFAULT_CORRECTION_DAYS;
    }

    public function admissionPrefix(): string
    {
        return $this->string(self::ADMISSION_PREFIX);
    }

    public function tcPrefix(): string
    {
        return $this->string(self::TC_PREFIX);
    }

    /**
     * Which admission documents a school requires: document kind => null (every
     * class) or a list of class ulids. Null when the school never saved its
     * own rules (the Admissions module then applies its defaults).
     *
     * @return array<string, list<string>|null>|null
     */
    public function admissionDocumentRules(): ?array
    {
        $value = SchoolSetting::query()->where('key', self::ADMISSION_DOCUMENT_RULES)->value('value');

        return is_array($value) ? $value : null;
    }

    /**
     * The school's own grading scale as saved (rows of grade, min_percent and
     * grade_point), or null when it never saved one. Exams checks and applies it.
     *
     * @return array<mixed>|null
     */
    public function gradeScale(): ?array
    {
        $value = SchoolSetting::query()->where('key', self::GRADE_SCALE)->value('value');

        return is_array($value) ? $value : null;
    }

    /**
     * Whether a class teacher's report cards wait for approval before parents
     * see them. On unless the school switched it off (as in the prototype).
     */
    public function reportCardApproval(): bool
    {
        $value = SchoolSetting::query()->where('key', self::REPORT_CARD_APPROVAL)->value('value');

        return ! is_bool($value) || $value;
    }

    public function data(): SchoolSettingsData
    {
        return new SchoolSettingsData($this->admissionPrefix(), $this->tcPrefix());
    }

    private function string(string $key): string
    {
        $value = SchoolSetting::query()->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : self::DEFAULTS[$key];
    }
}
