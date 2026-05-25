<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditLeaveBalances extends Command
{
    protected $signature = 'leave:audit-balances {--fix : Auto-fix mismatches}';
    protected $description = 'Audit all users leave balances against their assigned policy profile';

    public function handle()
    {
        $fix = $this->option('fix');

        $this->info("=== Leave Balance Audit ===");
        $this->line("Checking all active users with a leave policy profile...\n");

        // Get all active users with a profile assigned
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('leave_policy_profile_id')
            ->whereIn('status', ['active', 'onboarding'])
            ->get(['id', 'first_name', 'last_name', 'code', 'email', 'leave_policy_profile_id']);

        $this->line("Total users to audit: " . $users->count());

        // Pre-load all profile rules grouped by profile_id
        $allRules = DB::table('leave_policy_profile_rules as r')
            ->join('leave_types as lt', 'lt.id', '=', 'r.leave_type_id')
            ->select('r.profile_id', 'r.leave_type_id', 'lt.code', 'lt.name')
            ->get()
            ->groupBy('profile_id');

        // Pre-load all leave balances grouped by user_id
        $allBalances = DB::table('leave_balances as lb')
            ->join('leave_types as lt', 'lt.id', '=', 'lb.leave_type_id')
            ->select('lb.user_id', 'lb.leave_type_id', 'lb.id', 'lt.code', 'lt.name', 'lb.balance', 'lb.used')
            ->get()
            ->groupBy('user_id');

        $totalMismatches  = 0;
        $usersWithExtra   = [];  // Has balances NOT in profile
        $usersWithMissing = [];  // Missing balances that SHOULD be in profile
        $cleanUsers       = 0;

        foreach ($users as $user) {
            $profileRules   = $allRules->get($user->leave_policy_profile_id, collect());
            $userBalances   = $allBalances->get($user->id, collect());

            $profileCodes   = $profileRules->pluck('code')->toArray();
            $balanceCodes   = $userBalances->pluck('code')->toArray();

            $extraCodes     = array_diff($balanceCodes, $profileCodes);   // In DB but NOT in profile
            $missingCodes   = array_diff($profileCodes, $balanceCodes);   // In profile but NOT in DB

            if (empty($extraCodes) && empty($missingCodes)) {
                $cleanUsers++;
                continue;
            }

            $totalMismatches++;

            $profile = DB::table('leave_policy_profiles')
                ->where('id', $user->leave_policy_profile_id)
                ->value('name');

            $userName = $user->first_name . ' ' . $user->last_name;

            if (!empty($extraCodes)) {
                $usersWithExtra[] = [
                    'id'      => $user->id,
                    'name'    => $userName,
                    'code'    => $user->code,
                    'profile' => $profile,
                    'extra'   => implode(', ', $extraCodes),
                    'missing' => implode(', ', $missingCodes) ?: '-',
                ];
            }

            if (!empty($missingCodes)) {
                $usersWithMissing[] = [
                    'id'      => $user->id,
                    'name'    => $userName,
                    'code'    => $user->code,
                    'profile' => $profile,
                    'missing' => implode(', ', $missingCodes),
                    'extra'   => implode(', ', $extraCodes) ?: '-',
                ];
            }
        }

        // ── Results ──────────────────────────────────────────────────────────
        $this->info("\n✅ Clean users (no mismatch): {$cleanUsers}");
        $this->warn("⚠️  Users with mismatches   : {$totalMismatches}");

        if ($totalMismatches === 0) {
            $this->info("\nAll leave balances are correctly aligned with policy profiles. 🎉");
            return Command::SUCCESS;
        }

        // Show users with EXTRA balances (old policy leftovers)
        if (!empty($usersWithExtra)) {
            $this->line("\n");
            $this->warn("── Users with EXTRA balance records (old policy leftovers) ──");
            $this->table(
                ['User ID', 'Name', 'Emp Code', 'Current Profile', 'Extra (Stale) Codes', 'Missing Codes'],
                $usersWithExtra
            );
        }

        // Show users with MISSING balances (never initialized)
        if (!empty($usersWithMissing)) {
            $this->line("\n");
            $this->warn("── Users with MISSING balance records (never initialized) ──");
            $this->table(
                ['User ID', 'Name', 'Emp Code', 'Current Profile', 'Missing Codes', 'Extra Codes'],
                $usersWithMissing
            );
        }

        // ── Auto-fix ─────────────────────────────────────────────────────────
        if ($fix) {
            $this->line("\n");
            $this->info("Running smart fix — merging stale balances into ALP...");
            $fixed = 0;
            $skipped = 0;

            // Get ALP leave type id
            $alpType = DB::table('leave_types')->where('code', 'ALP')->first();

            foreach ($users as $user) {
                $profileRules = $allRules->get($user->leave_policy_profile_id, collect());
                $userBalances = $allBalances->get($user->id, collect());

                $profileTypeIds = $profileRules->pluck('leave_type_id')->toArray();
                $balanceTypeIds = $userBalances->pluck('leave_type_id')->toArray();

                $extraTypeIds   = array_diff($balanceTypeIds, $profileTypeIds);
                $missingTypeIds = array_diff($profileTypeIds, $balanceTypeIds);

                $tenantId = DB::table('users')->where('id', $user->id)->value('tenant_id');

                // Step 1: Sum up all stale balance records (CL + PL + SL etc.)
                $mergedBalance = 0;
                $mergedUsed    = 0;
                if (!empty($extraTypeIds)) {
                    $staleRecords = $userBalances->whereIn('leave_type_id', $extraTypeIds);
                    foreach ($staleRecords as $stale) {
                        $mergedBalance += (float)$stale->balance;
                        $mergedUsed    += (float)$stale->used;
                    }

                    // Delete stale records
                    DB::table('leave_balances')
                        ->where('user_id', $user->id)
                        ->whereIn('leave_type_id', $extraTypeIds)
                        ->delete();

                    $this->line("  [{$user->code}] {$user->first_name} {$user->last_name}: Merged stale balance {$mergedBalance} days → ALP");
                    $fixed++;
                }

                // Step 2: For each missing type in profile, create balance record
                foreach ($missingTypeIds as $typeId) {
                    $rule = $profileRules->firstWhere('leave_type_id', $typeId);
                    if (!$rule) continue;

                    // If this is ALP and we have merged balance, use that
                    $isAlp = ($alpType && $typeId == $alpType->id);
                    $startBalance = $isAlp
                        ? max($mergedBalance, (float)($rule->max_per_month ?? 0))
                        : (float)($rule->max_per_month ?? 0);

                    $startUsed = $isAlp ? $mergedUsed : 0;

                    // Check if record already exists (avoid duplicate)
                    $exists = DB::table('leave_balances')
                        ->where('user_id', $user->id)
                        ->where('leave_type_id', $typeId)
                        ->exists();

                    if ($exists) {
                        // Update existing ALP record with merged balance
                        if ($isAlp && $mergedBalance > 0) {
                            DB::table('leave_balances')
                                ->where('user_id', $user->id)
                                ->where('leave_type_id', $typeId)
                                ->update([
                                    'balance'    => DB::raw("balance + {$mergedBalance}"),
                                    'used'       => DB::raw("used + {$mergedUsed}"),
                                    'updated_at' => now(),
                                ]);
                        }
                        continue;
                    }

                    DB::table('leave_balances')->insert([
                        'user_id'                 => $user->id,
                        'leave_type_id'           => $typeId,
                        'balance'                 => $startBalance,
                        'used'                    => $startUsed,
                        'accrued_this_year'       => $startBalance,
                        'carry_forward_last_year' => 0,
                        'tenant_id'               => $tenantId,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);
                }
            }

            $this->info("\n✅ Smart fix complete.");
            $this->info("   {$fixed} users had stale balances merged into ALP.");
            $this->info("   Missing leave types initialized for all users.");
            $this->line("\nRun audit again to verify: php artisan leave:audit-balances");
        } else {
            $this->line("\nRun with --fix flag to auto-correct all mismatches:");
            $this->line("  php artisan leave:audit-balances --fix");
        }

        return Command::SUCCESS;
    }
}
