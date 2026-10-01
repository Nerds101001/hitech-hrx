<?php

namespace App\Support;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Models\UserDevice;

/**
 * Who the mobile app (the JWT 'api' guard) still lets in.
 *
 * A token used to keep working after its owner left - a terminated employee's
 * phone could still call the API until the token ran out, and the device-ID
 * login (no password) would hand it a fresh 28-day token whenever asked. Now a
 * token only works while its owner is current staff, and the device-ID login
 * needs a phone that has reported in recently; after a month of silence the
 * person signs in with their password again.
 */
class MobileAppAccess
{
    /** Current staff: working, joining, or held on the onboarding training. */
    public const STATUSES = [
        UserAccountStatus::ACTIVE->value,
        UserAccountStatus::ONBOARDING->value,
        UserAccountStatus::ONBOARDING_SUBMITTED->value,
    ];

    /** A phone silent this long must sign in with the password again. */
    public const DEVICE_IDLE_DAYS = 30;

    public static function isCurrentStaff(User $user): bool
    {
        $status = $user->status instanceof \BackedEnum ? $user->status->value : (string) $user->status;

        return in_array($status, self::STATUSES, true);
    }

    public static function deviceIsRecent(UserDevice $device): bool
    {
        return $device->updated_at !== null
            && $device->updated_at->gte(now()->subDays(self::DEVICE_IDLE_DAYS));
    }
}
