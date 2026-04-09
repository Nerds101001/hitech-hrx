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
                // Only process monthly quota rules
                if (!$rule->max_per_month) continue;

                $balance = LeaveBalance::firstOrNew([
                    'user_id'       => $user->id,
                    'leave_type_id' => $rule->leave_type_id,
                    'tenant_id'     => $user->tenant_id,
                ]);

                if ($rule->is_carry_forward) {
                    // Carry Forward: Increment the existing balance
                    $balance->balance += $rule->max_per_month;
                } else {
                    // Reset: Set balance specifically to the monthly quota
                    // This handles 'Redeem in same month' behavior.
                    $balance->balance = $rule->max_per_month;
                    $balance->used    = 0; // Reset used count for new month
                }

                $balance->save();
            }
        }
        
        Log::info("Monthly leave accrual processed for " . count($users) . " users.");
    }

    /**
     * Apply yearly carry-forward caps (e.g., max 6 days for Paid Leave)
     * Should be run on Jan 1st before the first accrual of the new year.
     */
    public static function resetYearlyCarryForward()
    {
        $users = User::whereNotNull('leave_policy_profile_id')->get();

        foreach ($users as $user) {
            $profile = $user->leavePolicyProfile;
            if (!$profile) continue;

            $rules = $profile->rules;
            foreach ($rules as $rule) {
                // Only process rules that have a carry forward cap
                if (!$rule->is_carry_forward || $rule->carry_forward_max_days === null) continue;

                $balance = LeaveBalance::where([
                    'user_id'       => $user->id,
                    'leave_type_id' => $rule->leave_type_id,
                    'tenant_id'     => $user->tenant_id,
                ])->first();

                if ($balance && $balance->balance > $rule->carry_forward_max_days) {
                    $oldBalance = $balance->balance;
                    $balance->balance = (float)$rule->carry_forward_max_days;
                    $balance->save();
                    
                    Log::info("Capped carry-forward for User {$user->id}, LeaveType {$rule->leave_type_id}. Old: {$oldBalance}, New Cap: {$rule->carry_forward_max_days}");
                }
            }
        }
        
        Log::info("Yearly carry-forward reset processed for " . count($users) . " users.");
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

        $balance->balance += $amount;
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
                    $balance->save();
                    $count++;
                }
            }
        }
        
        Log::info("Initialized {$count} leave balances for user {$user->id} with profile {$profile->name}.");
        return $count;
    }
}
