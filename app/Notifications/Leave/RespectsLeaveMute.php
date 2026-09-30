<?php

namespace App\Notifications\Leave;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Lets someone switch off the company-wide leave broadcast for themselves:
 * notification_preferences.preferences.leave_request = false.
 *
 * NotificationHelper::notifyAdminHR() sends every leave request to every
 * HR-role user. For an HR manager who does not process leave that was 121
 * alerts in a fortnight. The preference table and its 'leave_request' type
 * already existed, but nothing read them -- and the User model has no relation
 * to the table, so NotificationPreferenceHelper::getPreference() always
 * answered "not muted". This reads the row directly.
 *
 * The employee's own manager (or that manager's acting delegate) is never
 * muted: the switch silences the broadcast, not a request that is waiting on
 * them to approve it.
 */
trait RespectsLeaveMute
{
    protected function leaveMutedFor(object $notifiable): bool
    {
        $prefs = DB::table('notification_preferences')
            ->where('user_id', $notifiable->id ?? 0)
            ->value('preferences');
        $prefs = is_string($prefs) ? json_decode($prefs, true) : (array) $prefs;

        return ($prefs['leave_request'] ?? true) === false;
    }

    protected function isApproverOf(object $notifiable, ?User $employee): bool
    {
        $managerId = $employee?->reporting_to_id;
        if (!$managerId) {
            return false;
        }
        if ((int) $notifiable->id === (int) $managerId) {
            return true;
        }
        $delegateId = User::where('id', $managerId)->value('access_delegate_id');

        return $delegateId && (int) $notifiable->id === (int) $delegateId;
    }
}
