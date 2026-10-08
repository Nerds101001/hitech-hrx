<?php

namespace App\Services;

use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use Carbon\Carbon;

/**
 * Period + scoring engine for DWM-driven KPI/KRA tracking.
 *
 * Periods per frequency (fiscal year Apr-Mar):
 *  - daily:       each calendar day, due same day
 *  - weekly:      Mon-Sun week, due on check_day (ISO 1-7, default Saturday)
 *  - fortnightly: 1st-15th and 16th-end of month, due last day of the half
 *  - monthly:     calendar month, due on check_day of month (default last day)
 *  - quarterly:   fiscal quarter, due check_month (1-3 within quarter) + check_day
 *  - yearly:      fiscal year Apr-Mar, due check_month (calendar) + check_day
 *
 * Scoring:
 *  - timeline: completed on/before due date = max_marks, otherwise late_score
 *  - target:   min(achieved / target, 1) * max_marks
 */
class KpiPeriodService
{
    /** Current period window [start, end, due] for an assignment. */
    public static function currentPeriod(KpiAssignment $a, ?Carbon $today = null): array
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();

        switch ($a->frequency) {
            case 'daily':
                return [$today->copy(), $today->copy(), $today->copy()];

            case 'weekly': {
                $start = $today->copy()->startOfWeek(Carbon::MONDAY);
                $end = $start->copy()->addDays(6);
                $dueDow = min(max((int)($a->check_day ?: 6), 1), 7); // default Saturday
                $due = $start->copy()->addDays($dueDow - 1);
                return [$start, $end, $due];
            }

            case 'fortnightly': {
                if ($today->day <= 15) {
                    $start = $today->copy()->startOfMonth();
                    $end = $start->copy()->addDays(14);
                } else {
                    $start = $today->copy()->startOfMonth()->addDays(15);
                    $end = $today->copy()->endOfMonth()->startOfDay();
                }
                return [$start, $end, $end->copy()];
            }

            case 'monthly': {
                $start = $today->copy()->startOfMonth();
                $end = $today->copy()->endOfMonth()->startOfDay();
                $due = $a->check_day
                    ? $start->copy()->addDays(min((int)$a->check_day, $end->day) - 1)
                    : $end->copy();
                return [$start, $end, $due];
            }

            case 'quarterly': {
                // Fiscal quarters: Apr-Jun, Jul-Sep, Oct-Dec, Jan-Mar
                $m = $today->month;
                $qStartMonth = $m >= 4 ? (int)(floor(($m - 4) / 3) * 3 + 4) : ($m <= 3 ? 1 : 4);
                $start = Carbon::create($today->year, $qStartMonth, 1);
                $end = $start->copy()->addMonths(3)->subDay();
                $due = $end->copy();
                if ($a->check_month) {
                    $dueMonth = $start->copy()->addMonths(min(max((int)$a->check_month, 1), 3) - 1);
                    $day = min((int)($a->check_day ?: $dueMonth->copy()->endOfMonth()->day), $dueMonth->copy()->endOfMonth()->day);
                    $due = Carbon::create($dueMonth->year, $dueMonth->month, $day);
                }
                return [$start, $end->startOfDay(), $due->startOfDay()];
            }

            case 'yearly': {
                // Fiscal year Apr-Mar
                $start = $today->month >= 4
                    ? Carbon::create($today->year, 4, 1)
                    : Carbon::create($today->year - 1, 4, 1);
                $end = $start->copy()->addYear()->subDay();
                $due = $end->copy();
                if ($a->check_month) {
                    $y = $a->check_month >= 4 ? $start->year : $start->year + 1;
                    $dueMonth = Carbon::create($y, min(max((int)$a->check_month, 1), 12), 1);
                    $day = min((int)($a->check_day ?: $dueMonth->copy()->endOfMonth()->day), $dueMonth->copy()->endOfMonth()->day);
                    $due = Carbon::create($y, $dueMonth->month, $day);
                }
                return [$start, $end->startOfDay(), $due->startOfDay()];
            }
        }

        return [$today->copy(), $today->copy(), $today->copy()];
    }

    /**
     * Ensure a check row exists for the period containing $day (today by
     * default) and close out (score) any past-period rows still pending.
     *
     * $day exists because a figure does not always arrive inside the month it
     * belongs to. Sales months stay open to the 5th and can be reopened for
     * weeks; a data owner fills last month's call hours this month. Resolving
     * the check from today would file that figure against the wrong period --
     * and, worse, recalculate it there, overwriting a month nobody touched.
     */
    public static function sync(KpiAssignment $a, ?Carbon $day = null): KpiCheck
    {
        [$start, $end, $due] = self::currentPeriod($a, $day);

        // One check per (assignment, period_start) -- the table has a unique
        // index on exactly that, so period_start is the identity whether we
        // like it or not. What it does NOT tell us is the window: a monthly
        // April and a fiscal year both start on 1 April. recordAchievement()
        // therefore checks the window before writing; see the note there.
        $check = KpiCheck::firstOrCreate(
            ['kpi_assignment_id' => $a->id, 'period_start' => $start->toDateString()],
            [
                'tenant_id' => $a->tenant_id,
                'user_id' => $a->user_id,
                'period_end' => $end->toDateString(),
                'due_date' => $due->toDateString(),
                'status' => 'pending',
            ]
        );

        // Close overdue past periods: pending + period ended => missed/scored
        $stale = KpiCheck::where('kpi_assignment_id', $a->id)
            ->where('status', 'pending')
            ->whereKeyNot($check->getKey())
            ->whereDate('period_end', '<', Carbon::today())
            ->get();
        foreach ($stale as $s) {
            if ($a->scoring_type === 'target') {
                // Score whatever was achieved by period end (nothing entered = 0)
                $s->status = 'missed';
                $s->score = self::targetScore($a, (float)($s->achieved_value ?? 0));
            } else {
                // Missed is not the same as late: the work never happened, so
                // the late policy does not rescue it. 'fixed' keeps its
                // configured consolation score, everything else scores nothing.
                $s->status = 'missed';
                $s->score = ($a->late_policy ?? 'fixed') === 'fixed' ? $a->late_score : 0;
            }
            $s->save();
        }

        return $check;
    }

    /** Employee marks a timeline task complete for the current period. */
    public static function markCompleted(KpiAssignment $a, ?string $remarks = null): KpiCheck
    {
        $check = self::sync($a);
        if (in_array($check->status, ['completed_on_time', 'completed_late'])) {
            return $check; // already done for this period
        }

        $now = Carbon::now();
        $onTime = $now->copy()->startOfDay()->lte($check->due_date);
        $check->status = $onTime ? 'completed_on_time' : 'completed_late';
        $check->completed_at = $now;
        if ($remarks) $check->remarks = $remarks;

        if ($a->scoring_type === 'timeline') {
            $check->score = $onTime
                ? $a->max_marks
                : self::lateScore($a, $now, Carbon::parse($check->due_date));
        }
        $check->save();

        return $check;
    }

    /** Record achieved value for a target-based assignment (current period). */
    public static function recordAchievement(KpiAssignment $a, float $achieved, ?int $evaluatorId = null, ?string $remarks = null, ?Carbon $day = null): KpiCheck
    {
        $check = self::sync($a, $day);

        // The row holding this period_start may span a different window. 297 on
        // production do: these KPIs were yearly when their checks were made and
        // are monthly now, so a monthly April resolves onto a year-to-date row.
        // Writing would replace a running annual total with one month's figure
        // -- it moved 258 marks across 24 people before this guard existed. What
        // to do with those rows is a decision about the assignment, not
        // something a scoring pass should settle silently.
        [, $expectedEnd] = self::currentPeriod($a, $day);
        if ($check->period_end && !Carbon::parse($check->period_end)->isSameDay($expectedEnd)) {
            return $check;
        }

        $check->achieved_value = $achieved;
        $check->score = self::targetScore($a, $achieved);
        // Judged from the day being scored, not the day the scoring ran. An
        // auto-sourced figure involves no human deadline: re-reading August's
        // sales in September would otherwise stamp every row 'Late' and put
        // that word on the sheet of somebody who did nothing late.
        $onTime = ($day ? $day->copy()->startOfDay() : Carbon::today())->lte($check->due_date);
        $check->status = $onTime ? 'completed_on_time' : 'completed_late';
        $check->completed_at = Carbon::now();
        if ($evaluatorId) $check->evaluated_by_id = $evaluatorId;
        if ($remarks) $check->remarks = $remarks;
        $check->save();

        return $check;
    }

    /**
     * Value-based score for the achieved figure.
     *
     *  target    : min(achieved/target, 1) * max_marks          (linear)
     *  threshold : max_marks once achieved/target >= threshold%, else 0
     *  banded    : marks of the highest band whose min% is met
     *
     * A threshold or banded assignment with nothing configured falls back to
     * linear rather than silently scoring everyone zero.
     *
     * "Result %" (see resultPercent) is achieved ÷ target for an increase
     * KPI and target ÷ achieved for a decrease KPI. A linear KPI stops at 100%
     * unless the assignment sets result_cap_percent, which lets
     * over-achievement score above the KPI's marks.
     */
    public static function targetScore(KpiAssignment $a, float $achieved): float
    {
        $pct = self::resultPercent($a, $achieved);
        if ($pct === null) return 0.0;

        if ($a->scoring_type === 'threshold' && $a->threshold_percent !== null) {
            return $pct + 1e-9 >= (float) $a->threshold_percent ? round((float) $a->max_marks, 2) : 0.0;
        }

        if ($a->scoring_type === 'banded') {
            $bands = $a->bands;
            if (is_string($bands)) $bands = json_decode($bands, true);
            if (is_array($bands) && $bands) {
                // Highest qualifying band wins, whatever order they are stored in.
                $best = null;
                foreach ($bands as $b) {
                    $min = (float) ($b['min'] ?? 0);
                    if ($pct + 1e-9 >= $min && ($best === null || $min > (float) ($best['min'] ?? 0))) {
                        $best = $b;
                    }
                }
                $marks = $best ? (float) ($best['marks'] ?? 0) : 0.0;
                // Never more than the KPI's own maximum (user decision 2026-09-30).
                // The Sales rule book's top tiers paid past it - YoY 30 on a 25-mark
                // KPI, New Customer Wins 15/13, Market Share 15/10 - and scorecards
                // showed "30 / 25".
                if ((float) $a->max_marks > 0) {
                    $marks = min($marks, (float) $a->max_marks);
                }

                // Never below zero: a bad result scores 0, never a negative mark
                // (user decision 2026-10-08). A band set up as a penalty no longer
                // deducts - it bottoms out at 0 like any other KPI.
                return max(0.0, round($marks, 2));
            }
        }

        $ceiling = $a->result_cap_percent !== null ? (float) $a->result_cap_percent : 100.0;

        // Floored at zero too: a negative result (e.g. revenue below a target that
        // reads as a negative %) scores 0, never a negative mark.
        return max(0.0, round(min($pct, $ceiling) / 100 * $a->max_marks, 2));
    }

    /**
     * The KPI's result as a percentage, or null when it has no usable target.
     *
     *  increase (default): achieved ÷ target
     *  decrease          : target ÷ achieved — less is better (waste, mandays,
     *                      complaints). A zero target is "none allowed":
     *                      nothing achieved = 100%, anything at all = 0%.
     *                      Nothing achieved against a real target is the best
     *                      result there can be, so it earns the ceiling.
     *
     * result_cap_percent, when set, bounds the result. It is left unset for
     * every KPI that predates it, which keeps their scoring unchanged.
     */
    public static function resultPercent(KpiAssignment $a, float $achieved): ?float
    {
        $target = (float) $a->target_value;
        $cap = $a->result_cap_percent !== null ? (float) $a->result_cap_percent : null;

        if (($a->trend ?? 'increase') === 'decrease') {
            if ($target <= 0) {
                return $achieved <= 0 ? 100.0 : 0.0;
            }
            if ($achieved <= 0) {
                return $cap ?? 100.0;
            }
            $pct = ($target / $achieved) * 100;
        } else {
            if ($target <= 0) return null;
            $pct = ($achieved / $target) * 100;
        }

        return $cap !== null ? min($pct, $cap) : $pct;
    }

    /**
     * Marks for work that landed after the due date.
     *
     *  none    : lateness is not penalised
     *  fixed   : the flat late_score (original behaviour, still the default)
     *  percent : late_percent of max_marks
     *  per_day : full marks, less per_day_penalty for each day beyond grace_days
     */
    public static function lateScore(KpiAssignment $a, ?Carbon $completedAt = null, ?Carbon $dueDate = null): float
    {
        $max = (float) $a->max_marks;

        switch ($a->late_policy ?? 'fixed') {
            case 'none':
                return round($max, 2);

            case 'percent':
                return round($max * ((float) ($a->late_percent ?? 0) / 100), 2);

            case 'per_day':
                if (!$completedAt || !$dueDate) return round((float) $a->late_score, 2);
                $daysLate = $dueDate->diffInDays($completedAt->copy()->startOfDay(), false);
                $billable = max(0, $daysLate - (int) ($a->grace_days ?? 0));
                return round(max(0, $max - $billable * (float) ($a->per_day_penalty ?? 0)), 2);

            case 'fixed':
            default:
                return round((float) $a->late_score, 2);
        }
    }

    /** Sync all active assignments for a user and return them with current checks. */
    public static function syncUser(int $userId)
    {
        $assignments = KpiAssignment::with('routine')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('category')
            ->orderByRaw("FIELD(frequency, 'daily','weekly','fortnightly','monthly','quarterly','yearly')")
            ->get();

        foreach ($assignments as $a) {
            $a->setRelation('currentCheck', self::sync($a));
        }

        return $assignments;
    }

    /**
     * Human label for a period, shaped by frequency — "August 2026" for a
     * monthly assignment, "Q2 FY26-27" for quarterly, a date range for
     * anything shorter. Matches the fiscal-quarter/year definition in
     * currentPeriod() (Apr-Mar) so a label always names the same window the
     * scoring engine actually used.
     */
    public static function periodLabel(string $frequency, $periodStart, $periodEnd): string
    {
        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);

        return match ($frequency) {
            'daily' => $start->format('d M Y'),
            'monthly' => $start->format('F Y'),
            'quarterly' => self::fiscalQuarterLabel($start),
            'yearly' => self::fiscalYearLabel($start),
            default => $start->format('d M') . ' – ' . $end->format('d M Y'), // weekly, fortnightly
        };
    }

    private static function fiscalQuarterLabel(Carbon $start): string
    {
        $q = (int) floor((($start->month - 4 + 12) % 12) / 3) + 1; // Apr-Jun=1 ... Jan-Mar=4
        return "Q{$q} " . self::fiscalYearLabel($start);
    }

    private static function fiscalYearLabel(Carbon $start): string
    {
        $fyStart = $start->month >= 4 ? $start->year : $start->year - 1;
        return 'FY' . substr((string) $fyStart, 2, 2) . '-' . substr((string) ($fyStart + 1), 2, 2);
    }
}
