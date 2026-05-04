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
    public static function validate(User $user, int $leaveTypeId, string $fromDate, string $toDate, ?float $hours = null): ?string
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
            $limit = ($rule && $rule->short_leave_per_month) ? $rule->short_leave_per_month : 1;
            $usedThisMonth = LeaveRequest::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->whereIn('status', ['pending', 'approved'])
                ->whereYear('from_date', $from->year)
                ->whereMonth('from_date', $from->month)
                ->count();
            
            if ($usedThisMonth >= $limit) {
                return "You have reached the monthly short leave limit of {$limit}.";
            }

            if ($hours && $rule && $rule->short_leave_hours && $hours > $rule->short_leave_hours) {
                return "You can only apply for a maximum of {$rule->short_leave_hours} hours for this short leave.";
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

            // Exceptions like ML/MAT/PL/PAT often bypass standard monthly/yearly quotas
            if (in_array($code, ['ML', 'MAT', 'PL_PAT', 'PAT'])) {
                return null;
            }
        }

        if (in_array($code, ['OD', 'WFH'])) {
            return null;
        }

        // --- 4. Unified Paid Pool Logic ---
        $isPaidType = $leaveType->is_paid;
        $isMaternityPaternity = in_array($code, ['ML', 'MAT', 'PL_PAT', 'PAT']);

        if ($isPaidType && !$isMaternityPaternity && $code !== 'SHL') {
            // Check Accrued Balance from the unified pool
            $available = self::getPoolBalance($user);
            
            // Subtract pending requests from available balance
            $pendingRequests = LeaveRequest::where('user_id', $user->id)
                ->whereHas('leaveType', function($q) {
                    $q->where('is_paid', true)->whereNotIn('code', ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL']);
                })
                ->where('status', 'pending')
                ->get();
            
            $pendingDays = 0;
            foreach ($pendingRequests as $pr) {
                $pendingDays += self::calculateWorkingDays($user, $pr->leave_type_id, $pr->from_date, $pr->to_date);
            }
            
            $netAvailable = $available - $pendingDays;

            if ($netAvailable < $workingDays) {
                 // Note: For paid types, we usually allow submission (will be LWP),
                 // but we can return a warning or strict rejection if needed.
                 // The requirement says "used from the pool", so we should check.
                 // return "Insufficient pool balance. Net Available: " . $netAvailable . " day(s).";
            }
        } elseif ($isPaidType && $isMaternityPaternity) {
            // Check individual balance for Maternity/Paternity
            $balanceRecord = LeaveBalance::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
            $available = $balanceRecord ? ($balanceRecord->balance - $balanceRecord->used) : 0;
            if ($available < $workingDays && $leaveType->is_strict_rules) {
                return "Insufficient balance for this category.";
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
                [$startOfYear, $endOfYear] = self::getLeaveYearRange($from);
                $usedThisYear = (clone $base)
                    ->where('from_date', '>=', $startOfYear->toDateString())
                    ->where('from_date', '<=', $endOfYear->toDateString())
                    ->count();
                if (($usedThisYear + $workingDays) > $rule->max_per_year) {
                    return "Annual limit exceeded. Yearly quota for the April-March cycle is {$rule->max_per_year}.";
                }
            }
        }

        return null;
    }

    /**
     * Get the start and end dates for the leave year (April to March).
     */
    public static function getLeaveYearRange($date = null): array
    {
        $date = $date ? Carbon::parse($date) : now();
        $year = $date->year;
        $month = $date->month;

        // April (4) to March (3) cycle
        if ($month < 4) {
            $start = Carbon::createFromDate($year - 1, 4, 1)->startOfDay();
            $end   = Carbon::createFromDate($year, 3, 31)->endOfDay();
        } else {
            $start = Carbon::createFromDate($year, 4, 1)->startOfDay();
            $end   = Carbon::createFromDate($year + 1, 3, 31)->endOfDay();
        }

        return [$start, $end];
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

    public static function checkConflicts(User $user, string $fromDate, string $toDate): array
    {
        $conflicts = LeaveRequest::where('status', LeaveRequestStatus::APPROVED)
            ->where('from_date', '<=', $toDate)
            ->where('to_date', '>=', $fromDate)
            ->whereHas('user', function($q) use ($user) {
                $q->where('team_id', $user->team_id)
                  ->where('id', '!=', $user->id);
            })
            ->with('user')
            ->get();

        return $conflicts->map(function($c) {
            $from = $c->from_date instanceof Carbon ? $c->from_date : Carbon::parse($c->from_date);
            $to = $c->to_date instanceof Carbon ? $c->to_date : Carbon::parse($c->to_date);
            return [
                'user_name' => $c->user->getFullName(),
                'from' => $from->format('d M'),
                'to' => $to->format('d M'),
            ];
        })->toArray();
    }

    public static function getPoolBalance(User $user): float
    {
        // Poolable types are all paid types EXCEPT Maternity, Paternity, and Short Leave
        $poolableBalances = LeaveBalance::where('user_id', $user->id)
            ->whereHas('leaveType', function($q) {
                $q->where('is_paid', true)
                  ->whereNotIn('code', ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL']);
            })
            ->get();

        $totalBalance = 0;
        foreach ($poolableBalances as $bal) {
            $totalBalance += ($bal->balance - $bal->used);
        }

        return (float)max(0, $totalBalance);
    }

    public static function getBalanceImpact(User $user, int $leaveTypeId, string $fromDate, string $toDate): array
    {
        $leaveType = \App\Models\LeaveType::find($leaveTypeId);
        $workingDays = self::calculateWorkingDays($user, $leaveTypeId, $fromDate, $toDate);
        
        $isMaternityPaternity = $leaveType && in_array($leaveType->code, ['ML', 'MAT', 'PL_PAT', 'PAT']);
        $isShortLeave = $leaveType && $leaveType->code === 'SHL';
        $isPaidType = $leaveType ? $leaveType->is_paid : false;

        if ($isPaidType && !$isMaternityPaternity && !$isShortLeave) {
            // Use Unified Pool
            $available = self::getPoolBalance($user);
        } else {
            // Individual balance for exceptions or unpaid types
            $balanceRecord = LeaveBalance::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->first();
            $available = $balanceRecord ? ($balanceRecord->balance - $balanceRecord->used) : 0;
        }
        
        if ($isPaidType) {
            $paidUtilized = min($workingDays, $available);
            $unpaidUtilized = max(0, $workingDays - $available);
        } else {
            $paidUtilized = 0;
            $unpaidUtilized = $workingDays;
        }
        
        return [
            'total_days' => $workingDays,
            'paid_utilized' => $paidUtilized,
            'unpaid_utilized' => $unpaidUtilized,
            'remaining_balance' => max(0, $available - $paidUtilized),
        ];
    }
}
