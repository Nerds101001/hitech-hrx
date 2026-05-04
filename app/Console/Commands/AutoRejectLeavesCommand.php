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
        
        $expiredLeaves = LeaveRequest::where('status', 'pending')
            ->where('created_at', '<=', $expiredTime)
            ->where('is_adjustment', false)
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
