<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Illuminate\Database\Seeder;

/**
 * Retroactive, one-employee application of the ALP->legacy-PL balance
 * fallback fix (see LeaveRequest::booted()), same pattern as
 * FixDipikaJune3LeaveBalanceSeeder. Rajesh Bhardwaj (user 331) has 3
 * ALP-pooled leave requests that came out fully unpaid at approval time
 * (id 288: 2026-07-03/04, id 430: 2026-07-17/18, id 431: 2026-07-20/21;
 * 2 days each, 6 total) despite currently having 4.5 ALP days + 2.0 legacy
 * "Paid Leave" (PL) days genuinely free. Applied FIFO by leave date across
 * both pools: 288 and 430 fully covered by ALP (4 of 4.5 days), 431 takes
 * the remaining 0.5 ALP day plus 1.5 days from legacy PL. His Short Leave
 * requests (id 287, 472) are untouched here -- that's a separate bug
 * (see the is_short_leave fix in LeaveRequest::booted()) handled and
 * audited independently, not part of this balance-pool fix.
 */
class FixRajeshAlpLeaveBalanceSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 331;

        $alp = LeaveBalance::withoutGlobalScopes()->find(269);
        if (!$alp || $alp->user_id != $userId || (float) $alp->balance !== 10.50 || (float) $alp->used !== 6.00) {
            $this->command->error('Aborting: ALP balance (id 269) does not match expected snapshot (10.50/6.00). No changes made.');
            return;
        }

        $pl = LeaveBalance::withoutGlobalScopes()->find(809);
        if (!$pl || $pl->user_id != $userId || (float) $pl->balance !== 2.00 || (float) $pl->used !== 0.00) {
            $this->command->error('Aborting: legacy PL balance (id 809) does not match expected snapshot (2.00/0.00). No changes made.');
            return;
        }

        $requestIds = [288, 430, 431];
        $requests = LeaveRequest::withoutGlobalScopes()->whereIn('id', $requestIds)->get()->keyBy('id');
        foreach ($requestIds as $id) {
            $r = $requests->get($id);
            if (!$r || $r->user_id != $userId || !$r->is_lwp || (float) $r->lwp_days !== 2.00) {
                $this->command->error("Aborting: leave_request {$id} does not match expected state (Rajesh, is_lwp=1, lwp_days=2.00). No changes made.");
                return;
            }
        }

        $alpAvailable = (float) $alp->balance - (float) $alp->used; // 4.50
        $plAvailable  = (float) $pl->balance - (float) $pl->used;   // 2.00

        foreach ($requestIds as $id) {
            $need = 2.00;

            $fromAlp = min($need, $alpAvailable);
            $alpAvailable -= $fromAlp;
            $need -= $fromAlp;

            $fromPl = min($need, $plAvailable);
            $plAvailable -= $fromPl;
            $need -= $fromPl;

            $alp->used = (float) $alp->used + $fromAlp;
            $pl->used  = (float) $pl->used + $fromPl;

            $requests[$id]->withoutEvents(function () use ($requests, $id, $need) {
                $requests[$id]->updateQuietly([
                    'is_lwp'   => $need > 0,
                    'lwp_days' => $need,
                ]);
            });

            $this->command->info("Request {$id}: {$fromAlp} day(s) from ALP, {$fromPl} day(s) from legacy PL, {$need} day(s) still unpaid.");
        }

        $alp->save();
        $pl->save();

        $this->command->info("Done. ALP used: {$alp->used} / {$alp->balance}. Legacy PL used: {$pl->used} / {$pl->balance}.");
    }
}
