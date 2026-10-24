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
    /**
     * Minutes of real work on a Sunday, an OFF Saturday or a full holiday that
     * earn the SP/HP credit (and the 1-day ALP comp-off the Attendance model
     * grants for it). 3h55m: relaxed from a strict 4h at HR's request, so a
     * punch a few minutes short of four hours still counts. Only these off-day
     * credits use it; an ordinary weekday's half-day bar is CREDIT_MINUTES.
     */
    public const OFFDAY_CREDIT_MINUTES = 235;

    /** Minutes of real work that make an ordinary weekday a half day. */
    public const CREDIT_MINUTES = 240;

    /** Minutes of real work a Short Leave day needs to still count as a full Present. */
    public const SHORT_LEAVE_PRESENT_MINUTES = 350;

    /**
     * Put a day's two punches in order before they are saved. Some biometric reports list a
     * day's punches reversed (evening first), and the import stored them as given - the
     * evening punch became the check-in. A check-out before the check-in is either that
     * (swap them) or, for someone on an overnight shift, an evening check-in whose check-out
     * is the next morning (move the check-out to the next day).
     *
     * @return array{0: ?Carbon, 1: ?Carbon} [checkIn, checkOut]
     */
    public static function orderPunches(?Carbon $in, ?Carbon $out, $shift = null): array
    {
        if (!$in || !$out || $out->gte($in)) {
            return [$in, $out];
        }
        $overnight = $shift && $shift->start_time && $shift->end_time
            && Carbon::parse($shift->end_time)->format('H:i:s') <= Carbon::parse($shift->start_time)->format('H:i:s');
        if ($overnight && (int) $in->format('H') >= 12 && (int) $out->format('H') < 12) {
            return [$in, $out->copy()->addDay()];
        }

        return [$out, $in];
    }

    /**
     * Minutes between the two punches, whichever order they were stored in.
     * The biometric report sometimes lists them the other way round (in
     * 18:07, out 10:22) or a night shift ends after midnight (in 19:41, out
     * 05:51); Carbon 3 returns a negative difference for both, and every such
     * day resolved Absent - 41 days across 19 people in Aug-Sep 2026, each
     * charged at the unexplained-absence rate by payroll.
     */
    public static function workedMinutes(?Carbon $in, ?Carbon $out): int
    {
        if (!$in || !$out) {
            return 0;
        }

        return (int) $in->diffInMinutes($out, true);
    }

    /** From this day a leave approved with no paid part stays unpaid, whatever its row reads (see leaveDayIsPaid). */
    public const WHOLLY_UNPAID_FROM = '2026-09-01';

    /**
     * Resolver codes that mean "genuinely at work in some form" for a coarse
     * dashboard count - the "N Present today" cards and trend charts across
     * the admin and manager dashboards, which only ever needed a yes/no, not
     * the full status. These used to count any Attendance row whose raw
     * status text happened to say 'present'/'late'/'work_from_home' - so an
     * uncorrected miss-punch (a real check-in with no check-out, raw status
     * still 'Present' by default) inflated the Present card exactly the same
     * way it inflated payroll, and a worked designated off-Saturday/holiday
     * never counted at all, because its raw status never said 'present'.
     */
    public const PRESENT_LIKE_CODES = [
        'present', 'short-leave-present', 'sp', 'hp',
        'half-day-paid', 'half-day', 'work_from_home', 'wfh', 'late',
    ];

    /**
     * Whether a single Attendance row represents the employee genuinely
     * being at work in some form today, for a coarse dashboard count. Pass
     * the specific Holiday already resolved for this user/date (or null) -
     * see holidayForUserOnDateSingle() for a one-off lookup, or batch via
     * holidayMapsForMonth()/holidayForUserOnDate() when checking many rows
     * across a date range.
     */
    /**
     * Is THIS day of the leave a paid one?
     *
     * `is_lwp` and `lwp_days` describe the whole request, not a day of it. A
     * two-day leave taken with one day of balance left carries is_lwp = true,
     * and reading that flag per-day painted BOTH days unpaid -- 51 days across
     * 43 requests were being shown as unpaid on the calendar despite having
     * been paid for and deducted from the balance.
     *
     * The per-day answer is already written onto the attendance row at
     * approval: 'leave'/'half-Day' for a day the balance covered,
     * 'unpaid_leave' for one it did not. Prefer that.
     *
     * Where no row exists, fall back to the rule the approval hook itself
     * applies: it walks the span in date order and pays for working days
     * until the balance runs out, so the paid days are the leading ones.
     */
    private static function leaveDayIsPaid(User $user, ?LeaveRequest $leave, ?Attendance $attendance, Carbon $date): bool
    {
        if (!$leave || !($leave->leaveType?->is_paid)) {
            return false;
        }

        // Wholly paid or wholly unpaid: the request-level flag is the answer.
        if (!$leave->is_lwp) {
            return true;
        }

        // Approved with no paid part at all: unpaid, whatever the row says now.
        // CCare recording the worked half of an unpaid half-day turns its
        // 'unpaid_leave' row into 'half-day', which read below as paid - the
        // calendar said "(Paid)" and payroll paid the day in full. From
        // September 2026 only: July-August were already paid out on the old
        // reading and stay as they are (decided 2026-09-30).
        if ($date->gte(Carbon::parse(self::WHOLLY_UNPAID_FROM))
            && self::leaveSpanDays($user, $leave) - (float) ($leave->lwp_days ?? 0) <= 0) {
            return false;
        }

        $perDay = $attendance ? strtolower((string) $attendance->status) : null;
        if (in_array($perDay, ['leave', 'paid_leave', 'half-day'], true)) {
            return true;
        }
        if ($perDay === 'unpaid_leave') {
            return false;
        }

        $paidDays = max(0.0, self::leaveSpanDays($user, $leave) - (float) ($leave->lwp_days ?? 0));
        if ($paidDays <= 0) {
            return false;
        }

        $step = $leave->is_half_day ? 0.5 : 1.0;
        $consumed = 0.0;

        for ($d = $leave->from_date->copy(); $d->lte($leave->to_date); $d->addDay()) {
            if (!LeavePolicyService::isWorkingDay($user, $d)) {
                continue;
            }
            if ($d->isSameDay($date)) {
                return $consumed < $paidDays;
            }
            $consumed += $step;
        }

        return false;
    }

    /**
     * A half-day leave with nothing recorded for the other half (under 4h
     * worked): that half is absent. The leave half pays if it was paid
     * ('half-day': half paid, half absent), else nothing is paid
     * ('unpaid_leave'). This used to pay the whole day (paid leave) or half of
     * it (unpaid) for a half nobody worked.
     *
     * Past days from WHOLLY_UNPAID_FROM only: July-August are already paid out,
     * and today or a later day may not have its worked half recorded yet -
     * CCare often marks it the next day. Null = keep the old code.
     */
    private static function unworkedHalfCode(Carbon $date, bool $isPaid): ?string
    {
        if ($date->lt(Carbon::parse(self::WHOLLY_UNPAID_FROM)) || !$date->isPast() || $date->isToday()) {
            return null;
        }

        return $isPaid ? 'half-day' : 'unpaid_leave';
    }

    /**
     * Not cached: a long-running worker would keep a "no other half" answer
     * after the other half is approved. It only runs on half-day leave days.
     */
    private static function otherHalfOf(LeaveRequest $leave): ?LeaveRequest
    {
        return $leave->otherHalf();
    }

    /** Working days the leave actually spans, counted the way approval counts them. */
    private static function leaveSpanDays(User $user, LeaveRequest $leave): float
    {
        if ($leave->is_half_day) {
            return 0.5;
        }

        $days = 0.0;
        for ($d = $leave->from_date->copy(); $d->lte($leave->to_date); $d->addDay()) {
            if (LeavePolicyService::isWorkingDay($user, $d)) {
                $days += 1.0;
            }
        }

        return $days;
    }

    public static function isPresentLikeRow(Attendance $attendance, ?Holiday $holiday): bool
    {
        if (!$attendance->user || !$attendance->check_in_time) {
            return false;
        }
        $r = self::resolve($attendance->user, $attendance->check_in_time->copy(), $attendance, null, $holiday);
        return in_array($r['code'], self::PRESENT_LIKE_CODES, true);
    }

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
        $workMins = $hasRealPunch ? self::workedMinutes($attendance->check_in_time, $attendance->check_out_time) : 0;

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
        $isPaid = self::leaveDayIsPaid($user, $leaveReq, $attendance, $date);
        $isWfh = $leaveReq && strtoupper($leaveReq->leaveType->code ?? '') === 'WFH';

        // First half and second half taken as two requests: the whole day is
        // off. Paid for both halves -> paid leave, neither -> unpaid leave,
        // one of each -> half paid / half unpaid.
        $bothHalvesCode = null;
        if ($isHalfDay && !$isShort && $leaveReq && ($otherHalf = self::otherHalfOf($leaveReq))) {
            $paidHalves = (int) $leaveReq->halfIsPaid() + (int) $otherHalf->halfIsPaid();
            $bothHalvesCode = $paidHalves === 2 ? 'paid_leave' : ($paidHalves === 0 ? 'unpaid_leave' : 'half-day');
            $isPaid = $paidHalves > 0;
        }

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
                $result['code'] = $workMins >= self::OFFDAY_CREDIT_MINUTES ? 'sp' : 'weekly_off';
            } elseif ($isFullHoliday) {
                $result['code'] = $workMins >= self::OFFDAY_CREDIT_MINUTES ? 'hp' : 'holiday';
            } elseif (empty($attendance->admin_reason)) {
                if ($bothHalvesCode && $workMins < $reqMins) {
                    $result['code'] = $bothHalvesCode;
                } elseif ($isWfh) {
                    $result['code'] = 'work_from_home';
                } elseif ($isShort) {
                    if ($workMins >= self::SHORT_LEAVE_PRESENT_MINUTES) {
                        $result['code'] = 'short-leave-present';
                    } elseif ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = 'half-day';
                    } else {
                        $result['code'] = 'absent';
                    }
                    
                    // A real punch-in with no punch-out on a past day is a missed punch, not an
                    // absence - the same rule the ordinary-day branch below already applies.
                    if ($attendance->check_in_time && !$attendance->check_out_time && !$isDummyPunch && $date->isPast() && !$date->isToday()) {
                        $result['code'] = 'miss-punch';
                    }
                    // Approving a short leave creates a dummy row (00:00, no punches) marked
                    // present. There are no times to compute from, so trust that status.
                    if ($isDummyPunch && strtolower((string) $attendance->status) === 'present') {
                        $result['code'] = 'short-leave-present';
                    }
                } elseif ($isHalfDay) {
                    if ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = $isPaid ? 'half-day-paid' : 'half-day';
                    } else {
                        $result['code'] = self::unworkedHalfCode($date, $isPaid) ?? ($isPaid ? 'paid_leave' : 'half-day');
                    }
                } elseif ($isFullDayLeave) {
                    if ($workMins >= $reqMins) {
                        $result['code'] = 'present';
                    } elseif ($workMins >= self::CREDIT_MINUTES) {
                        $result['code'] = $isPaid ? 'half-day-paid' : 'half-day';
                    } else {
                        $result['code'] = $isPaid ? 'paid_leave' : 'unpaid_leave';
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
                // The stored status IS the answer here, paid-ness included. An
                // absence CCare's register covered from the balance is stored
                // 'paid_leave' with no leave request behind it, so isPaid read
                // false: payroll paid the day (it goes by the code) while the
                // calendar called it "Unpaid Leave" (SHRI KANT 4 and 15 Sep 2026).
                if ($result['code'] === 'paid_leave') {
                    $result['isPaid'] = true;
                } elseif ($result['code'] === 'unpaid_leave') {
                    $result['isPaid'] = false;
                }
            }

            // Worked minutes are 0 until the second punch lands, so anyone still at work
            // resolved as absent for the whole day. The miss-punch rule above covers only
            // PAST days - a day still running is not a missed punch, it is unfinished.
            if ($attendance->check_in_time && !$attendance->check_out_time && !$isDummyPunch && $date->isToday() && $result['code'] === 'absent') {
                $result['code'] = 'present';
            }

            return $result;
        }

        // No attendance row at all for this day.
        //
        // A full-day holiday always wins here, even for a day inside an approved
        // leave's date range (e.g. a leave applied Fri-Mon where the Friday is a
        // declared holiday): the employee was never going to work that day
        // regardless of the leave, nothing is deducted from their balance for it
        // (calculateWorkingDays() already skips it), and showing "Holiday" avoids
        // the wrong impression that leave was spent on a day it wasn't (owner's
        // call, 2026-10-24, re: Sachin Sharma EMP-004's Oct 2-5 request where Oct 2
        // is Gandhi Jayanti). This does NOT apply to a coinciding Sunday/weekly-off
        // - that still loses to $matchingLeave below, per the Aug 2026 refactor
        // that made leave win over a coinciding weekly-off so it reads as leave
        // rather than a generic OFF.
        if ($isFullHoliday) {
            $result['code'] = 'holiday';
        } elseif ($matchingLeave) {
            $isShortLeave = $matchingLeave->leaveType?->is_short_leave ?? false;
            $isHalfDayLeave = $matchingLeave->is_half_day || ($matchingLeave->leaveType?->is_half_day ?? false);
            $isPaidLeave = self::leaveDayIsPaid($user, $matchingLeave, null, $date);

            if ($isShortLeave) {
                $result['code'] = 'absent';
            } elseif ($bothHalvesCode) {
                $result['code'] = $bothHalvesCode;
                $isPaidLeave = $bothHalvesCode !== 'unpaid_leave';
            } elseif ($isHalfDayLeave) {
                $result['code'] = self::unworkedHalfCode($date, $isPaidLeave) ?? ($isPaidLeave ? 'paid_leave' : 'half-day');
            } else {
                $result['code'] = $isPaidLeave ? 'paid_leave' : 'unpaid_leave';
            }
            $result['isPaid'] = (bool) $isPaidLeave;
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
