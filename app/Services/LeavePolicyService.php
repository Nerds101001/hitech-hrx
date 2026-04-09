<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\LeavePolicyProfileRule;
use App\Models\LeaveBalance;
use App\Models\User;
use App\Enums\LeaveRequestStatus;
use Carbon\Carbon;

class LeavePolicyService
{
    /**
     * Validate a leave request against user-based or unit-based policies.
     *
     * @param  User   $user
     * @param  int    $leaveTypeId
     * @param  string $fromDate   Y-m-d
     * @param  string $toDate     Y-m-d
     * @return string|null  Returns an error message string, or null if the request is valid.
     */
    public static function validate(User $user, int $leaveTypeId, string $fromDate, string $toDate): ?string
    {
        $from = Carbon::parse($fromDate);
        $to   = Carbon::parse($toDate);

        $leaveType = \App\Models\LeaveType::find($leaveTypeId);
        $code = $leaveType ? $leaveType->code : '';

        $profile = $user->leavePolicyProfile;
        $workingDays = self::calculateWorkingDays($user, $leaveTypeId, $fromDate, $toDate);

        if ($workingDays <= 0) {
            return 'The selected period contains only off-days or holidays.';
        }

        // --- 2. Overlap Check ---
        $overlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', [LeaveRequestStatus::PENDING, LeaveRequestStatus::APPROVED])
            ->where('from_date', '<=', $toDate)
            ->where('to_date', '>=', $fromDate)
            ->exists();

        if ($overlap) {
            return 'You already have an active leave request (Pending or Approved) that overlaps with these dates. Please wait for the current one to be processed or cancelled.';
        }

        // --- 3. Policy Enforcement ---
        $rule = null;
        if ($profile) {
            $rule = LeavePolicyProfileRule::where('profile_id', $profile->id)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
        } else {
            // Fallback to legacy UnitLeavePolicy
            $rule = \App\Models\UnitLeavePolicy::where('site_id', $user->site_id)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
        }

        if (!$rule) {
            return null; // No specific restrictions defined
        }

        if (!$rule->is_applicable) {
            return 'This leave type is not applicable for your current policy.';
        }

        // Master Eligibility Checks (Gender & Marital Status)
        if ($leaveType->is_strict_rules) {
            $genderRule = $rule->applicable_gender ?? 'all';
            $maritalRule = $rule->applicable_marital_status ?? 'all';

            if ($genderRule !== 'all') {
                if (strtolower($user->gender ?? '') !== strtolower($genderRule)) {
                    return "This leave type is only applicable for {$genderRule} employees.";
                }
            }

            if ($maritalRule !== 'all') {
                if (strtolower($user->marital_status ?? '') !== strtolower($maritalRule)) {
                    return "This leave type is only applicable for {$maritalRule} employees.";
                }
            }
        }

        // Tenure Upgrade Logic (Depends on Consecutive Toggle)
        $maxConsecutive = $rule->max_consecutive_days;
        if ($leaveType->is_consecutive_allowed && $rule->tenure_required_months && $rule->tenure_consecutive_allowed && $user->date_of_joining) {
            $userTenureMonths = Carbon::parse($user->date_of_joining)->diffInMonths(now());
            if ($userTenureMonths >= $rule->tenure_required_months) {
                $maxConsecutive = $rule->tenure_consecutive_allowed;
            }
        }

        // Short Leave Check
        if ($leaveType && $leaveType->is_short_leave) {
            if ($rule->short_leave_per_month) {
                $usedThisMonth = LeaveRequest::where('user_id', $user->id)
                    ->where('leave_type_id', $leaveTypeId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->whereYear('from_date', $from->year)
                    ->whereMonth('from_date', $from->month)
                    ->count();
                if ($usedThisMonth >= $rule->short_leave_per_month) {
                    return "You have reached the monthly short leave limit of {$rule->short_leave_per_month}.";
                }
            }
            return null;
        }

        // Tenure Requirement
        if ($rule->tenure_required_months && $user->date_of_joining) {
            $tenureMonths = Carbon::parse($user->date_of_joining)->diffInMonths(now());
            if ($tenureMonths < $rule->tenure_required_months) {
                return "You need at least {$rule->tenure_required_months} months of service to apply.";
            }
        }

        // Consecutive Check (Only if enabled in Master Type)
        if ($leaveType->is_consecutive_allowed && $maxConsecutive && $workingDays > $maxConsecutive) {
            return "You cannot take more than {$maxConsecutive} consecutive working day(s).";
        }

        // Quota / Balance Check (Only if Carry Forward is master-switched)
        $isCarryForward = $leaveType->is_carry_forward && ($rule->is_carry_forward ?? false);

        // Exceptional leaves (Maternity/Paternity) usually don't consume standard balance
        // and are handled separately by HR/Admin. Bypass standard balance checks.
        // Split Entitlement Check (Universal for any type with the toggle enabled)
        if ($leaveType->is_split_entitlement) {
            $wfhEntitlement = $rule->wfh_days_entitlement ?? 0;
            $offEntitlement = $rule->off_days_entitlement ?? 0;
            $totalEntitlement = $wfhEntitlement + $offEntitlement;

            if ($totalEntitlement > 0 && $workingDays > $totalEntitlement) {
                return "The total entitlement for this leave type is {$totalEntitlement} days ({$wfhEntitlement} WFH + {$offEntitlement} OFF). You are requesting {$workingDays} days.";
            }

            // Exceptions like ML/PL_PAT often bypass standard monthly/yearly quotas
            if (in_array($code, ['ML', 'PL_PAT'])) {
                return null;
            }
        }

        if (in_array($code, ['OD', 'WFH'])) {
            return null;
        }

        if ($isCarryForward) {
            // Check Accrued Balance
            $balanceRecord = LeaveBalance::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
            $available = $balanceRecord ? ($balanceRecord->balance - $balanceRecord->used) : 0;
            
            // Subtract pending requests from available balance
            $pendingRequests = LeaveRequest::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->where('status', 'pending')
                ->get();
            
            $pendingDays = 0;
            foreach ($pendingRequests as $pr) {
                $p_from = $pr->from_date instanceof Carbon ? $pr->from_date : Carbon::parse($pr->from_date);
                $p_to = $pr->to_date instanceof Carbon ? $pr->to_date : Carbon::parse($pr->to_date);

                $pendingDays += self::calculateWorkingDays($user, $leaveTypeId, $p_from->toDateString(), $p_to->toDateString());
            }
            
            if (($available - $pendingDays) < $workingDays) {
                return "Insufficient leave balance. Available: " . ($available - $pendingDays) . " day(s).";
            }
        } else {
            // Check Monthly/Yearly Quota (Non-carry forward)
            $base = LeaveRequest::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->whereIn('status', ['pending', 'approved']);

            if ($rule->max_per_month) {
                $usedThisMonth = (clone $base)
                    ->whereYear('from_date', $from->year)
                    ->whereMonth('from_date', $from->month)
                    ->count();
                if (($usedThisMonth + $workingDays) > $rule->max_per_month) {
                    return "Monthly limit exceeded. Monthly quota is {$rule->max_per_month}.";
                }
            }

            if ($rule->max_per_year) {
                $usedThisYear = (clone $base)->whereYear('from_date', $from->year)->count();
                if (($usedThisYear + $workingDays) > $rule->max_per_year) {
                    return "Annual limit exceeded. Yearly quota is {$rule->max_per_year}.";
                }
            }
        }

        return null;
    }

    /**
     * Helper to calculate working days (excluding holidays and Saturdays).
     */
    public static function calculateWorkingDays(User $user, int $leaveTypeId, string $fromDate, string $toDate): int
    {
        $from = Carbon::parse($fromDate);
        $to   = Carbon::parse($toDate);
        
        $workingDays = 0;
        $tempDate = $from->copy();
        
        $leaveType = \App\Models\LeaveType::find($leaveTypeId);
        $code = $leaveType ? $leaveType->code : '';

        while ($tempDate->lte($to)) {
            if (self::isWorkingDay($user, $tempDate)) {
                $workingDays++;
            }
            $tempDate->addDay();
        }

        return (int)$workingDays;
    }

    /**
     * Check if a specific date is a working day for a user.
     */
    public static function isWorkingDay(User $user, $date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $currentDateStr = $date->toDateString();
        
        // 1. Check Holidays
        $holiday = \App\Models\Holiday::where(function($q) use ($user) {
                $q->whereNull('site_id')
                  ->orWhere('site_id', $user->site_id);
            })
            ->where('date', $currentDateStr)
            ->exists();
            
        if ($holiday) return false;

        // 2. Check Sundays
        if ($date->isSunday()) return false;

        // 3. Check Saturdays (Rules from Profile or Site)
        if ($date->isSaturday()) {
            $profile = $user->leavePolicyProfile;
            $site    = $user->site;
            $satConfig = $profile ? ($profile->saturday_off_config ?? []) : ($site->saturday_off_config ?? []);

            if (!empty($satConfig)) {
                if (in_array('all', (array)$satConfig)) {
                    return false;
                } else {
                    $occurrence = ceil($date->day / 7);
                    $isLast = ($date->copy()->addWeek()->month != $date->month);
                    
                    if (in_array((string)$occurrence, (array)$satConfig) || ($isLast && in_array('last', (array)$satConfig))) {
                        return false;
                    }
                }
            }
        }

        // 4. Fallback to Shift-based Working Days if no Profile/Site rule explicitly marks it OFF
        if ($user->shift) {
            $dayName = strtolower($date->format('l'));
            if (!$user->shift->$dayName) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deduct short leave balance and create an audit log.
     */
    public static function deductShortLeave(User $user, $date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        
        // 1. Find the Short Leave Type
        $shortLeaveType = \App\Models\LeaveType::where('is_short_leave', true)->first();
        if (!$shortLeaveType) {
            \Illuminate\Support\Facades\Log::warning("No Short Leave type defined for tenant {$user->tenant_id}.");
            return false;
        }

        // 2. Decrement Balance
        $balance = \App\Models\LeaveBalance::where('user_id', $user->id)
            ->where('leave_type_id', $shortLeaveType->id)
            ->first();

        if ($balance) {
            $balance->used += 1; // Count as 1 occurrence
            $balance->save();
        }

        // 3. Create a phantom LeaveRequest for audit/tracking
        \App\Models\LeaveRequest::create([
            'user_id'       => $user->id,
            'leave_type_id' => $shortLeaveType->id,
            'from_date'     => $date->toDateString(),
            'to_date'       => $date->toDateString(),
            'reason'        => 'Auto-generated for Late Waiver (Short Leave Policy)',
            'status'        => \App\Enums\LeaveRequestStatus::APPROVED,
            'approved_by_id' => 1, // System Approved
            'tenant_id'     => $user->tenant_id,
        ]);

        return true;
    }
}
