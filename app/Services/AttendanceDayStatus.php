<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;

/**
 * The single source of truth for "what is this employee's status on this
 * day" - present, absent, holiday, on leave, weekly off, etc.
 *
 * Before this existed, that decision was reimplemented independently in
 * four places: the admin Monthly Registry (AttendanceController::registryAjax),
 * the admin Attendance Logs (AttendanceController::indexAjax), and the
 * employee's own My Attendance calendar and list view
 * (UserDashboardController::attendanceRegistryAjax / attendanceIndex). Each
 * copy drifted from the others in small ways over time - a part-day holiday
 * concession treated as a full day off in one place but not another, a
 * required-minutes bar that accounted for that concession in one place but
 * used a flat number in another, a worked designated off-Saturday earning
 * its SP badge in one place but rendering as plain Present in another - so
 * the same underlying facts could show a different status depending on
 * which page you looked at.
 *
 * This resolves a single day ONCE from the raw facts (is there an
 * attendance row, a covering leave request, a holiday, is it a working
 * day). Every caller then applies its own presentation on top of the same
 * decision - a compact HTML badge for the admin log, a full calendar card
 * for the employee's own page, running row totals for the registry grid -
 * but the underlying classification can no longer diverge between them.
 *
 * $holiday must be the specific Holiday row already resolved for this user
 * and date (site- and gender-scoped, the most generous one if several
 * part-day concessions apply) - this class does not query for it, so a
 * per-user-per-day loop can batch that lookup once per request instead of
 * once per user per day. See Holiday::appliesTo() / Holiday::partDayFor()
 * for the single-lookup case (e.g. a DataTable iterating one attendance row
 * at a time, where batching doesn't apply).
 */
class AttendanceDayStatus
{
    /** Minutes of real work needed to earn the SP/HP/weekly-off-worked credit. */
    public const CREDIT_MINUTES = 240;

    /** Minutes of real work a Short Leave day needs to still count as a full Present. */
    public const SHORT_LEAVE_PRESENT_MINUTES = 350;

    public static function resolve(
        User $user,
        Carbon $date,
        ?Attendance $attendance,
        ?LeaveRequest $matchingLeave,
        ?Holiday $holiday
    ): array {
        $isSunday = $date->isSunday();
        $isFullHoliday = $holiday ? $holiday->isFullDay() : false;
        $isOffSaturday = !$isSunday && !$isFullHoliday && $date->isSaturday()
            && LeavePolicyService::saturdayOffState($user, $date) === true;
        $reqMins = ($holiday && $holiday->isPartDay())
            ? max(Holiday::MIN_PRESENCE_MINUTES, Holiday::FULL_DAY_MINUTES - $holiday->concessionMinutes())
            : Holiday::FULL_DAY_MINUTES;

        $isDummyPunch = $attendance && $attendance->check_in_time
            && $attendance->check_in_time->format('H:i') === '00:00';
        $hasRealPunch = $attendance && $attendance->check_in_time && $attendance->check_out_time && !$isDummyPunch;
        $workMins = $hasRealPunch ? $attendance->check_in_time->diffInMinutes($attendance->check_out_time) : 0;

        // A leave can reach this day via two routes that both exist across
        // the four original implementations: linked directly on the
        // Attendance row (leave_request_id), or separately matched by date
        // range with no Attendance link at all (e.g. an approved short leave
        // that never got its own punch record). Either counts.
        $attendanceLeave = $attendance?->leaveRequest;
        $isShort = ($attendanceLeave && ($attendanceLeave->is_short_leave || ($attendanceLeave->leaveType?->is_short_leave ?? false)))
            || ($matchingLeave && ($matchingLeave->is_short_leave || ($matchingLeave->leaveType?->is_short_leave ?? false)));
        $isHalfDay = ($attendanceLeave && ($attendanceLeave->is_half_day || ($attendanceLeave->leaveType?->is_half_day ?? false)))
            || ($matchingLeave && ($matchingLeave->is_half_day || ($matchingLeave->leaveType?->is_half_day ?? false)));
        $isFullDayLeave = ($attendanceLeave && !$isShort && !$isHalfDay) || ($matchingLeave && !$isShort && !$isHalfDay);
        $leaveReq = $attendanceLeave ?: $matchingLeave;
        $isPaid = $leaveReq && $leaveReq->leaveType?->is_paid && !$leaveReq->is_lwp;
        $isWfh = $leaveReq && strtoupper($leaveReq->leaveType->code ?? '') === 'WFH';

        $result = [
            'code' => 'missing',
            'workMins' => $workMins,
            'reqMins' => $reqMins,
            'isSunday' => $isSunday,
            'isOffSaturday' => $isOffSaturday,
            'isFullHoliday' => $isFullHoliday,
            'holiday' => $holiday,
            'holidayName' => $holiday?->name,
            'isPaid' => (bool) $isPaid,
            'isShort' => (bool) $isShort,
            'isHalfDay' => (bool) $isHalfDay,
            'leaveRequest' => $leaveReq,
        ];

        if ($attendance) {
            if ($isSunday || $isOffSaturday) {
                $result['code'] = $workMins >= self::CREDIT_MINUTES ? 'sp' : 'weekly_off';
            } elseif ($isFullHoliday) {
                $result['code'] = $workMins >= self::CREDIT_MINUTES ? 'hp' : 'holiday';
            } elseif (empty($attendance->admin_reason)) {
                if ($isWfh) {
                    $result['code'] = 'work_from_home';
                } elseif ($isShort) {
                    if ($workMins >= self::SHORT_LEAVE_PRESENT_MINUTES) {
                        $result['code'] = 'short-leave-present';
                    } elseif ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = 'half-day';
                    } else {
                        $result['code'] = 'absent';
                    }
                } elseif ($isHalfDay) {
                    if ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = $isPaid ? 'half-day-paid' : 'half-day';
                    } else {
                        $result['code'] = $isPaid ? 'paid_leave' : 'absent';
                    }
                } elseif ($isFullDayLeave) {
                    if ($workMins >= $reqMins) {
                        $result['code'] = 'present';
                    } elseif ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = $isPaid ? 'half-day-paid' : 'half-day';
                    } else {
                        $result['code'] = $isPaid ? 'paid_leave' : 'absent';
                    }
                } else {
                    if ($workMins >= $reqMins) {
                        $result['code'] = 'present';
                    } elseif ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = 'half-day';
                    } else {
                        $result['code'] = 'absent';
                    }

                    // A real punch-in with no punch-out on a past day is a
                    // missed punch, not Absent (workMins is 0 without both
                    // punches, so this must override the <4h bucket).
                    if ($attendance->check_in_time && !$attendance->check_out_time
                        && !$isDummyPunch && $date->isPast() && !$date->isToday()) {
                        $result['code'] = 'miss-punch';
                    }
                }
            } else {
                // A manual admin adjustment (admin_reason set): trust the
                // stored status as-is rather than recomputing it - this is
                // the one deliberate escape hatch from the rules above,
                // consistent across all four original implementations.
                $result['code'] = strtolower($attendance->status ?: 'present');
            }

            return $result;
        }

        // No attendance row at all for this day.
        if ($matchingLeave) {
            $isShortLeave = $matchingLeave->leaveType?->is_short_leave ?? false;
            $isHalfDayLeave = $matchingLeave->is_half_day || ($matchingLeave->leaveType?->is_half_day ?? false);
            $isPaidLeave = $matchingLeave->leaveType?->is_paid && !$matchingLeave->is_lwp;

            if ($isShortLeave) {
                $result['code'] = 'absent';
            } elseif ($isHalfDayLeave) {
                $result['code'] = $isPaidLeave ? 'paid_leave' : 'absent';
            } else {
                $result['code'] = $isPaidLeave ? 'paid_leave' : 'unpaid_leave';
            }
            $result['isPaid'] = (bool) $isPaidLeave;
        } elseif ($isFullHoliday) {
            $result['code'] = 'holiday';
        } elseif (!LeavePolicyService::isWorkingDay($user, $date)) {
            $result['code'] = 'weekly_off';
        } elseif ($date->isFuture() && !$date->isToday()) {
            $result['code'] = 'scheduled';
        } elseif ($date->isPast() && !$date->isToday()) {
            $result['code'] = 'absent';
        } else {
            $result['code'] = 'today';
        }

        return $result;
    }

    /**
     * Batch-resolve every holiday for a month, split into full-day and
     * part-day lists keyed by date string only (site/department matching
     * happens per-user in holidayForUserOnDate() - a holiday can be scoped
     * by either, independently, so pre-splitting by site alone would either
     * drop a department-scoped holiday or apply it too broadly). Built once
     * per request/month by a per-user-per-day loop, instead of querying per
     * user per day.
     *
     * Returns ['full' => [dateStr => Holiday[]], 'part' => [dateStr => Holiday[]]].
     */
    public static function holidayMapsForMonth(int $month, int $year): array
    {
        $rows = Holiday::where('status', 1)
            ->whereYear('date', $year)->whereMonth('date', $month)->get();

        $full = [];
        $part = [];
        foreach ($rows as $holiday) {
            $dateStr = Carbon::parse($holiday->date)->toDateString();
            if ($holiday->isFullDay()) {
                $full[$dateStr][] = $holiday;
            } else {
                $part[$dateStr][] = $holiday;
            }
        }

        return ['full' => $full, 'part' => $part];
    }

    /** Site/department match only - mirrors Holiday::scopeForUser() (NULL on either column means "not restricted by that"). Gender is checked separately via appliesToUser(), since it depends on the user record rather than the query. */
    private static function holidayReachesUser(Holiday $holiday, User $user, ?int $siteId): bool
    {
        $siteOk = !$holiday->site_id || $holiday->site_id === $siteId;
        $deptOk = !$holiday->department_id || $holiday->department_id === $user->department_id;
        return $siteOk && $deptOk;
    }

    /**
     * The specific Holiday applying to $user on $dateStr, picked from a
     * holidayMapsForMonth() result - a full-day holiday if one applies,
     * otherwise the most generous part-day concession that applies to this
     * user (gender-scoped concessions only count for that gender), otherwise
     * null. $siteId is the user's own site_id (or null for no site).
     */
    public static function holidayForUserOnDate(array $maps, User $user, ?int $siteId, string $dateStr): ?Holiday
    {
        foreach ($maps['full'][$dateStr] ?? [] as $candidate) {
            if (self::holidayReachesUser($candidate, $user, $siteId)) {
                return $candidate;
            }
        }

        $best = null;
        foreach ($maps['part'][$dateStr] ?? [] as $candidate) {
            if (!self::holidayReachesUser($candidate, $user, $siteId) || !$candidate->appliesToUser($user)) {
                continue;
            }
            if (!$best || $candidate->concessionMinutes() > $best->concessionMinutes()) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * Same result as holidayForUserOnDate(), but for a single row at a time
     * (two direct queries) instead of a pre-fetched month batch - for a
     * caller iterating attendance rows rather than every day of a month
     * (e.g. a DataTable listing whichever rows exist in an arbitrary date
     * range), where a per-month batch doesn't fit naturally.
     */
    public static function holidayForUserOnDateSingle(User $user, string $dateStr): ?Holiday
    {
        $full = Holiday::where('status', 1)
            ->forUser($user)
            ->whereDate('date', $dateStr)
            ->where('holiday_type', 'full')
            ->get()
            ->first(fn ($h) => $h->appliesToUser($user));
        if ($full) {
            return $full;
        }

        return Holiday::where('status', 1)
            ->forUser($user)
            ->whereDate('date', $dateStr)
            ->whereIn('holiday_type', ['half', 'hours'])
            ->get()
            ->filter(fn ($h) => $h->appliesToUser($user))
            ->sortByDesc(fn ($h) => $h->concessionMinutes())
            ->first();
    }
}
