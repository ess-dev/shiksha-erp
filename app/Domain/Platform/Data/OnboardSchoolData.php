<?php

namespace App\Domain\Platform\Data;

/**
 * Everything the onboarding wizard collects, already validated.
 */
final readonly class OnboardSchoolData
{
    /**
     * @param  'trial'|'paid'  $start
     * @param  list<array{name: string, address: ?string}>  $campuses
     * @param  array{name: string, starts_on: string, ends_on: string}  $academicYear
     * @param  list<array{level: int, name: string, sections: list<string>}>  $classes
     * @param  list<array{name: string, email: string, role: string}>  $staff  role is a role template key or "teacher"
     * @param  list<string>  $moduleKeys
     */
    public function __construct(
        public string $name,
        public string $subdomain,
        public string $board,
        public string $city,
        public string $state,
        public string $contactName,
        public string $contactPhone,
        public string $contactEmail,
        public string $planUlid,
        public string $start,
        public array $campuses,
        public array $academicYear,
        public array $classes,
        public array $staff,
        public array $moduleKeys,
    ) {}
}
