<?php

namespace App\Console\Commands;

use App\Models\KpiAssignment;
use App\Services\KpiAutoSourceService;
use App\Services\KpiPeriodService;
use Illuminate\Console\Command;

/**
 * Fills in the achieved figure for every auto-sourced KPI and scores it.
 * Manual and approval-based KPIs are left alone — a human owns those.
 *
 * Safe to re-run: recordAchievement() overwrites that period's check rather
 * than adding another, so a nightly schedule keeps scores current.
 *
 * It scores the period containing --as-of (today by default) and, with
 * --months=N, the N-1 periods before it as well. Re-scoring a closed month is
 * not housekeeping: a sales month stays open to the 5th and is often reopened
 * for weeks, so the figure that was true on the 1st is not the figure that is
 * true on the 20th. A month scored once and never revisited freezes a
 * half-entered number into somebody's appraisal.
 */
class KpiAutoScoreCommand extends Command
{
    protected $signature = 'kpi:auto-score
                            {--user=   : only this user id}
                            {--as-of=  : score the period containing this date (Y-m-d or Y-m), default today}
                            {--months=1 : also re-score this many earlier periods}
                            {--dry     : show what would be written, change nothing}
                            {--department= : only people in this department id}
                            {--backfill : also score periods that ended before the KPI was assigned}';

    protected $description = 'Compute and score KPIs whose data_source is auto:*';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $asOf = $this->option('as-of');
        try {
            $anchor = $asOf
                ? (preg_match('/^\d{4}-\d{2}$/', $asOf)
                    ? \Carbon\Carbon::createFromFormat('!Y-m', $asOf)->endOfMonth()->startOfDay()
                    : \Carbon\Carbon::parse($asOf)->startOfDay())
                : \Carbon\Carbon::today();
        } catch (\Throwable $e) {
            $this->error("--as-of={$asOf} is not a date I can read (use Y-m-d or Y-m).");
            return self::FAILURE;
        }
        $months = max(1, (int) $this->option('months'));

        $assignments = KpiAssignment::query()
            ->where('is_active', true)
            ->where('data_source', 'like', 'auto:%')
            ->when($this->option('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($this->option('department'), fn ($q, $d) => $q->whereIn('user_id', \App\Models\User::where('department_id', $d)->select('id')))
            ->get();

        if ($assignments->isEmpty()) {
            $this->info('No auto-sourced KPI assignments found.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY RUN] ' : '')
            . "Scoring {$assignments->count()} auto-sourced KPI(s) over {$months} period(s) to "
            . $anchor->format('d M Y') . ' ...');
        $rows = [];
        $scored = $skipped = $changed = $legacy = 0;

        // Walk back a month at a time. A quarterly or yearly KPI resolves to
        // the same period for several of those steps; scoring it twice is
        // harmless (the check is overwritten) but pointless, so skip repeats.
        $seen = [];
        for ($i = 0; $i < $months; $i++) {
            $day = $anchor->copy()->subMonthsNoOverflow($i);

            foreach ($assignments as $a) {
                [$start, $end] = KpiPeriodService::currentPeriod($a, $day);

                // A KPI cannot be measured before it existed. The scorecard shows
                // any period that has a check row, so creating one for a period
                // that closed before the assignment was made does not just add a
                // harmless zero -- it puts a KPI on a month's sheet that nobody
                // was working to, and its marks into that month's denominator.
                // --backfill lifts this on purpose, to build scorecards for months
                // before the KPIs were set up (Sales / New Biz, Jul-Sep 2026).
                if (!$this->option('backfill') && $a->created_at && $end->lt($a->created_at->copy()->startOfDay())) {
                    continue;
                }

                // A check can already occupy this period_start with a different
                // window: 297 of them do, because these KPIs were yearly when
                // their checks were made and are monthly now. Scoring into it
                // overwrites a year-to-date total with one month; creating a
                // second row shows the KPI twice on that month's sheet. Neither
                // is ours to choose -- report it and leave the row alone.
                if (\App\Models\KpiCheck::where('kpi_assignment_id', $a->id)
                        ->whereDate('period_start', $start->toDateString())
                        ->whereDate('period_end', '!=', $end->toDateString())->exists()) {
                    $legacy++;
                    continue;
                }

                $key = $a->id . '|' . $start->toDateString();
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $achieved = KpiAutoSourceService::compute($a, $start, $end);

                if ($achieved === null) {
                    $skipped++;
                    $rows[] = [$a->user_id, substr($a->title, 0, 24), $start->format('M y'), '—', 'no data', ''];
                    continue;
                }

                $was = \App\Models\KpiCheck::where('kpi_assignment_id', $a->id)
                    ->where('period_start', $start->toDateString())->value('achieved_value');
                $score = KpiPeriodService::targetScore($a, $achieved);
                if (!$dry) {
                    KpiPeriodService::recordAchievement($a, $achieved, null, null, $day);
                }
                $moved = $was === null || abs((float) $was - $achieved) > 0.005;
                $scored++;
                $changed += $moved ? 1 : 0;
                $rows[] = [$a->user_id, substr($a->title, 0, 24), $start->format('M y'),
                    round($achieved, 2), "{$score} / {$a->max_marks}",
                    $moved ? ('was ' . ($was === null ? '—' : round((float) $was, 2))) : ''];
            }
        }

        $this->table(['user', 'kpi', 'period', 'achieved', 'score', 'change'], $rows);
        $this->info(($dry ? 'Would score' : 'Scored') . " {$scored} ({$changed} would move)"
            . ", skipped {$skipped} with no data"
            . ($legacy ? ", left {$legacy} alone (a check already holds that period with a different window)" : '') . '.');

        return self::SUCCESS;
    }
}
