<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * One school as the Schools list shows it. Money stays in paise; the
 * internal id never leaves the server.
 */
final readonly class SchoolRowData implements JsonSerializable
{
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $city,
        public ?string $state,
        public ?string $board,
        public int $campuses,
        public ?string $planName,
        public string $status,
        public int $students,
        public int $mrrPaise,
        public ?string $trialEndsOn,
        public ?string $renewsOn,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'ulid' => $this->ulid,
            'name' => $this->name,
            'city' => $this->city,
            'state' => $this->state,
            'board' => $this->board,
            'campuses' => $this->campuses,
            'plan_name' => $this->planName,
            'status' => $this->status,
            'students' => $this->students,
            'mrr_paise' => $this->mrrPaise,
            'trial_ends_on' => $this->trialEndsOn,
            'renews_on' => $this->renewsOn,
        ];
    }
}
