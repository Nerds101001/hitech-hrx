<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveType;
use App\Enums\Status;

/**
 * Bring the live leave-type list to the owner's canonical set of 4:
 *
 *   1. All Purpose Leave (ALP)  — the single paid pool
 *   2. Short Leave (SHL)        — 1/month, hours-based
 *   3. Half Day Leave (HDL)     — 0.5 day, deducts from ALP
 *   4. Work From Home (WFH)     — functionally identical to HDL: always
 *      0.5 days, attendance marked automatically on approval (see
 *      LeaveRequest::booted()), only the name/label differs (owner spec,
 *      2026-08-13). Superseded design: this type was originally full-day
 *      pay with no attendance/balance effect at all, attendance marked
 *      manually by accounts from a tracking-software report. That design
 *      never went live, so there is no migration concern in replacing it.
 *      leave:ensure-wfh-type is the single source of truth for these flags;
 *      kept here too only so re-running this command cannot recreate the
 *      old, contradictory version if it somehow still exists.
 *
 * Legacy types (CL, SL, PL, UL, ML, PL_PAT) stay INACTIVE, not deleted —
 * historic requests still reference them and PL still holds legacy
 * balances that the ALP fallback drains. The stray active "Comp off"
 * type is deactivated: real comp-off credit already lands in ALP via the
 * Attendance model hook, so the separate type only added confusion.
 *
 * Idempotent — safe to re-run anywhere.
 */
class StandardizeLeaveTypesCommand extends Command
{
    protected $signature = 'leave:standardize-types';
    protected $description = 'Ensure the canonical 4 active leave types (ALP, SHL, HDL, WFH); deactivate the stray Comp off type.';

    public function handle(): int
    {
        // 1. Ensure Work From Home exists and is active.
        $wfh = LeaveType::withTrashed()->where('code', 'WFH')->first();
        if ($wfh) {
            if ($wfh->trashed()) {
                $wfh->restore();
            }
            if ($wfh->status !== Status::ACTIVE) {
                $wfh->status = Status::ACTIVE;
                $wfh->save();
                $this->info('WFH leave type reactivated.');
            } else {
                $this->info('WFH leave type already exists and is active.');
            }
        } else {
            LeaveType::create([
                'name'                   => 'Work From Home',
                'code'                   => 'WFH',
                'is_paid'                => true,
                'is_short_leave'         => false,
                // Reason (user_notes) is already mandatory on every leave
                // application; proof document stays optional per owner spec
                // ("attach the proof OR mention the reason").
                'is_proof_required'      => false,
                'is_carry_forward'       => false,
                'is_split_entitlement'   => false,
                // Matches HDL exactly (see class docblock) — was previously
                // `true` under the superseded full-day design.
                'is_consecutive_allowed' => false,
                'is_strict_rules'        => false,
                'status'                 => Status::ACTIVE,
                'notes'                  => 'Work From Home. Same as Half Day Leave: always 0.5 days, attendance is marked automatically on approval — nothing to capture by hand.',
            ]);
            $this->info('Work From Home (WFH) leave type created.');
        }

        // 2. Deactivate the stray "Comp off" type (site-scoped, code is the
        //    literal string "Comp off"). Historic requests keep working —
        //    only new applications are prevented.
        $compOff = LeaveType::where('code', 'Comp off')->where('status', Status::ACTIVE)->first();
        if ($compOff) {
            $compOff->status = Status::INACTIVE;
            $compOff->save();
            $this->info("Deactivated stray 'Comp off' type (id {$compOff->id}).");
        } else {
            $this->info("No active 'Comp off' type found — nothing to deactivate.");
        }

        // 3. Report the final active set.
        $active = LeaveType::where('status', Status::ACTIVE)->orderBy('id')->get(['id', 'code', 'name']);
        $this->table(['id', 'code', 'name'], $active->toArray());

        return 0;
    }
}
