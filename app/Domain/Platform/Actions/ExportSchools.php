<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Platform\Queries\SchoolsQuery;
use App\Domain\Shared\Action;

/**
 * Builds the Schools list as CSV rows and records that it was exported
 * (exports are audited). Includes school contact details, so it is never
 * run without a signed-in Super Admin.
 */
class ExportSchools extends Action
{
    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(private readonly SchoolsQuery $schools) {}

    /**
     * @return list<list<string>> the header row first
     */
    public function handle(?string $status, ?string $planUlid, string $search): array
    {
        return $this->transaction(function () use ($status, $planUlid, $search) {
            $rows = [['School', 'City', 'State', 'Board', 'Campuses', 'Plan', 'Status', 'Students', 'MRR (INR)', 'Since', 'Renews', 'Trial ends', 'Contact', 'Email']];

            foreach ($this->schools->forExport($status, $planUlid, $search) as $entry) {
                $row = $entry['row'];

                $rows[] = [
                    $row->name,
                    $row->city ?? '',
                    $row->state ?? '',
                    $row->board ?? '',
                    (string) $row->campuses,
                    $row->planName ?? '',
                    $row->status,
                    (string) $row->students,
                    $this->rupees($row->mrrPaise),
                    $entry['since_on'] ?? '',
                    $row->renewsOn ?? '',
                    $row->trialEndsOn ?? '',
                    $entry['contact_name'] ?? '',
                    $entry['contact_email'] ?? '',
                ];
            }

            $this->audit(new AuditEntry(
                action: 'platform.schools_exported',
                subjectType: 'school_list',
                after: [
                    'schools' => count($rows) - 1,
                    'status' => $status,
                    'plan' => $planUlid,
                    'has_search' => trim($search) !== '',
                ],
            ));

            return $rows;
        });
    }

    /**
     * Whole paise as "1234.50", without going through a float.
     */
    private function rupees(int $paise): string
    {
        return intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }
}
