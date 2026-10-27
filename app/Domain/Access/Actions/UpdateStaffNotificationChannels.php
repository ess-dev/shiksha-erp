<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Models\Staff;
use App\Domain\Access\Queries\StaffProfileQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Tenancy\Tenant;

/**
 * A member of staff chooses how the school reaches them. Saved only: nothing
 * is sent yet (no SMS/WhatsApp/email provider chosen).
 */
class UpdateStaffNotificationChannels extends Action
{
    /**
     * WhatsApp can only be switched on when it is in the school's plan.
     *
     * @param  array<string, bool>  $channels  any of push, sms, whatsapp, email
     */
    public function handle(Identity $identity, array $channels): Staff
    {
        $staff = Staff::query()->where('identity_ulid', $identity->ulid)->firstOrFail();
        $whatsappInPlan = Tenant::current()?->hasModule('whatsapp') ?? false;

        return $this->transaction(function () use ($staff, $channels, $whatsappInPlan) {
            $before = StaffProfileQuery::channelsFor($staff, $whatsappInPlan);
            $after = $before;

            foreach (StaffProfileQuery::CHANNELS as $channel) {
                if (array_key_exists($channel, $channels)) {
                    $after[$channel] = $channel === 'whatsapp' ? $whatsappInPlan && $channels[$channel] : $channels[$channel];
                }
            }

            $staff->forceFill(['notification_channels' => $after])->save();

            $this->audit(new AuditEntry('staff.notification_channels_updated', 'staff', $staff->ulid, $before, $after));

            return $staff;
        });
    }
}
