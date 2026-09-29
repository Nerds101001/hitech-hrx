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
    /** Short leave length when the policy sets none. */
    public const DEFAULT_SHORT_LEAVE_HOURS = 2.0;

    /** Max hours for one short leave: the policy rule's figure, else the default. */
    public static function shortLeaveHoursCap(User $user, int $leaveTypeId): float
    {
        $rule = $user->leavePolicyProfile
            ? LeavePolicyProfileRule::where('profile_id', $user->leavePolicyProfile->id)->where('leave_type_id', $leaveTypeId)->first()
            : \App\Models\UnitLeavePolicy::where('site_id', $user->site_id)->where('leave_type_id', $leaveTypeId)->first();

        $hours = (float) ($rule->short_leave_hours ?? 0);
        return $hours > 0 ? $hours : self::DEFAULT_SHORT_LEAVE_HOURS;
    }

    /**
     * Length of a short leave in hours: the longer of the stated duration and
     * the start-to-end span, so neither figure alone can understate it.
     */
    public static function shortLeaveHours(?float $hours, ?string $startTime, ?string $endTime): ?float
    {
        $span = null;
        if ($startTime && $endTime) {
            try {
                $s = Carbon::parse($startTime);
                $e = Carbon::parse($endTime);
                if ($e->greaterThan($s)) {
                    $span = $s->diffInMinutes($e) / 60;
                }
            } catch (\Throwable $ex) {
                $span = null;
            }
        }
        $values = array_filter([$hours !== null ? (float) $hours : null, $span], fn ($v) => $v !== null);
        return $values ? max($values) : null;
    }

    /** A one-date half-day request that names its half (first_half / second_half). */
    private static function isSingleHalfDay(bool $isHalfDay, string $fromDate, string $toDate, ?string $session): bool
    {
        return $isHalfDay && in_array($session, ['first_half', 'second_half'], true)
            && Carbon::parse($fromDate)->isSameDay(Carbon::parse($toDate));
    }

    public static function validate(User $user, int $leaveTypeId, string $fromDate, string $toDate, ?float $hours = null, bool $isHalfDay = false, ?string $startTime = null, ?string $endTime = null, ?string $halfDaySession = null): ?string
    {
        $from = Carbon::parse($fromDate);
        $to   = Carbon::parse($toDate);

        $leaveType = \App\Models\LeaveType::find($leaveTypeId);
        $code = $leaveType ? $leaveType->code : '';

        $profile = $user->leavePolicyProfile;
        $workingDays = self::calculateWorkingDays($user, $leaveTypeId, $fromDate, $toDate, $isHalfDay);

        if ($workingDays <= 0) {
            return 'The selected period contains only off-days or holidays.';
        }

        // --- 2. Overlap Check ---
        // A half day may sit beside the OTHER half of the same date (first half
        // taken, now the second half too): together they make a full day. Any
        // other overlap - a full day, a longer span, the same half again - blocks.
        $overlap = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', [LeaveRequestStatus::PENDING, LeaveRequestStatus::APPROVED])
            ->where('from_date', '<=', $toDate)
            ->where('to_date', '>=', $fromDate)
            ->when(self::isSingleHalfDay($isHalfDay, $fromDate, $toDate, $halfDaySession), fn ($q) => $q->where(fn ($w) => $w
                ->where('is_half_day', false)
                ->orWhereNull('half_day_session')
                ->orWhere('half_day_session', $halfDaySession)
                ->orWhereColumn('from_date', '!=', 'to_date')))
            ->exists();

        if ($overlap) {
            return 'You already have an active leave request (Pending or Approved) that overlaps with these dates. Please wait for the current one to be processed or cancelled.';
        }

        // --- Short Leave: strictly one per month, enforced even when no
        // policy rule is configured for the type ---
        if ($leaveType && $leaveType->is_short_leave) {
            // Scoped to THIS leave type, not to every type carrying the
            // is_short_leave flag. Matching on the flag meant any two types
            // that both had it shared a single monthly allowance: a
            // Compensatory OFF (type 12, flagged is_short_leave by mistake
            // when it was created) silently consumed the real Short Leave
            // quota, so the balance card still read "1 available" while the
            // application came back "already consumed" — confirmed live on
            // seven CG Office employees. The flag says what KIND of leave
            // this is; it was never an identity for one particular type.
            $usedThisMonth = LeaveRequest::where('user_id', $user->id)
                ->where('leave_type_id', $leaveTypeId)
                ->whereIn('status', [LeaveRequestStatus::PENDING, LeaveRequestStatus::APPROVED])
                ->whereYear('from_date', $from->year)
                ->whereMonth('from_date', $from->month)
                ->count();

            if ($usedThisMonth >= 1) {
                return 'You have already consumed your short leave for this month.';
            }

            // Hours cap, enforced even when the policy has no rule for the
            // type. It used to be checked only when a rule with
            // short_leave_hours existed, so on a profile without one (Sales
            // (New)) a "short leave" could run 10:18 to 18:59 -- and an
            // approved short leave counts the day as Present. The length is
            // taken from the start/end times as well as the client's own
            // duration figure, whichever is longer.
            $cap = self::shortLeaveHoursCap($user, $leaveTypeId);
            $asked = self::shortLeaveHours($hours, $startTime, $endTime);
            if ($asked !== null && $asked > $cap + 0.01) {
                $capText = rtrim(rtrim(number_format($cap, 2), '0'), '.');
                return "A short leave can be at most {$capText} hours (you asked for " . round($asked, 2) . " hours). For a longer absence apply for Half Day or a full-day leave.";
            }
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

        // Short Leave: monthly limit and hours cap are both enforced above.
        if ($leaveType && $leaveType->is_short_leave) {
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
                    // Same pool-exclusion list as getPoolBalance() and
                    // processApproval() -- WFH included, or a pending WFH
                    // request would subtract days from a pool it never draws on.
                    $q->where('is_paid', true)->whereNotIn('code', ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH']);
                })
                ->where('status', 'pending')
                ->get();
            
            $pendingDays = 0;
            foreach ($pendingRequests as $pr) {
                $pendingDays += self::calculateWorkingDays($user, $pr->leave_type_id, $pr->from_date, $pr->to_date, (bool)$pr->is_half_day);
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
    public static function calculateWorkingDays(User $user, int $leaveTypeId, string $fromDate, string $toDate, bool $isHalfDay = false): float
    {
        $from = Carbon::parse($fromDate);
        $to   = Carbon::parse($toDate);
        
        $workingDays = 0.0;
        $tempDate = $from->copy();
        
        $leaveType = \App\Models\LeaveType::find($leaveTypeId);
        $code = $leaveType ? $leaveType->code : '';

        while ($tempDate->lte($to)) {
            if (self::isWorkingDay($user, $tempDate)) {
                $workingDays += $isHalfDay ? 0.5 : 1.0;
            }
            $tempDate->addDay();
        }

        return (float)$workingDays;
    }

    protected static $holidayCache = [];

    /**
     * Is this Saturday an OFF day for the user, per their leave policy profile
     * (or their site as fallback)?
     *
     *   true  => off Saturday (working it earns a comp-off)
     *   false => a normal working Saturday
     *   null  => no profile/site rule exists, caller decides
     */
    public static function saturdayOffState(User $user, $date): ?bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        if (!$date->isSaturday()) {
            return false;
        }

        $profile = $user->leavePolicyProfile;
        $site    = $user->site;
        if (!$profile && !$site) {
            return null; // nothing configured
        }

        $satConfig = (array) ($profile
            ? ($profile->saturday_off_config ?? [])
            : ($site->saturday_off_config ?? []));

        if (in_array('all', $satConfig)) {
            return true;
        }

        if (!empty($satConfig)) {
            $occurrence = ceil($date->day / 7);
            $isLast = ($date->copy()->addWeek()->month != $date->month);
            if (in_array((string) $occurrence, $satConfig) || ($isLast && in_array('last', $satConfig))) {
                return true;
            }
        }

        // A profile/site dictates the rules and this Saturday is not among the
        // off ones, so it is a working Saturday.
        return false;
    }

    /**
     * Check if a specific date is a working day for a user.
     */
    public static function isWorkingDay(User $user, $date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $currentDateStr = $date->toDateString();
        
        $siteKey = ($user->site_id ?? 'g') . '-' . ($user->department_id ?? 'g');
        if (!isset(self::$holidayCache[$siteKey])) {
            // pluck('date') yields Carbon objects (the model casts `date`), and
            // in_array() compared those against a 'Y-m-d' string — Carbon
            // stringifies to 'Y-m-d H:i:s', so the check NEVER matched and every
            // holiday was treated as a working day. Normalise to date strings.
            //
            // Only FULL-DAY holidays suppress the day. A part-day concession
            // (e.g. a 90-minute morning grace period) is still a working day -
            // Holiday::appliesTo() and registryAjax's own $holidaysBySite split
            // already know this, but this cache didn't, so a part-day record
            // made the whole day register as OFF here: anyone who genuinely
            // didn't show up (no approved leave, no punch) silently stopped
            // counting as Absent for the day instead.
            self::$holidayCache[$siteKey] = \App\Models\Holiday::query()
                ->forUser($user)
                ->where('holiday_type', 'full')
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all();
        }

        if (in_array($currentDateStr, self::$holidayCache[$siteKey], true)) return false;

        // 2. Check Sundays
        if ($date->isSunday()) {
            if (!$user->shift) {
                return false;
            }
        }

        // 3. Check Saturdays (Rules from Profile or Site)
        if ($date->isSaturday()) {
            $satResult = self::saturdayOffState($user, $date);
            if ($satResult !== null) {
                return !$satResult; // true = it's an off Saturday => not a working day
            }
        }

        // 4. Fallback to Shift-based Working Days if no Profile/Site rule explicitly marks it OFF
        if ($user->shift) {
            $dayName = strtolower($date->format('l'));
            if (!$user->shift->$dayName) {
                return false;
            }
            return true;
        }

        // 5. Nothing configured at all — 31 staff have no leave policy profile and
        //    228 have no shift, and for them every branch above falls through.
        //    Treating Sunday as a working day for those people is never right, so
        //    the safe default is the company's standard weekly off.
        return !$date->isSunday();
    }

    public static function checkConflicts(User $user, string $fromDate, string $toDate): array
    {
        // If the user has no team assigned, there are no team conflicts to check.
        // Without this guard, all "no-team" users would see each other's leaves as conflicts.
        if (!$user->team_id) {
            return [];
        }

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
        // Poolable types are all paid types EXCEPT Maternity, Paternity, Short
        // Leave and WFH. WFH belongs here for the same reason as the rest: it
        // is paid, but never out of this pool -- LeaveRequest::processApproval()
        // excludes it from $excludedPoolCodes, so counting its balance here
        // would inflate the pool by days approval will never deduct.
        $poolableBalances = LeaveBalance::where('user_id', $user->id)
            ->whereHas('leaveType', function($q) {
                $q->where('is_paid', true)
                  ->whereNotIn('code', ['ML', 'MAT', 'PL_PAT', 'PAT', 'SHL', 'WFH']);
            })
            ->get();

        $totalBalance = 0;
        foreach ($poolableBalances as $bal) {
            $totalBalance += ($bal->balance - $bal->used);
        }

        return (float)max(0, $totalBalance);
    }

    public static function getBalanceImpact(User $user, int $leaveTypeId, string $fromDate, string $toDate, bool $isHalfDay = false): array
    {
        $leaveType = \App\Models\LeaveType::find($leaveTypeId);
        $workingDays = self::calculateWorkingDays($user, $leaveTypeId, $fromDate, $toDate, $isHalfDay);
        
        $isMaternityPaternity = $leaveType && in_array($leaveType->code, ['ML', 'MAT', 'PL_PAT', 'PAT']);
        $isShortLeave = $leaveType && $leaveType->code === 'SHL';
        $isWfh = $leaveType && strtoupper($leaveType->code) === 'WFH';
        $isPaidType = $leaveType ? $leaveType->is_paid : false;

        // WFH never draws from any balance pool — it's always fully paid
        // (see PayrollService), so the preview must show 0 impact, not a
        // phantom 0.5-day deduction against the pool.
        if ($isWfh) {
            return [
                'total_days' => $workingDays,
                'paid_utilized' => $workingDays,
                'unpaid_utilized' => 0,
                'remaining_balance' => self::getPoolBalance($user),
            ];
        }

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
