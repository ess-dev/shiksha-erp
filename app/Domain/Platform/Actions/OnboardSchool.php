<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Access\Actions\OnboardStaffMember;
use App\Domain\Access\Actions\SetUpSchoolOwner;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Audit\Enums\AuditScope;
use App\Domain\Identity\Actions\CreateIdentity;
use App\Domain\Identity\Actions\GrantMembership;
use App\Domain\Identity\Actions\IssuePasswordLink;
use App\Domain\Identity\Queries\IdentityLookupQuery;
use App\Domain\Platform\Data\OnboardedSchoolData;
use App\Domain\Platform\Data\OnboardSchoolData;
use App\Domain\Platform\Models\Plan;
use App\Domain\School\Actions\SetUpInitialStructure;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use App\Tenancy\ProvisionTenant;
use App\Tenancy\Tenant;
use App\Tenancy\TenantStatus;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Creates a whole school for the Super Admin: its database, subscription,
 * modules, owner and staff, campuses, first academic year and optional
 * classes. If anything fails after the database exists, the school is
 * discarded so the same details can be submitted again.
 *
 * Fees and student import are not part of onboarding: the school's
 * accountant and admin do those once those modules exist.
 */
class OnboardSchool extends Action
{
    public const TEACHER_ROLE = 'teacher';

    protected AuditScope $auditScope = AuditScope::Platform;

    public function __construct(
        private readonly ProvisionTenant $provision,
        private readonly IdentityLookupQuery $identities,
        private readonly CreateIdentity $createIdentity,
        private readonly GrantMembership $grantMembership,
        private readonly IssuePasswordLink $issuePasswordLink,
        private readonly SetUpSchoolOwner $setUpOwner,
        private readonly OnboardStaffMember $onboardStaff,
        private readonly SetUpInitialStructure $setUpStructure,
    ) {}

    public function handle(OnboardSchoolData $data): OnboardedSchoolData
    {
        $plan = Plan::query()->where('ulid', $data->planUlid)->firstOrFail();
        $isTrial = $data->start === 'trial';

        // A database cannot be created inside a transaction, so it comes first;
        // the audit event is written below, in the one transaction that can
        // still be undone, which lets a failure discard the school cleanly.
        $tenant = $this->provision->handle($data->name, $data->subdomain, $isTrial ? TenantStatus::Trial->value : TenantStatus::Active->value, audit: false);

        try {
            return $this->transaction(fn () => $this->setUp($tenant, $plan, $data, $isTrial));
        } catch (Throwable $e) {
            $this->provision->discard($tenant);

            throw $e;
        }
    }

    private function setUp(Tenant $tenant, Plan $plan, OnboardSchoolData $data, bool $isTrial): OnboardedSchoolData
    {
        $today = CalendarDate::today();

        $tenant->forceFill([
            'plan_id' => $plan->id,
            'board' => $data->board,
            'city' => $data->city,
            'state' => $data->state,
            'contact_name' => $data->contactName,
            'contact_email' => $data->contactEmail,
            'contact_phone' => PhoneNumber::tryParse($data->contactPhone)->e164 ?? throw new InvalidArgumentException('Invalid contact phone number.'),
            'campus_count' => count($data->campuses),
            'student_count' => 0,
            'since_on' => $today,
            'trial_ends_on' => $isTrial ? $this->addDays($today, (int) config('shiksha.trial_days')) : null,
            'renews_on' => $isTrial ? null : $data->academicYear['ends_on'],
        ])->save();

        foreach (array_values(array_unique($data->moduleKeys)) as $module) {
            DB::connection(config('multitenancy.landlord_database_connection_name'))->table('tenant_modules')->insert([
                'tenant_id' => $tenant->id, 'module' => $module, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $invites = [];
        $owner = null;
        $members = [];

        foreach ($data->staff as $person) {
            $existing = $this->identities->byEmail($person['email']);
            $identity = $existing ?? $this->createIdentity->handle($person['email'], null, null);

            $this->grantMembership->handle($identity, $tenant);

            $invites[] = [
                'name' => $person['name'],
                'email' => $person['email'],
                'role' => $person['role'],
                'link' => $existing === null ? $this->issuePasswordLink->handle($identity, $tenant) : null,
            ];

            if ($person['role'] === SetUpSchoolOwner::OWNER_TEMPLATE) {
                $this->setUpOwner->handle($tenant, $identity, $person['name']);
                $owner ??= $identity;
            } else {
                $members[] = [$identity, $person];
            }
        }

        if ($owner === null) {
            throw new InvalidArgumentException('A school needs a Principal (owner).');
        }

        foreach ($members as [$identity, $person]) {
            $this->onboardStaff->handle($tenant, $owner, $identity, $person['name'], $person['role'] === self::TEACHER_ROLE ? null : $person['role']);
        }

        $structure = $this->setUpStructure->handle($tenant, $owner, $data->campuses, $data->academicYear, $data->classes);

        $this->audit(new AuditEntry(
            action: 'platform.school_onboarded',
            subjectType: 'school',
            subjectUlid: $tenant->ulid,
            after: [
                'name' => $tenant->name,
                'subdomain' => $tenant->subdomain,
                'plan' => $plan->key,
                'status' => $tenant->status->value,
                'campuses' => $structure['campuses'],
                'sections' => $structure['sections'],
                'staff' => count($data->staff),
                'modules' => count($data->moduleKeys),
            ],
        ), $tenant->id);

        $tenant->refresh();

        return new OnboardedSchoolData(
            ulid: $tenant->ulid,
            name: $tenant->name,
            address: $tenant->subdomain.'.'.config('shiksha.base_domain'),
            status: $tenant->status->value,
            planName: $plan->name,
            trialEndsOn: $tenant->trial_ends_on,
            campuses: $structure['campuses'],
            sections: $structure['sections'],
            invites: $invites,
        );
    }

    private function addDays(string $date, int $days): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify("+{$days} days")->format('Y-m-d');
    }
}
