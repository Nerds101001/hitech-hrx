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
     * Ensure a check row exists for the assignment's current period and
     * close out (score) any past-period rows still pending.
     */
    public static function sync(KpiAssignment $a): KpiCheck
    {
        [$start, $end, $due] = self::currentPeriod($a);

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
            ->whereDate('period_end', '<', Carbon::today())
            ->get();
        foreach ($stale as $s) {
            if ($a->scoring_type === 'target') {
                // Score whatever was achieved by period end (nothing entered = 0)
                $s->status = 'missed';
                $s->score = self::targetScore($a, (float)($s->achieved_value ?? 0));
            } else {
                $s->status = 'missed';
                $s->score = $a->late_score;
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
            $check->score = $onTime ? $a->max_marks : $a->late_score;
        }
        $check->save();

        return $check;
    }

    /** Record achieved value for a target-based assignment (current period). */
    public static function recordAchievement(KpiAssignment $a, float $achieved, ?int $evaluatorId = null, ?string $remarks = null): KpiCheck
    {
        $check = self::sync($a);
        $check->achieved_value = $achieved;
        $check->score = self::targetScore($a, $achieved);
        $onTime = Carbon::today()->lte($check->due_date);
        $check->status = $onTime ? 'completed_on_time' : 'completed_late';
        $check->completed_at = Carbon::now();
        if ($evaluatorId) $check->evaluated_by_id = $evaluatorId;
        if ($remarks) $check->remarks = $remarks;
        $check->save();

        return $check;
    }

    public static function targetScore(KpiAssignment $a, float $achieved): float
    {
        $target = (float)$a->target_value;
        if ($target <= 0) return 0.0;
        return round(min($achieved / $target, 1) * $a->max_marks, 2);
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
}
