<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveType;
use App\Enums\Status;

/**
 * Create the Work From Home (WFH) leave type if it does not already exist.
 *
 * Functionally identical to Half Day Leave (HDL) — same flags, same forced
 * half-day/0.5-day pay treatment — only the name and code differ. Approving a
 * WFH request auto-marks attendance the same way HDL does (see
 * LeaveRequest::booted()), so nothing needs to be captured by hand; it is
 * simply labelled "WFH" instead of "Half Day" throughout, per the owner's
 * spec (2026-08-13).
 *
 * A leave type with code 'WFH' already existed in this codebase from an
 * earlier, different design — full-day pay, no balance/attendance effect at
 * all, attendance marked manually by accounts from a tracking-software
 * report. That design was superseded by this one and never went live (no WFH
 * type has ever existed on production), so there is no migration concern.
 */
class EnsureWfhLeaveTypeCommand extends Command
{
    protected $signature = 'leave:ensure-wfh-type';
    protected $description = 'Create the Work From Home (WFH) leave type if it does not already exist.';

    public function handle(): int
    {
        $existing = LeaveType::withTrashed()->where('code', 'WFH')->first();

        if ($existing) {
            $changed = false;
            if ($existing->trashed()) {
                $existing->restore();
                $changed = true;
            }
            if ($existing->status !== Status::ACTIVE) {
                $existing->status = Status::ACTIVE;
                $changed = true;
            }
            if ($changed) {
                $existing->save();
                $this->info('WFH leave type restored and set to active.');
            } else {
                $this->info('WFH leave type already exists and is active.');
            }
            return 0;
        }

        LeaveType::create([
            'name'                   => 'Work From Home',
            'code'                   => 'WFH',
            'is_paid'                => true,
            'is_short_leave'         => false,
            'is_proof_required'      => false,
            'is_carry_forward'       => false,
            'is_split_entitlement'   => false,
            'is_consecutive_allowed' => false,
            'is_strict_rules'        => false,
            'status'                 => Status::ACTIVE,
            'notes'                  => 'Work From Home. Same as Half Day Leave: always 0.5 days, attendance is marked automatically on approval — nothing to capture by hand.',
        ]);

        $this->info('Work From Home (WFH) leave type created successfully.');
        return 0;
    }
}
