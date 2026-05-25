<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveType;
use App\Enums\Status;

class EnsureHalfDayLeaveTypeCommand extends Command
{
    protected $signature = 'leave:ensure-half-day-type';
    protected $description = 'Create the Half Day Leave (HDL) leave type if it does not already exist.';

    public function handle(): int
    {
        $existing = LeaveType::withTrashed()->where('code', 'HDL')->first();

        if ($existing) {
            // Restore if soft-deleted
            if ($existing->trashed()) {
                $existing->restore();
                $existing->status = Status::ACTIVE;
                $existing->save();
                $this->info('HDL leave type restored and set to active.');
            } else {
                $this->info('HDL leave type already exists — no action needed.');
            }
            return 0;
        }

        LeaveType::create([
            'name'                   => 'Half Day Leave',
            'code'                   => 'HDL',
            'is_paid'                => true,
            'is_short_leave'         => false,
            'is_proof_required'      => false,
            'is_carry_forward'       => false,
            'is_split_entitlement'   => false,
            'is_consecutive_allowed' => false,
            'is_strict_rules'        => false,
            'status'                 => Status::ACTIVE,
            'notes'                  => 'Half day leave. Always counts as 0.5 days deducted from the Paid Leave pool.',
        ]);

        $this->info('Half Day Leave (HDL) leave type created successfully.');
        return 0;
    }
}
