<?php

namespace App\Domain\Admissions;

use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;

/**
 * Which documents an application for a class must have. A school's own rules
 * (a setting) win; until it saves some, the defaults apply: birth certificate,
 * Aadhaar and photo for every class, transfer certificate for every class
 * except the lowest (illustrative — verify, schools differ).
 */
class AdmissionDocumentRules
{
    public function __construct(
        private readonly SchoolSettingsQuery $settings,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    /**
     * Document kind value => null (every class) or the class ulids it applies to.
     *
     * @return array<string, list<string>|null>
     */
    public function effective(): array
    {
        $saved = $this->settings->admissionDocumentRules();

        if ($saved !== null) {
            return $saved;
        }

        $classes = $this->classes();
        $lowest = $classes === [] ? null : min(array_column($classes, 'level'));

        return [
            AdmissionDocumentKind::BirthCertificate->value => null,
            AdmissionDocumentKind::Aadhaar->value => null,
            AdmissionDocumentKind::TransferCertificate->value => array_values(array_map(
                fn (array $class) => $class['ulid'],
                array_filter($classes, fn (array $class) => $class['level'] !== $lowest),
            )),
            AdmissionDocumentKind::Photo->value => null,
        ];
    }

    /**
     * @return list<AdmissionDocumentKind>
     */
    public function requiredFor(string $classUlid): array
    {
        $rules = $this->effective();
        $required = [];

        foreach (AdmissionDocumentKind::cases() as $kind) {
            if (! array_key_exists($kind->value, $rules)) {
                continue;
            }

            $classes = $rules[$kind->value];

            if ($classes === null || in_array($classUlid, $classes, true)) {
                $required[] = $kind;
            }
        }

        return $required;
    }

    /**
     * Classes that have a section this year (the ones that can take a child), lowest level first.
     *
     * @return list<array{ulid: string, name: string, level: int}>
     */
    public function classes(): array
    {
        $classes = [];

        foreach ($this->sections->currentYear() as $section) {
            $classes[$section->classUlid] = ['ulid' => $section->classUlid, 'name' => $section->className, 'level' => $section->classLevel];
        }

        $classes = array_values($classes);
        usort($classes, fn (array $a, array $b) => $a['level'] <=> $b['level']);

        return $classes;
    }
}
