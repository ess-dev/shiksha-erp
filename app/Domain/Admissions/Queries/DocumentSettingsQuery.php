<?php

namespace App\Domain\Admissions\Queries;

use App\Domain\Admissions\AdmissionDocumentRules;
use App\Domain\Admissions\Data\ClassOptionData;
use App\Domain\Admissions\Data\DocumentRuleData;
use App\Domain\Admissions\Data\DocumentSettingsData;
use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\School\Queries\SchoolSettingsQuery;

class DocumentSettingsQuery
{
    public function __construct(
        private readonly AdmissionDocumentRules $rules,
        private readonly SchoolSettingsQuery $settings,
    ) {}

    /**
     * The required-documents rules as the settings card edits them: one row
     * per document kind, required for every class, for chosen classes, or not
     * required.
     */
    public function get(): DocumentSettingsData
    {
        $effective = $this->rules->effective();

        return new DocumentSettingsData(
            rules: array_map(function (AdmissionDocumentKind $kind) use ($effective) {
                $required = array_key_exists($kind->value, $effective);
                $classes = $required ? $effective[$kind->value] : null;

                return new DocumentRuleData(
                    kind: $kind->value,
                    label: $kind->label(),
                    is_required: $required,
                    all_classes: $required && $classes === null,
                    class_ulids: $classes ?? [],
                );
            }, AdmissionDocumentKind::cases()),
            classes: array_map(fn (array $class) => new ClassOptionData($class['ulid'], $class['name'], $class['level']), $this->rules->classes()),
            is_default: $this->settings->admissionDocumentRules() === null,
        );
    }
}
