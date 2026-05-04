<?php

namespace App\Services;

use App\Models\User;
use App\Models\LeaveBalance;
use App\Models\LeavePolicyProfileRule;
use Illuminate\Support\Facades\Log;

class LeaveAccrualService
{
    /**
     * Accrue leaves for all users based on their assigned profile policies.
     * Should be run on the 1st of every month.
     */
    public static function accrueMonthly()
    {
        $users = User::whereNotNull('leave_policy_profile_id')->get();

        foreach ($users as $user) {
            $profile = $user->leavePolicyProfile;
            if (!$profile) continue;

            $rules = $profile->rules;
            foreach ($rules as $rule) {
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

                if ($rule->is_carry_forward) {
                    // Carry Forward: Increment the existing balance
                    $balance->balance += $monthlyIncrement;
                    $balance->accrued_this_year += $monthlyIncrement;
                    $balance->auditCustomMessage = "Monthly Accrual ({$rule->leaveType->name})";
                } else {
                    // Reset: Set balance specifically to the monthly quota (non-cumulative)
                    // Note: We still increment 'accrued_this_year' to track annual total
                    $balance->balance = $monthlyIncrement;
                    $balance->used    = 0; 
                    $balance->accrued_this_year = $monthlyIncrement;
                    $balance->carry_forward_last_year = 0;
                    $balance->auditCustomMessage = "Monthly Reset & Grant ({$rule->leaveType->name})";
                }

                $balance->save();
            }
        }
        
        Log::info("Monthly leave accrual processed for " . count($users) . " users.");
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
                    $balance->auditCustomMessage = "Yearly Reset (April 1st) - Carry Forward Cap: {$cap}";
                    $balance->save();
                    
                    Log::info("April Reset for User {$user->id}, LeaveType {$rule->leave_type_id}. Carry Forward: {$balance->carry_forward_last_year}");
                }
            }
        }
        
        Log::info("Yearly (April) carry-forward reset processed for " . count($users) . " users.");
    }

    public static function grantCoff(User $user, $amount = 1)
    {
        $coffType = \App\Models\LeaveType::where('code', 'COFF')->first();
        if (!$coffType) return;

        $balance = LeaveBalance::firstOrNew([
            'user_id'       => $user->id,
            'leave_type_id' => $coffType->id,
            'tenant_id'     => $user->tenant_id,
        ]);

        $balance->balance = ($balance->balance ?? 0) + $amount;
        $balance->accrued_this_year = ($balance->accrued_this_year ?? 0) + $amount;
        $balance->auditCustomMessage = "Comp Off Granted (Automated)";
        $balance->save();
        
        Log::info("Granted {$amount} COFF to user {$user->id}.");
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
