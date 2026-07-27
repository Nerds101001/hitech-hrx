<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Illuminate\Database\Seeder;

/**
 * Retroactive, one-employee application of the ALP->legacy-PL balance
 * fallback fix (see LeaveRequest::booted()). Dipika Das (user 250) has 1
 * unused day sitting in the now-inactive "Paid Leave" (PL) type. Applied
 * FIFO to her oldest outstanding unpaid ALP-pooled leave request first:
 * leave_request 60 (2026-06-03, exactly 1 day, currently is_lwp=1/lwp_days=1)
 * is fully covered by that 1 day and flips to paid. Nothing else changes --
 * every other unpaid instance (June 6/8-10/11, July 3, July 13-14) remains
 * unpaid because no balance is left after covering this one day. Short
 * Leave requests (id 140, 308) are untouched -- they're on their own
 * separate, excluded balance pool and were already correct.
 */
class FixDipikaJune3LeaveBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $leaveRequest = LeaveRequest::withoutGlobalScopes()->find(60);
        if (!$leaveRequest || $leaveRequest->user_id != 250 || (float) $leaveRequest->lwp_days !== 1.0) {
            $this->command->error('Aborting: leave_request 60 does not match expected state (Dipika, 1.0 lwp_days). No changes made.');
            return;
        }

        $plBalance = LeaveBalance::withoutGlobalScopes()
            ->where('user_id', 250)
            ->where('leave_type_id', 3) // legacy "Paid Leave" (PL)
            ->first();

        if (!$plBalance || ((float) $plBalance->balance - (float) $plBalance->used) < 1.0) {
            $this->command->error('Aborting: PL balance is not >= 1.0 available. No changes made.');
            return;
        }

        $plBalance->used = (float) $plBalance->used + 1.0;
        $plBalance->save();

        $leaveRequest->withoutEvents(function () use ($leaveRequest) {
            $leaveRequest->updateQuietly([
                'is_lwp'   => false,
                'lwp_days' => 0,
            ]);
        });

        $this->command->info('Leave request 60 (Dipika Das, 2026-06-03) flipped to paid; PL balance used 0.00 -> 1.00.');
    }
}
