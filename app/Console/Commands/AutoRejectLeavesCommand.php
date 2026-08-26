<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveRequest;
use Carbon\Carbon;

class AutoRejectLeavesCommand extends Command
{
    protected $signature = 'leave:auto-reject-expired';
    protected $description = 'Auto-reject leave requests pending for more than 48 hours';

    public function handle()
    {
        $expiredTime = Carbon::now()->subHours(48);
        
        /*
         * No is_adjustment filter here. That flag is not a column on
         * leave_requests and never has been - it is a presentation field on the
         * in-memory rows LeaveHistoryService builds, marking a balance
         * adjustment (sourced from the audits table) apart from a real request.
         * Filtering on it threw 'Unknown column' and this command failed on
         * every scheduled run, so no leave was ever auto-rejected. Every row in
         * leave_requests is a real request, so no exclusion is needed.
         */
        $expiredLeaves = LeaveRequest::where('status', 'pending')
            ->where('created_at', '<=', $expiredTime)
            ->get();

        $count = 0;
        foreach ($expiredLeaves as $leave) {
            $leave->update([
                'status' => 'rejected',
                'approval_notes' => 'Auto-rejected: No action taken by manager within 48 hours.',
                'rejected_at' => Carbon::now(),
            ]);
            $count++;
        }

        $this->info("Successfully auto-rejected {$count} pending leave requests.");
    }
}
