<?php

namespace Database\Seeders;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Bulk retroactive fix for the Short Leave approval bug (see the
 * is_short_leave branch in LeaveRequest::booted()): approval never used to
 * recheck the balance, so is_lwp was whatever was true at submission time,
 * forever. This walks every approved, currently-unpaid Short Leave request,
 * grouped by employee and ordered oldest-first, against that employee's
 * OWN current SHL balance -- exactly the same FIFO method already applied
 * individually to Rajesh Bhardwaj (request 287). Requests that fit within
 * the currently-available balance flip to paid; requests beyond it are left
 * unpaid untouched. Terminated employees are excluded entirely (per explicit
 * scope decision -- e.g. Rajesh Gupta / EMP-014 stays untouched).
 *
 * Idempotent by construction: the selection criteria (is_lwp=1, approved)
 * naturally excludes any request this seeder has already fixed.
 */
class FixStaleShortLeaveBalancesSeeder extends Seeder
{
    public function run(): void
    {
        $shlType = LeaveType::where('code', 'SHL')->first();
        if (!$shlType) {
            $this->command->error('Aborting: no SHL leave type found.');
            return;
        }

        $stale = LeaveRequest::withoutGlobalScopes()
            ->where('is_short_leave', true)
            ->where('is_lwp', true)
            ->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->notTerminated())
            ->orderBy('user_id')
            ->orderBy('from_date')
            ->get();

        $flipped = 0;
        $stillUnpaid = 0;

        foreach ($stale->groupBy('user_id') as $userId => $requests) {
            $balance = LeaveBalance::withoutGlobalScopes()
                ->where('user_id', $userId)
                ->where('leave_type_id', $shlType->id)
                ->first();

            $available = $balance ? ((float) $balance->balance - (float) $balance->used) : 0.0;
            if (!$balance || $available <= 0) {
                $stillUnpaid += $requests->count();
                continue;
            }

            foreach ($requests as $r) {
                $decrement = $r->is_half_day ? 0.5 : 1.0;

                if ($available >= $decrement) {
                    $available -= $decrement;
                    $balance->used = (float) $balance->used + $decrement;

                    $r->withoutEvents(function () use ($r) {
                        $r->updateQuietly(['is_lwp' => false, 'lwp_days' => 0]);
                    });

                    $flipped++;
                    $this->command->info("Flipped request {$r->id} (user {$userId}, {$r->from_date->toDateString()}) to paid.");
                } else {
                    $stillUnpaid++;
                }
            }

            $balance->save();
        }

        $this->command->info("Done. Flipped to paid: {$flipped}. Still genuinely unpaid: {$stillUnpaid}.");
    }
}
