<?php

namespace App\Services;

use App\Models\User;
use App\Models\LeaveBalance;
use App\Models\LeavePolicyProfileRule;
use Illuminate\Support\Facades\Log;

class LeaveAccrualService
{
    /**
     * Has this balance already received THIS month's credit?
     *
     * The month is baked into the audit reason itself ("Earned for August 2026",
     * stashed in audits.user_agent by LeaveBalance::transformAudit), so matching
     * the tag alone is exact and timezone-proof — no date range needed.
     *
     * This is what makes a re-run safe: the command is registered twice in the
     * schedule (bootstrap/app.php and routes/console.php) and may also be run by
     * hand, and ALP is carry-forward (balance += grant), so without this guard a
     * second pass silently doubles everyone's leave.
     */
    protected static function alreadyCreditedThisMonth($balanceId, string $monthTag): bool
    {
        if (!$balanceId) return false;

        return \Illuminate\Support\Facades\DB::table('audits')
            ->where('auditable_type', LeaveBalance::class)
            ->where('auditable_id', $balanceId)
            ->where('user_agent', $monthTag)
            ->exists();
    }

    /**
     * Accrue leaves for all users based on their assigned profile policies.
     * Should be run on the 1st of every month.
     *
     * Who gets credited: ACTIVE, non-deleted staff whose leave policy profile
     * has a rule granting a monthly amount. A profile whose rule leaves the
     * monthly columns null (e.g. ALP on the "Even/ODD Saturday Off" profiles)
     * grants nothing and is skipped — that exclusion is intentional policy.
     */
    public static function accrueMonthly()
    {
        // Terminated / onboarding / soft-deleted people must never accrue.
        $users = User::whereNotNull('leave_policy_profile_id')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->get();

        $monthTag = 'Earned for ' . now()->format('F Y');
        $granted  = 0;
        $skipped  = 0;

        // Short Leave is owned exclusively by `leave:grant-short-leave`, which
        // grants it to EVERY active employee (company policy), not just those
        // whose profile happens to carry an SHL rule — and it reaches the ~15
        // active staff who have no leave policy profile at all, whom this
        // profile-driven loop can never see. Granting SHL here as well would
        // mean two commands writing the same balance from two different
        // populations in the same run, so this loop skips SHL entirely.
        $shlTypeId = \App\Models\LeaveType::where('code', 'SHL')->value('id');

        foreach ($users as $user) {
            $profile = $user->leavePolicyProfile;
            if (!$profile) continue;

            $rules = $profile->rules;
            foreach ($rules as $rule) {
                if ($shlTypeId && (int) $rule->leave_type_id === (int) $shlTypeId) {
                    continue;
                }

                // Calculate monthly increment:
                // 1. If it's a short leave, use short_leave_per_month
                // 2. Else use max_per_month or max_per_year/12
                $monthlyIncrement = $rule->short_leave_per_month ?? $rule->max_per_month ?? ($rule->max_per_year ? ($rule->max_per_year / 12) : 0);

                if ($monthlyIncrement <= 0) continue;

                $balance = LeaveBalance::firstOrNew([
                    'user_id'       => $user->id,
                    'leave_type_id' => $rule->leave_type_id,
                    'tenant_id'     => $user->tenant_id,
                ]);

                // Idempotent: already credited for this month → leave it alone.
                if (self::alreadyCreditedThisMonth($balance->id, $monthTag)) {
                    $skipped++;
                    continue;
                }

                $granted++;

                if ($rule->is_carry_forward) {
                    // Carry Forward: Increment the existing balance
                    $balance->balance += $monthlyIncrement;
                    $balance->accrued_this_year += $monthlyIncrement;
                    \App\Models\LeaveBalance::$auditReason = $monthTag;
                } else {
                    // Reset: Lapse any unused balance first
                    if ($balance->exists && $balance->balance > 0) {
                        $balance->balance = 0;
                        \App\Models\LeaveBalance::$auditReason = "Leave lapsed for last month";
                        $balance->save();
                    }

                    // Grant the new monthly quota
                    // Note: We increment 'accrued_this_year' to track annual total
                    $balance->balance = $monthlyIncrement;
                    $balance->used    = 0; 
                    $balance->accrued_this_year += $monthlyIncrement;
                    $balance->carry_forward_last_year = 0;

                    \App\Models\LeaveBalance::$auditReason = $monthTag;
                }

                $balance->save();
            }
        }

        Log::info("Monthly leave accrual ({$monthTag}) — users scanned: " . count($users) . ", credits granted: {$granted}, skipped (already credited this month): {$skipped}.");

        return ['granted' => $granted, 'skipped' => $skipped, 'users' => count($users)];
    }

    /**
     * Apply yearly carry-forward caps (e.g., max 6 days for Paid Leave)
     * Should be run on April 1st before the first accrual of the new fiscal year.
     */
    public static function resetYearlyCarryForward()
    {
        $users = User::whereNotNull('leave_policy_profile_id')->get();

        foreach ($users as $user) {
            $profile = $user->leavePolicyProfile;
            if (!$profile) continue;

            $rules = $profile->rules;
            foreach ($rules as $rule) {
                // Only process rules that have carry forward enabled
                if (!$rule->is_carry_forward) continue;

                // Max 6 days can be carried forward as per user request, fallback if not set in rule
                $cap = $rule->carry_forward_max_days !== null ? (float)$rule->carry_forward_max_days : 6.0;

                $balance = LeaveBalance::where([
                    'user_id'       => $user->id,
                    'leave_type_id' => $rule->leave_type_id,
                    'tenant_id'     => $user->tenant_id,
                ])->first();

                if ($balance) {
                    $oldBalance = $balance->balance;
                    // On April 1st, the current balance (capped) becomes the 'Carry Forward Last Year'
                    $balance->balance = ($oldBalance > $cap) ? $cap : $oldBalance;
                    $balance->carry_forward_last_year = $balance->balance;
                    $balance->accrued_this_year = 0; // Reset for new fiscal year
                    \App\Models\LeaveBalance::$auditReason = "Yearly Reset (April 1st) - Carry Forward Cap: {$cap}";
                    $balance->save();
                    
                    Log::info("April Reset for User {$user->id}, LeaveType {$rule->leave_type_id}. Carry Forward: {$balance->carry_forward_last_year}");
                }
            }
        }
        
        Log::info("Yearly (April) carry-forward reset processed for " . count($users) . " users.");
    }

    public static function grantCoff(User $user, $amount = 1)
    {
        // Comp-off credit lands in the unified All Purpose Leave pool — the
        // same place the Attendance Sunday/holiday-working hook credits it.
        // (This used to look up code 'COFF', which has never existed — the
        // stray type's code is literally "Comp off" — so the method was a
        // silent no-op.)
        $alpType = \App\Models\LeaveType::where('code', 'ALP')->first();
        if (!$alpType) return;

        $balance = LeaveBalance::firstOrNew([
            'user_id'       => $user->id,
            'leave_type_id' => $alpType->id,
            'tenant_id'     => $user->tenant_id,
        ]);

        $balance->balance = ($balance->balance ?? 0) + $amount;
        $balance->accrued_this_year = ($balance->accrued_this_year ?? 0) + $amount;
        \App\Models\LeaveBalance::$auditReason = "Comp Off Granted (Automated)";
        $balance->save();

        Log::info("Granted {$amount} comp-off day(s) into ALP for user {$user->id}.");
    }

    /**
     * Initialize leave balances for a newly assigned user/profile.
     */
    public static function initializeForUser(User $user)
    {
        $profile = $user->leavePolicyProfile;
        if (!$profile) return 0;

        $rules = $profile->rules;
        $count = 0;
        foreach ($rules as $rule) {
            $balance = LeaveBalance::firstOrNew([
                'user_id'       => $user->id,
                'leave_type_id' => $rule->leave_type_id,
                'tenant_id'     => $user->tenant_id,
            ]);

            // For new initialization OR existing 0-balance unused records, set to quota
            if ($balance->id === null || ($balance->balance == 0 && $balance->used == 0)) {
                $startingBalance = $rule->max_per_month ?? ($rule->max_per_year ? ($rule->max_per_year / 12) : 0);
                
                if ($startingBalance > 0) {
                    $balance->balance = $startingBalance;
                    $balance->used    = 0;
                    $balance->accrued_this_year = $startingBalance;
                    $balance->save();
                    $count++;
                }
            }
        }
        
        Log::info("Initialized {$count} leave balances for user {$user->id} with profile {$profile->name}.");
        return $count;
    }
}
