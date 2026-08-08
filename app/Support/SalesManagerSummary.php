<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The sales figures behind a sales manager's dashboard.
 *
 * This lived inline in DashboardController::index(), which serves "/". But the
 * delegate switch and the sidebar both send a manager to /user/dashboard,
 * served by UserDashboardController - and that renders the SAME manager view
 * without ever passing $sm, so every sales figure fell back to its `?? 0`
 * default and the page read zero while "/" showed real numbers.
 *
 * One implementation, called from both, so they cannot drift again.
 *
 * Money in sales_pipelines / sales_pipeline_months is in LAKHS.
 */
class SalesManagerSummary
{
    /** Empty shape, so a view never has to guard every key. */
    public static function blank(): array
    {
        return [
            'pipelineValueL' => 0.0, 'dealsInProgress' => 0, 'newLeads' => 0,
            'expectedClosingL' => 0.0, 'funnel' => [], 'targetL' => 0.0,
            'achievedL' => 0.0, 'forecastL' => 0.0, 'pendingL' => 0.0, 'bookL' => 0.0,
            'achievePct' => 0, 'pendingPct' => 0, 'onTrack' => 0, 'atRisk' => 0,
            'offTrack' => 0, 'topPerformers' => [], 'perf' => [], 'snapshot' => [],
            'activity' => [], 'goals' => [], 'schedule' => [], 'feed' => [], 'alerts' => [],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection  $deployMembers   the manager's direct reports
     * @param  \Illuminate\Support\Collection  $teamDeployment  each report plus today's status
     */
    public static function build($deployMembers, $teamDeployment, int $pendingLeaveRequests = 0): array
    {
        $sm = [
            'pipelineValueL' => 0.0, 'dealsInProgress' => 0, 'newLeads' => 0,
            'expectedClosingL' => 0.0, 'funnel' => [], 'targetL' => 0.0,
            'achievedL' => 0.0, 'achievePct' => 0, 'onTrack' => 0, 'atRisk' => 0,
            'offTrack' => 0, 'topPerformers' => [], 'perf' => [], 'snapshot' => [],
            'activity' => [], 'goals' => [], 'schedule' => [], 'feed' => [], 'alerts' => [],
        ];
        try {
            // Direct reports = the sales team the manager owns.
            $repIds = $deployMembers->pluck('id')->all();
            // Current financial year month keys (Apr–Mar), up to this month.
            $fyStartYear = now()->month >= 4 ? now()->year : now()->year - 1;
            $fyMonths = [];
            $cur = \Carbon\Carbon::create($fyStartYear, 4, 1);
            $end = now()->copy()->endOfMonth();
            while ($cur <= $end) { $fyMonths[] = $cur->format('Y-m'); $cur->addMonth(); }
            $thisMonthKey = now()->format('Y-m');

            if (!empty($repIds)) {
                // Every column a team member can hold a customer through. Keying
                // the whole dashboard off salesperson_id alone showed a CCare /
                // New Biz manager an empty page — Shallu's 20 reports hold their
                // customers as CCare or New Biz and own almost nothing AS
                // salesperson, so pipeline value, deals and each rep's sales all
                // read zero while the Sales Report showed thousands of rows.
                $roleCols = ['salesperson_id', 'ccare_id', 'new_biz_id'];
                if (\Illuminate\Support\Facades\Schema::hasColumn('sales_pipelines', 'new_biz_2_id')) {
                    $roleCols[] = 'new_biz_2_id';
                }
                $heldByTeam = function ($q) use ($roleCols, $repIds) {
                    $q->where(function ($w) use ($roleCols, $repIds) {
                        foreach ($roleCols as $i => $col) {
                            $i === 0 ? $w->whereIn($col, $repIds) : $w->orWhereIn($col, $repIds);
                        }
                    });
                };

                // ---- Active pipeline (is_locked = 0) owned by the team ----
                $pipeRows = DB::table('sales_pipelines')
                    ->where($heldByTeam)->where('is_locked', 0)
                    ->get(array_merge(['id', 'total_business_potential', 'status_stage'], $roleCols));
                $sm['dealsInProgress'] = $pipeRows->count();
                $sm['pipelineValueL'] = (float) $pipeRows->sum('total_business_potential');

                // Bucket free-text stages into the 5 funnel stages.
                $stageBucket = function ($s) {
                    $s = strtolower(trim((string) $s));
                    if ($s === '' || str_contains($s, 'prospect') || str_contains($s, 'introduction') || str_contains($s, 'to visit')) return 'New Leads';
                    if (str_contains($s, 'quot')) return 'Proposal';
                    if (str_contains($s, 'negoti') || str_contains($s, 'rate')) return 'Negotiation';
                    if (str_contains($s, 'clos') || str_contains($s, 'won') || str_contains($s, 'order')) return 'Closing';
                    return 'Qualified'; // sample, trial, under discussion, etc.
                };
                $funnel = ['New Leads' => ['n' => 0, 'v' => 0.0], 'Qualified' => ['n' => 0, 'v' => 0.0],
                           'Proposal' => ['n' => 0, 'v' => 0.0], 'Negotiation' => ['n' => 0, 'v' => 0.0],
                           'Closing' => ['n' => 0, 'v' => 0.0]];
                foreach ($pipeRows as $r) {
                    $b = $stageBucket($r->status_stage);
                    $funnel[$b]['n']++; $funnel[$b]['v'] += (float) $r->total_business_potential;
                }
                $sm['funnel'] = $funnel;
                $sm['newLeads'] = $funnel['New Leads']['n'];
                $sm['expectedClosingL'] = $funnel['Closing']['v'] + $funnel['Negotiation']['v'];

                // ---- Targets vs realized (this FY) per rep ----
                $allPipeIds = DB::table('sales_pipelines')->where($heldByTeam)->pluck('id');
                $salesByRep = []; $fcstByRep = []; $pendingByRep = [];
                $teamAchievedL = 0.0; $teamForecastL = 0.0; $teamPendingL = 0.0;
                if ($allPipeIds->isNotEmpty()) {
                    // Per rep: a customer counts for whichever teammate holds it,
                    // in whatever role. One customer can legitimately count for
                    // two teammates (its salesperson and its CCare), which is
                    // right for an individual scorecard...
                    foreach ($roleCols as $col) {
                        $rows = DB::table('sales_pipelines as sp')
                            ->join('sales_pipeline_months as m', 'm.sales_pipeline_id', '=', 'sp.id')
                            ->whereIn('sp.' . $col, $repIds)
                            ->whereIn('m.month_year', $fyMonths)
                            ->groupBy('sp.' . $col)
                            ->selectRaw('sp.' . $col . ' uid, SUM(m.sale_amount) sold,
                                         SUM(m.forecast_amount) fcst, SUM(m.pending_amount) pend')
                            ->get();
                        foreach ($rows as $r) {
                            $salesByRep[$r->uid]    = ($salesByRep[$r->uid] ?? 0) + (float) $r->sold;
                            $fcstByRep[$r->uid]     = ($fcstByRep[$r->uid] ?? 0) + (float) $r->fcst;
                            $pendingByRep[$r->uid]  = ($pendingByRep[$r->uid] ?? 0) + (float) $r->pend;
                        }
                    }
                    // ...but the TEAM total must count each customer once, or a
                    // row with both a salesperson and a CCare on this team would
                    // be added twice.
                    $teamTotals = DB::table('sales_pipeline_months')
                        ->whereIn('sales_pipeline_id', $allPipeIds)
                        ->whereIn('month_year', $fyMonths)
                        ->selectRaw('SUM(sale_amount) sold, SUM(forecast_amount) fcst, SUM(pending_amount) pend')
                        ->first();
                    $teamAchievedL = (float) ($teamTotals->sold ?? 0);
                    $teamForecastL = (float) ($teamTotals->fcst ?? 0);
                    $teamPendingL  = (float) ($teamTotals->pend ?? 0);
                }
                $targetByRep = DB::table('salesperson_targets')
                    ->whereIn('salesperson_id', $repIds)->whereIn('month_year', $fyMonths)
                    ->groupBy('salesperson_id')->selectRaw('salesperson_id uid, SUM(kingo_target) tgt')
                    ->pluck('tgt', 'uid')->toArray();

                $nameOf = $deployMembers->keyBy('id');
                $perf = [];
                foreach ($repIds as $uid) {
                    $sold = (float) ($salesByRep[$uid] ?? 0);
                    $fcst = (float) ($fcstByRep[$uid] ?? 0);
                    $pend = (float) ($pendingByRep[$uid] ?? 0);
                    $tgt  = (float) ($targetByRep[$uid] ?? 0);
                    // Sales vs FORECAST is the headline: targets are set for
                    // barely any rep, while every pipeline row carries a
                    // forecast, so a target-based percentage read 0% for a team
                    // that was in fact selling.
                    $pct     = $fcst > 0 ? (int) round($sold / $fcst * 100) : ($sold > 0 ? 100 : 0);
                    $pctPend = ($sold + $pend) > 0 ? (int) round($sold / ($sold + $pend) * 100) : 0;
                    $u = $nameOf->get($uid);
                    $perf[] = [
                        'id' => $uid,
                        'name' => $u ? (method_exists($u, 'getFullName') ? $u->getFullName() : trim($u->first_name . ' ' . $u->last_name)) : ('#' . $uid),
                        'desig' => $u ? optional($u->designation)->name : '',
                        'sold' => $sold, 'target' => $tgt, 'pct' => $pct,
                        'forecast' => $fcst, 'pending' => $pend, 'pctPend' => $pctPend,
                        'book' => $sold + $pend,
                    ];
                }
                // Sort by amount sold desc for the leaderboard.
                usort($perf, fn($a, $b) => $b['sold'] <=> $a['sold']);
                $sm['perf'] = $perf;
                $sm['topPerformers'] = array_slice($perf, 0, 5);
                $sm['targetL']   = array_sum($targetByRep);
                $sm['achievedL'] = $teamAchievedL;   // deduplicated, see above
                $sm['forecastL'] = $teamForecastL;
                $sm['pendingL']  = $teamPendingL;
                $sm['achievePct']    = $sm['forecastL'] > 0 ? (int) round($sm['achievedL'] / $sm['forecastL'] * 100) : 0;
                $sm['pendingPct']    = ($sm['achievedL'] + $sm['pendingL']) > 0
                    ? (int) round($sm['achievedL'] / ($sm['achievedL'] + $sm['pendingL']) * 100) : 0;
                $sm['bookL']         = $sm['achievedL'] + $sm['pendingL'];
                // Buckets describe attainment against FORECAST now, not a target.
                foreach ($perf as $p) {
                    if ($p['pct'] >= 90) $sm['onTrack']++;
                    elseif ($p['pct'] >= 60) $sm['atRisk']++;
                    else $sm['offTrack']++;
                }

                // ---- Team member snapshot (status today + achievement) ----
                $perfById = collect($perf)->keyBy('id');
                $sm['snapshot'] = $teamDeployment->map(function ($d) use ($perfById) {
                    $p = $perfById->get($d->user->id);
                    return [
                        'name' => method_exists($d->user, 'getFullName') ? $d->user->getFullName() : trim($d->user->first_name . ' ' . $d->user->last_name),
                        'desig' => optional($d->user->designation)->name ?? '—',
                        'status' => $d->deploy_status,
                        'pct' => $p['pct'] ?? 0,
                        'pic' => method_exists($d->user, 'getProfilePicture') ? $d->user->getProfilePicture() : null,
                    ];
                })->take(6)->values()->all();

                // ---- Activity overview (this month, derived from pipeline) ----
                $newThisMonth = DB::table('sales_pipelines')->where($heldByTeam)
                    ->where('created_at', '>=', now()->startOfMonth())->count();
                $wonThisMonth = DB::table('sales_pipelines as sp')
                    ->join('sales_pipeline_months as m', 'm.sales_pipeline_id', '=', 'sp.id')
                    ->where($heldByTeam)->where('m.month_year', $thisMonthKey)
                    ->where('m.sale_amount', '>', 0)->distinct()->count('sp.id');
                $proposals = $funnel['Proposal']['n'];
                $sm['activity'] = [
                    ['icon' => 'bx-user-plus', 'label' => 'New Clients', 'value' => $newThisMonth],
                    ['icon' => 'bx-collection', 'label' => 'Active Deals', 'value' => $sm['dealsInProgress']],
                    ['icon' => 'bx-file', 'label' => 'Proposals (Quotation)', 'value' => $proposals],
                    ['icon' => 'bx-handshake', 'label' => 'In Negotiation', 'value' => $funnel['Negotiation']['n']],
                    ['icon' => 'bx-trophy', 'label' => 'Deals with Sales (MTD)', 'value' => $wonThisMonth],
                ];

                // ---- Goals vs achievement (this FY, team totals) ----
                $sm['goals'] = [
                    ['label' => 'Sales vs Forecast', 'target' => round($sm['forecastL'], 1), 'achieved' => round($sm['achievedL'], 1)],
                    ['label' => 'New Clients', 'target' => max(1, $sm['dealsInProgress']), 'achieved' => $newThisMonth],
                    ['label' => 'Pipeline (L)', 'target' => round($sm['pipelineValueL'], 1), 'achieved' => round($sm['expectedClosingL'], 1)],
                    ['label' => 'Closures', 'target' => max(1, $funnel['Negotiation']['n'] + $funnel['Closing']['n']), 'achieved' => $funnel['Closing']['n']],
                ];

                // ---- Team alerts ----
                if ($sm['offTrack'] > 0) $sm['alerts'][] = ['icon' => 'bx-target-lock', 'tone' => 'danger', 'title' => $sm['offTrack'] . ' rep(s) below 60% of forecast', 'sub' => 'Coach and review pipeline'];
                if ($sm['atRisk'] > 0) $sm['alerts'][] = ['icon' => 'bx-error-circle', 'tone' => 'warning', 'title' => $sm['atRisk'] . ' rep(s) at risk (60–90%)', 'sub' => 'Check pipeline progress'];
                if ($pendingLeaveRequests > 0) $sm['alerts'][] = ['icon' => 'bx-calendar', 'tone' => 'info', 'title' => $pendingLeaveRequests . ' leave request(s) pending', 'sub' => 'Awaiting your approval'];
                if ($funnel['Negotiation']['n'] > 0) $sm['alerts'][] = ['icon' => 'bx-trending-up', 'tone' => 'success', 'title' => $funnel['Negotiation']['n'] . ' deal(s) in negotiation', 'sub' => number_format($funnel['Negotiation']['v'], 1) . ' L in play'];
            }
        } catch (\Throwable $e) { Log::warning('Manager sales widgets: ' . $e->getMessage()); }
        return $sm;
    }
}
