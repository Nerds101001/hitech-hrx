<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Enums\UserAccountStatus;
use Illuminate\Support\Facades\Log;

class GrantShortLeaveCommand extends Command
{
    protected $signature = 'leave:grant-short-leave
                            {--seed : Seed once for all users without showing monthly reset}
                            {--user= : Grant for a specific user ID only}';

    protected $description = 'Grant / refresh Short Leave (SHL) balance for all active users. Runs monthly.';

    public function handle(): int
    {
        $shl = LeaveType::where('code', 'SHL')->first();

        if (!$shl) {
            $this->error('Short Leave type (code: SHL) not found in the database.');
            return 1;
        }

        $query = User::where('status', UserAccountStatus::ACTIVE->value);

        if ($userId = $this->option('user')) {
            $query->where('id', $userId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No active users found.');
            return 0;
        }

        $granted = 0;
        $skipped = 0;
        $isSeed  = $this->option('seed');

        foreach ($users as $user) {
            // Check if balance already exists and was updated this month
            $balance = LeaveBalance::where('user_id', $user->id)
                ->where('leave_type_id', $shl->id)
                ->first();

            // Skip if already granted this calendar month (prevent double-run)
            if (!$isSeed && $balance && $balance->updated_at && $balance->updated_at->isCurrentMonth()) {
                $skipped++;
                continue;
            }

            if (!$balance) {
                $balance = new LeaveBalance();
                $balance->user_id       = $user->id;
                $balance->leave_type_id = $shl->id;
                $balance->tenant_id     = $user->tenant_id;
            }

            // SHL is non-cumulative: reset to 1 each month, reset used to 0
            $balance->balance              = 1;
            $balance->used                 = 0;
            $balance->accrued_this_year    = ($balance->accrued_this_year ?? 0) + 1;
            $balance->carry_forward_last_year = 0;
            $balance->auditCustomMessage   = $isSeed
                ? 'Short Leave Seed Grant (One-time)'
                : 'Monthly Short Leave Grant (Automated)';

            $balance->save();
            $granted++;
        }

        $this->info("Short Leave granted to {$granted} user(s). Skipped (already granted this month): {$skipped}.");
        Log::info("leave:grant-short-leave — granted={$granted}, skipped={$skipped}, seed={$isSeed}");

        return 0;
    }
}
