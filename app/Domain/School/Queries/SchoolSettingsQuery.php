<?php

namespace App\Domain\School\Queries;

use App\Domain\School\Data\SchoolSettingsData;
use App\Domain\School\Models\SchoolSetting;

class SchoolSettingsQuery
{
    public const ADMISSION_PREFIX = 'students.admission_prefix';

    public const TC_PREFIX = 'students.tc_prefix';

    public const ADMISSION_DOCUMENT_RULES = 'admissions.document_rules';

    /** illustrative — verify: schools choose their own prefixes. */
    private const DEFAULTS = [
        self::ADMISSION_PREFIX => 'ADM',
        self::TC_PREFIX => 'TC',
    ];

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
