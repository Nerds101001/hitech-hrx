<?php

namespace App\Console\Commands;

use App\Mail\TeamDailyDigestMail;
use App\Models\LeaveRequest;
use App\Models\Team;
use App\Models\User;
use App\Enums\LeaveRequestStatus;
use App\Enums\UserAccountStatus;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendLeaveDailyDigest extends Command
{
    protected $signature   = 'leave:daily-digest';
    protected $description = 'Send a single daily digest email (with CC) to each team about who is on leave today';

    public function handle()
    {
        $today = Carbon::today()->toDateString();

        // Get all approved leaves covering today
        $leavesToday = LeaveRequest::where('status', LeaveRequestStatus::APPROVED)
            ->whereDate('from_date', '<=', $today)
            ->whereDate('to_date', '>=', $today)
            ->with(['user.team', 'leaveType'])
            ->get();

        // Get all teams that have at least one active member
        $teams = Team::all();

        if ($teams->isEmpty()) {
            $this->info('No teams found. Skipping digest.');
            return;
        }

        $sentCount = 0;

        foreach ($teams as $team) {
            // Get all active members of this team
            $teamMembers = User::where('team_id', $team->id)
                ->where('status', UserAccountStatus::ACTIVE)
                ->whereNotNull('email')
                ->get();

            if ($teamMembers->isEmpty()) {
                continue;
            }

            // Filter leaves for this team only
            $teamLeaves = $leavesToday->filter(function ($leave) use ($team) {
                return $leave->user && $leave->user->team_id === $team->id;
            })->values();

            // Skip teams with no one on leave (optional — comment out to send to ALL teams daily)
            if ($teamLeaves->isEmpty()) {
                continue;
            }

            // Build to/CC list
            $primary   = $teamMembers->first();
            $ccMembers = $teamMembers->skip(1)->pluck('email')->filter()->values()->toArray();

            try {
                $mail = Mail::to($primary->email);

                if (!empty($ccMembers)) {
                    $mail->cc($ccMembers);
                }

                $mail->send(new TeamDailyDigestMail($teamLeaves, $team->name));
                $sentCount++;

                $this->info("Digest sent for team: {$team->name} ({$teamMembers->count()} members, {$teamLeaves->count()} on leave)");
            } catch (\Exception $e) {
                Log::error("Failed to send daily digest for team {$team->name}: " . $e->getMessage());
                $this->error("Failed for team {$team->name}: " . $e->getMessage());
            }
        }

        $this->info("Daily digest complete. Sent {$sentCount} team email(s).");
    }
}
