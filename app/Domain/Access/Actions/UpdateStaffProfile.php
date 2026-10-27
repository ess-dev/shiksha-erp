<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Staff;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Actions\UpdateIdentityContact;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\PhoneNumber;
use InvalidArgumentException;

/**
 * A member of staff edits their own profile: the sign-in mobile (on the
 * identity) and the details the school keeps on the staff record. Name, work
 * email and work details are changed by the school, not here.
 */
class UpdateStaffProfile extends Action
{
    /** Fields a member of staff may change about themselves. */
    public const FIELDS = [
        'personal_email', 'qualifications', 'languages', 'home_address', 'about',
        'emergency_name', 'emergency_relation', 'emergency_phone',
    ];

    public function __construct(private readonly UpdateIdentityContact $updateContact) {}

    /**
     * The audit entry lists which fields changed, never their values.
     *
     * @param  array<string, string|null>  $details  keys from FIELDS; missing keys are left alone
     */
    public function handle(Identity $identity, ?string $phone, array $details): Staff
    {
        $staff = Staff::query()->where('identity_ulid', $identity->ulid)->firstOrFail();

        $e164 = $phone === null || trim($phone) === ''
            ? null
            : (PhoneNumber::tryParse($phone)->e164 ?? throw new InvalidArgumentException('Invalid phone number.'));

        $values = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $details)) {
                continue;
            }

            $value = $details[$field] === null ? null : trim($details[$field]);
            $value = $value === '' ? null : $value;

            if ($field === 'emergency_phone' && $value !== null) {
                $value = PhoneNumber::tryParse($value)->e164 ?? throw new InvalidArgumentException('Invalid phone number.');
            }

            if ($field === 'personal_email' && $value !== null) {
                $value = strtolower($value);
            }

            $values[$field] = $value;
        }

        return $this->transaction(function () use ($identity, $staff, $e164, $values) {
            $changed = array_keys(array_filter($values, fn ($value, $field) => $staff->{$field} !== $value, ARRAY_FILTER_USE_BOTH));

            $staff->forceFill($values)->save();

            $this->audit(new AuditEntry('staff.profile_updated', 'staff', $staff->ulid, null, ['changed' => $changed]));

            if ($e164 !== null && $e164 !== $identity->phone_e164) {
                $this->updateContact->handle($identity, $identity->email, $e164);
            }

            return $staff;
        });
    }
}
