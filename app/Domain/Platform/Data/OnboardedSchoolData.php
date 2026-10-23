<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * The outcome of onboarding a school, for the success screen. Set-password
 * links appear here once and are never stored.
 */
final readonly class OnboardedSchoolData implements JsonSerializable
{
    /**
     * @param  list<array{name: string, email: string, role: string, link: ?string}>  $invites  link is null for someone who already had an account
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public string $address,
        public string $status,
        public string $planName,
        public ?string $trialEndsOn,
        public int $campuses,
        public int $sections,
        public array $invites,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'address' => $this->address,
            'status' => $this->status,
            'plan_name' => $this->planName,
            'trial_ends_on' => $this->trialEndsOn,
            'campuses' => $this->campuses,
            'sections' => $this->sections,
            'invites' => $this->invites,
        ];
    }
}
