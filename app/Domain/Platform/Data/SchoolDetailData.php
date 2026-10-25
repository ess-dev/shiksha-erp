<?php

namespace App\Domain\Platform\Data;

use JsonSerializable;

/**
 * One school as the School details screen shows it. Money stays in paise;
 * the internal id never leaves the server.
 */
final readonly class SchoolDetailData implements JsonSerializable
{
    /**
     * @param  list<array{key: string, label: string, enabled: bool, badge: ?string}>  $modules  badge: "in_plan", "add_on", "turned_off" or null
     * @param  list<array{key: string, label: string}>  $gatewayOptions  the payment gateways that can be chosen in this environment
     * @param  list<array{ulid: string, name: string, price_per_student_year_paise: int}>  $plans
     * @param  list<array{key: string, label: string}>  $suspensionReasons
     * @param  list<array{summary: string, at: string}>  $activity
     */
    public function __construct(
        public string $ulid,
        public string $name,
        public ?string $city,
        public ?string $state,
        public ?string $board,
        public int $campuses,
        public ?string $sinceOn,
        public string $status,
        public ?string $suspendedReason,
        public ?string $trialEndsOn,
        public ?string $renewsOn,
        public ?string $planUlid,
        public ?int $planMinStudents,
        public int $students,
        public int $billableStudents,
        public ?int $annualPaise,
        public int $mrrPaise,
        public ?string $contactName,
        public ?string $contactEmail,
        public array $modules,
        public array $plans,
        public array $suspensionReasons,
        public array $activity,
        public string $timezone,
        public ?string $paymentGateway = null,
        public array $gatewayOptions = [],
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
            'since_on' => $this->sinceOn,
            'status' => $this->status,
            'suspended_reason' => $this->suspendedReason,
            'trial_ends_on' => $this->trialEndsOn,
            'renews_on' => $this->renewsOn,
            'plan_ulid' => $this->planUlid,
            'plan_min_students' => $this->planMinStudents,
            'students' => $this->students,
            'billable_students' => $this->billableStudents,
            'annual_paise' => $this->annualPaise,
            'mrr_paise' => $this->mrrPaise,
            'contact_name' => $this->contactName,
            'contact_email' => $this->contactEmail,
            'modules' => $this->modules,
            'plans' => $this->plans,
            'suspension_reasons' => $this->suspensionReasons,
            'activity' => $this->activity,
            'timezone' => $this->timezone,
            'payment_gateway' => $this->paymentGateway,
            'gateway_options' => $this->gatewayOptions,
        ];
    }
}
