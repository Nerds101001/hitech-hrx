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
 * Safe to re-run: recordAchievement() overwrites the current period's check
 * rather than adding another, so a nightly schedule keeps scores current.
 */
class KpiAutoScoreCommand extends Command
{
    protected $signature = 'kpi:auto-score
                            {--user= : only this user id}
                            {--dry   : show what would be written, change nothing}';

    protected $description = 'Compute and score KPIs whose data_source is auto:*';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');

        $assignments = KpiAssignment::query()
            ->where('is_active', true)
            ->where('data_source', 'like', 'auto:%')
            ->when($this->option('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->get();

        if ($assignments->isEmpty()) {
            $this->info('No auto-sourced KPI assignments found.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "Scoring {$assignments->count()} auto-sourced KPI(s)...");
        $rows = [];
        $scored = $skipped = 0;

        foreach ($assignments as $a) {
            [$start, $end] = KpiPeriodService::currentPeriod($a);
            $achieved = KpiAutoSourceService::compute($a, $start, $end);

            if ($achieved === null) {
                $skipped++;
                $rows[] = [$a->user_id, substr($a->title, 0, 26), $a->data_source, '—', 'no data'];
                continue;
            }

            $score = KpiPeriodService::targetScore($a, $achieved);
            if (!$dry) {
                KpiPeriodService::recordAchievement($a, $achieved);
            }
            $scored++;
            $rows[] = [$a->user_id, substr($a->title, 0, 26), $a->data_source, $achieved, "{$score} / {$a->max_marks}"];
        }

        $this->table(['user', 'kpi', 'source', 'achieved', 'score'], $rows);
        $this->info(($dry ? 'Would score' : 'Scored') . " {$scored}, skipped {$skipped} with no data.");

        return self::SUCCESS;
    }
}
