<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RunMonthlyAccrual extends Command
{
    protected $signature = 'leave:run-accrual {month} {year} {--dry-run : Preview without saving}';
    protected $description = 'Run monthly leave accrual for a specific month/year. Example: leave:run-accrual 4 2026';

    public function handle()
    {
        $month   = (int) $this->argument('month');
        $year    = (int) $this->argument('year');
        $dryRun  = $this->option('dry-run');
        $monthLabel = Carbon::create($year, $month, 1)->format('F Y');

        $this->info("=== Monthly Leave Accrual: {$monthLabel} ===");
        if ($dryRun) $this->warn("DRY RUN — no changes will be saved.\n");

        // Get ALP leave type
        $alpType = DB::table('leave_types')->where('code', 'ALP')->first();
        if (!$alpType) {
            $this->error("ALP leave type not found.");
            return Command::FAILURE;
        }

        // Get all profile rules for ALP
        $alpRules = DB::table('leave_policy_profile_rules')
            ->where('leave_type_id', $alpType->id)
            ->get()
            ->keyBy('profile_id');

        // Get all active users with a profile that has ALP
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('leave_policy_profile_id')
            ->whereIn('status', ['active'])
            ->whereIn('leave_policy_profile_id', $alpRules->keys()->toArray())
            ->get(['id', 'first_name', 'last_name', 'code', 'leave_policy_profile_id', 'tenant_id']);

        $this->line("Users to process: " . $users->count());
        $this->line('');

        $rows = [];
        $processed = 0;

        foreach ($users as $user) {
            $rule = $alpRules->get($user->leave_policy_profile_id);
            if (!$rule) continue;

            $monthlyGrant = (float)($rule->max_per_month ?? 0);
            if ($monthlyGrant <= 0) continue;

            // Get current balance
            $balance = DB::table('leave_balances')
                ->where('user_id', $user->id)
                ->where('leave_type_id', $alpType->id)
                ->first();

            $currentBalance  = $balance ? (float)$balance->balance : 0;
            $currentUsed     = $balance ? (float)$balance->used : 0;
            $currentAccrued  = $balance ? (float)$balance->accrued_this_year : 0;
            $currentCF       = $balance ? (float)$balance->carry_forward_last_year : 0;

            // Check if this month was already accrued
            // We track via accrued_this_year — if it already includes this month's grant, skip
            // Simple check: if balance already has the grant for this month, skip
            $alreadyAccrued = DB::table('audits')
                ->where('auditable_type', 'App\\Models\\LeaveBalance')
                ->where('auditable_id', $balance?->id ?? 0)
                ->whereRaw("JSON_EXTRACT(new_values, '$.accrued_this_year') IS NOT NULL")
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->exists();

            $newBalance = $currentBalance + $monthlyGrant;
            $newAccrued = $currentAccrued + $monthlyGrant;

            $rows[] = [
                $user->code,
                $user->first_name . ' ' . $user->last_name,
                number_format($currentBalance, 1),
                '+' . number_format($monthlyGrant, 1),
                number_format($newBalance, 1),
                $alreadyAccrued ? '⚠️ Already done' : '✅ Will process',
            ];

            if (!$dryRun && !$alreadyAccrued) {
                if ($balance) {
                    $balanceModel = \App\Models\LeaveBalance::find($balance->id);
                    \App\Models\LeaveBalance::$auditReason = "Earned for {$monthLabel}";
                    $balanceModel->balance           = $newBalance;
                    $balanceModel->accrued_this_year = $newAccrued;
                    $balanceModel->save();
                } else {
                    \App\Models\LeaveBalance::$auditReason = "Earned for {$monthLabel}";
                    \App\Models\LeaveBalance::create([
                        'user_id'               => $user->id,
                        'leave_type_id'         => $alpType->id,
                        'balance'               => $monthlyGrant,
                        'used'                  => 0,
                        'accrued_this_year'     => $monthlyGrant,
                        'carry_forward_last_year' => 0,
                        'tenant_id'             => $user->tenant_id,
                    ]);
                }
                $processed++;
            }
        }

        $this->table(
            ['Emp Code', 'Name', 'Current Bal', 'Grant', 'New Balance', 'Status'],
            $rows
        );

        if (!$dryRun) {
            $this->info("\n✅ Accrual complete. {$processed} users updated.");
        } else {
            $this->warn("\nDry run complete. Run without --dry-run to apply.");
        }

        return Command::SUCCESS;
    }
}
