<?php

namespace App\Domain\Leave;

use App\Domain\Leave\Enums\StaffLeaveType;
use App\Domain\School\Queries\SchoolSettingsQuery;

/**
 * The school's staff leave allowances: what it saved in its settings, with the
 * illustrative defaults for any type it never set.
 */
class StaffLeavePolicy
{
    public function __construct(private readonly SchoolSettingsQuery $settings) {}

    /**
     * By leave type key: `days` is the yearly allowance in school days (null =
     * as needed), `document_after_days` the length beyond which a certificate
     * is needed (null = never).
     *
     * @return array<string, array{days: int|null, document_after_days: int|null}>
     */
    public function all(): array
    {
        $saved = $this->settings->staffLeavePolicy();
        $policy = [];

        foreach (StaffLeaveType::cases() as $type) {
            $policy[$type->value] = $saved[$type->value]
                ?? ['days' => $type->defaultDays(), 'document_after_days' => $type->defaultDocumentAfterDays()];
        }

        return $policy;
    }
}
