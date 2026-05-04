<?php

namespace App\Console\Commands;

use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Enums\LeaveRequestStatus;
use Carbon\Carbon;

class SendLeaveDailyDigest extends Command
{
    protected $signature = 'leave:daily-digest';
    protected $description = 'Send daily digest of team members on leave';

    public function handle()
    {
        $today = Carbon::today()->toDateString();
        
        // Get all approved leaves for today
        $leavesToday = LeaveRequest::where('status', LeaveRequestStatus::APPROVED)
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->with('user.team')
            ->get();

        if ($leavesToday->isEmpty()) {
            $this->info('No one is on leave today.');
            return;
        }

        // Group by team
        $teamsOnLeave = $leavesToday->groupBy(function($leave) {
            return $leave->user->team_id;
        });

        foreach ($teamsOnLeave as $teamId => $teamLeaves) {
            if (!$teamId) continue;
            
            $team = Team::find($teamId);
            if (!$team) continue;

            // Get all active team members (including those on leave, they should also know who else is out)
            $teamMembers = User::where('team_id', $teamId)
                ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
                ->get();

            if ($teamMembers->isEmpty()) continue;

            // Send notification to all team members
            \Illuminate\Support\Facades\Notification::send($teamMembers, new \App\Notifications\TeamDailyDigest($teamLeaves, $team->name));
        }

        $this->info('Daily digest sent successfully.');
    }
}
