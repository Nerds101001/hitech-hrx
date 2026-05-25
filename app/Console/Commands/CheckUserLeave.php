<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckUserLeave extends Command
{
    protected $signature = 'leave:check-user {name}';
    protected $description = 'Check leave balances and policy for a specific user';

    public function handle()
    {
        $name = $this->argument('name');

        // Find user
        $user = DB::table('users')
            ->whereNull('deleted_at')
            ->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$name}%"])
            ->first();

        if (!$user) {
            $this->error("User '{$name}' not found.");
            return;
        }

        $this->info("=== User Details ===");
        $this->table(['Field', 'Value'], [
            ['ID', $user->id],
            ['Name', $user->first_name . ' ' . $user->last_name],
            ['Email', $user->email],
            ['Code', $user->code],
            ['Status', $user->status],
            ['Leave Policy Profile ID', $user->leave_policy_profile_id ?? 'NOT ASSIGNED'],
        ]);

        // Get assigned profile
        if (!$user->leave_policy_profile_id) {
            $this->warn("No leave policy profile assigned to this user.");
            return;
        }

        $profile = DB::table('leave_policy_profiles')
            ->where('id', $user->leave_policy_profile_id)
            ->first();

        $this->info("\n=== Assigned Leave Policy Profile ===");
        $this->line("Profile: [{$profile->id}] {$profile->name}");

        // Profile rules (what SHOULD be available)
        $rules = DB::table('leave_policy_profile_rules as r')
            ->join('leave_types as lt', 'lt.id', '=', 'r.leave_type_id')
            ->where('r.profile_id', $profile->id)
            ->select('lt.name', 'lt.code', 'r.max_per_month', 'r.max_per_year', 'r.is_carry_forward')
            ->get();

        $this->info("\n=== Leave Types in Profile (Should Have) ===");
        $this->table(['Code', 'Leave Type', '/Month', '/Year', 'Carry Fwd'],
            $rules->map(fn($r) => [$r->code, $r->name, $r->max_per_month ?? '-', $r->max_per_year ?? '-', $r->is_carry_forward ? 'Yes' : 'No'])->toArray()
        );

        // Actual leave balances (what they actually have in DB)
        $balances = DB::table('leave_balances as lb')
            ->join('leave_types as lt', 'lt.id', '=', 'lb.leave_type_id')
            ->where('lb.user_id', $user->id)
            ->select('lt.name', 'lt.code', 'lb.balance', 'lb.used', 'lb.accrued_this_year', 'lb.carry_forward_last_year')
            ->get();

        $this->info("\n=== Actual Leave Balances in DB (What They Have) ===");
        if ($balances->isEmpty()) {
            $this->warn("No leave balance records found for this user.");
        } else {
            $this->table(
                ['Code', 'Leave Type', 'Balance', 'Used', 'Accrued This Year', 'Carry Forward'],
                $balances->map(fn($b) => [$b->code, $b->name, $b->balance, $b->used, $b->accrued_this_year, $b->carry_forward_last_year])->toArray()
            );
        }

        // Check for mismatches
        $profileTypeCodes = $rules->pluck('code')->toArray();
        $balanceTypeCodes = $balances->pluck('code')->toArray();
        $extraBalances = array_diff($balanceTypeCodes, $profileTypeCodes);

        if (!empty($extraBalances)) {
            $this->warn("\n⚠️  MISMATCH DETECTED: User has balance records for leave types NOT in their profile:");
            foreach ($extraBalances as $code) {
                $this->warn("   - {$code}");
            }
            $this->warn("   These are likely from a previous policy profile assignment.");
        } else {
            $this->info("\n✅ No mismatches — balances match the profile.");
        }
    }
}
