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
            // Kingo/Bingo scorecard, this month, for this manager's own team.
            'scorecardMonth' => '', 'scorecardAchieved' => 0.0, 'repsWithoutTarget' => 0,
            'tierTargets' => ['Bingo' => 0.0, 'Kingo' => 0.0, 'Super Kingo' => 0.0],
            'tierPct'     => ['Bingo' => 0.0, 'Kingo' => 0.0, 'Super Kingo' => 0.0],
            'staff' => [], 'scoreTop' => [], 'scoreBehind' => [],
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
            // Kingo/Bingo scorecard, this month, for this manager's own team.
            'scorecardMonth' => '', 'scorecardAchieved' => 0.0, 'repsWithoutTarget' => 0,
            'tierTargets' => ['Bingo' => 0.0, 'Kingo' => 0.0, 'Super Kingo' => 0.0],
            'tierPct'     => ['Bingo' => 0.0, 'Kingo' => 0.0, 'Super Kingo' => 0.0],
            'staff' => [], 'scoreTop' => [], 'scoreBehind' => [],
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
                // A customer that moved New Biz -> CCare is still its New Biz
                // person's for the months before the move (former_new_biz_id).
                $allPipeIds = DB::table('sales_pipelines')
                    ->where(fn ($q) => $q->where($heldByTeam)->orWhereIn('former_new_biz_id', $repIds))
                    ->pluck('id');
                $salesByRep = []; $fcstByRep = []; $pendingByRep = [];
                $teamAchievedL = 0.0; $teamForecastL = 0.0; $teamPendingL = 0.0;
                if ($allPipeIds->isNotEmpty()) {
                    // Per rep: a customer counts for whichever teammate holds it,
                    // in whatever role. One customer can legitimately count for
                    // two teammates (its salesperson and its CCare), which is
                    // right for an individual scorecard...
                    // Credit each customer once per rep. Summing the role
                    // columns separately counted a rep who is both salesperson
                    // and CCare on the same row twice.
                    $holderCols = array_merge($roleCols, ['former_new_biz_id']);
                    $rowHolders = DB::table('sales_pipelines')
                        ->whereIn('id', $allPipeIds)
                        ->get(array_merge(['id', 'ccare_from'], $holderCols));
                    // Split at the move (`pre` = months before ccare_from), so
                    // each side of a moved customer is credited its own months.
                    // Once a leaver's customers have been handed over, the totals are kept
                    // per month so each month goes to whoever held the customer then.
                    $byMonth = \App\Models\SalesPipelineHandover::hasAny();
                    $fyTotals = DB::table('sales_pipeline_months as m')
                        ->join('sales_pipelines as sp', 'sp.id', '=', 'm.sales_pipeline_id')
                        ->whereIn('m.sales_pipeline_id', $allPipeIds)
                        ->whereIn('m.month_year', $fyMonths)
                        ->groupBy(...($byMonth ? ['m.sales_pipeline_id', 'pre', 'm.month_year'] : ['m.sales_pipeline_id', 'pre']))
                        ->selectRaw('m.sales_pipeline_id pid,
                                     (sp.ccare_from IS NOT NULL AND m.month_year < sp.ccare_from) pre,'
                                     . ($byMonth ? ' m.month_year mk,' : '') . '
                                     SUM(m.sale_amount) sold, SUM(m.forecast_amount) fcst, SUM(m.pending_amount) pend')
                        ->get()->groupBy('pid');
                    $handoverEvents = $byMonth ? \App\Models\SalesPipelineHandover::eventsFor($allPipeIds->all()) : [];
                    $repLookupPerf = array_flip($repIds);
                    foreach ($rowHolders as $row) {
                        foreach ($fyTotals[$row->id] ?? [] as $t) {
                            $onRow = [];
                            $tMk = $t->mk ?? null;
                            foreach ($holderCols as $col) {
                                $holder = $row->{$col} ?? null;
                                if ($tMk !== null && in_array($col, \App\Models\SalesPipelineHandover::ROLE_COLUMNS, true)) {
                                    $holder = \App\Models\SalesPipelineHandover::holderAt($holder ? (int) $holder : null, $col, $tMk, $handoverEvents[$row->id] ?? []);
                                }
                                if ($holder && isset($repLookupPerf[$holder])
                                    && \App\Models\SalesPipeline::creditsSide($row, (int) $holder, (bool) $t->pre, $tMk)) {
                                    $onRow[$holder] = true;
                                }
                            }
                            foreach (array_keys($onRow) as $uid) {
                                $salesByRep[$uid]   = ($salesByRep[$uid] ?? 0) + (float) $t->sold;
                                $fcstByRep[$uid]    = ($fcstByRep[$uid] ?? 0) + (float) $t->fcst;
                                $pendingByRep[$uid] = ($pendingByRep[$uid] ?? 0) + (float) $t->pend;
                            }
                            // ...but the TEAM total must count each customer once, or
                            // a row with both a salesperson and a CCare on this team
                            // would be added twice - and only the months someone on
                            // the team is credited with.
                            if ($onRow) {
                                $teamAchievedL += (float) $t->sold;
                                $teamForecastL += (float) $t->fcst;
                                $teamPendingL  += (float) $t->pend;
                            }
                        }
                    }
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

        // ---- Kingo / Bingo scorecard, this month, for THIS manager's team ----
        // Mirrors the company-wide card on the admin dashboard, scoped to the
        // manager's own reports. Shown to Sales-department managers.
        try {
            $repIds = $deployMembers->pluck('id')->all();
            if (!empty($repIds)) {
                $nameOf = $deployMembers->keyBy('id');
                $mk = now()->format('Y-m');
                $sm['scorecardMonth'] = now()->format('F Y');

                $roleCols = ['salesperson_id', 'ccare_id', 'new_biz_id'];
                if (\Illuminate\Support\Facades\Schema::hasColumn('sales_pipelines', 'new_biz_2_id')) {
                    $roleCols[] = 'new_biz_2_id';
                }

                $tierRow = DB::table('salesperson_targets')
                    ->where('month_year', $mk)->whereIn('salesperson_id', $repIds)
                    ->selectRaw('SUM(bingo_target) b, SUM(kingo_target) k, SUM(super_kingo_target) s')
                    ->first();
                $sm['tierTargets'] = [
                    'Bingo'       => (float) ($tierRow->b ?? 0),
                    'Kingo'       => (float) ($tierRow->k ?? 0),
                    'Super Kingo' => (float) ($tierRow->s ?? 0),
                ];

                // Achievement = realised sales this month from the Sales Report
                // (locked rows), counted for whichever role the rep holds.
                //
                // Summing each role column separately double-counts anyone who
                // holds two roles on the same customer — a rep who is both
                // salesperson and ccare on a row was credited twice, reading up
                // to 39% high. Resolve each row to the distinct set of reps on
                // it, then credit the amount once per rep.
                // former_new_biz_id: a customer that moved New Biz -> CCare is
                // still its New Biz person's for the months before the move.
                $holderCols = array_merge($roleCols, ['former_new_biz_id']);
                $rowRoles = DB::table('sales_pipelines')
                    ->where(function ($w) use ($holderCols, $repIds) {
                        foreach ($holderCols as $i => $c) {
                            $i === 0 ? $w->whereIn($c, $repIds) : $w->orWhereIn($c, $repIds);
                        }
                    })
                    ->where('is_locked', true)->whereNull('deleted_at')
                    ->get(array_merge(['id', 'ccare_from'], $holderCols));

                $monthAmounts = DB::table('sales_pipeline_months')
                    ->whereIn('sales_pipeline_id', $rowRoles->pluck('id'))
                    ->where('month_year', $mk)
                    ->groupBy('sales_pipeline_id')
                    ->selectRaw('sales_pipeline_id pid, SUM(sale_amount) amt')
                    ->pluck('amt', 'pid');

                $repLookup = array_flip($repIds);
                $achievedBySp = [];
                $teamMonthAchieved = 0.0;
                foreach ($rowRoles as $row) {
                    $amt = (float) ($monthAmounts[$row->id] ?? 0);
                    if ($amt == 0.0) {
                        continue;
                    }
                    $beforeMove = !empty($row->ccare_from) && $mk < $row->ccare_from;
                    $onThisRow = [];
                    foreach ($holderCols as $col) {
                        $holder = $row->{$col} ?? null;
                        // The person who held this role in the month, once a leaver's customers have been handed over.
                        if (in_array($col, \App\Models\SalesPipelineHandover::ROLE_COLUMNS, true)) {
                            $holder = \App\Models\SalesPipelineHandover::holderAt($holder ? (int) $holder : null, $col, $mk, \App\Models\SalesPipelineHandover::eventsFor([$row->id])[$row->id] ?? []);
                        }
                        if ($holder && isset($repLookup[$holder])
                            && \App\Models\SalesPipeline::creditsSide($row, (int) $holder, $beforeMove, $mk)) {
                            $onThisRow[$holder] = true;   // set — one credit per rep
                        }
                    }
                    foreach (array_keys($onThisRow) as $spid) {
                        $achievedBySp[$spid] = ($achievedBySp[$spid] ?? 0) + $amt;
                    }
                    // Team headline is deduplicated: a customer with two of the
                    // team on it counts once, and only if someone here is credited.
                    if ($onThisRow) {
                        $teamMonthAchieved += $amt;
                    }
                }
                $sm['scorecardAchieved'] = (float) $teamMonthAchieved;

                foreach ($sm['tierTargets'] as $tier => $t) {
                    $sm['tierPct'][$tier] = $t > 0 ? round($sm['scorecardAchieved'] / $t * 100, 1) : 0.0;
                }

                $targetRows = DB::table('salesperson_targets')
                    ->where('month_year', $mk)->whereIn('salesperson_id', $repIds)
                    ->get(['salesperson_id', 'bingo_target'])->keyBy('salesperson_id');

                $staff = [];
                foreach ($repIds as $uid) {
                    $ach = (float) ($achievedBySp[$uid] ?? 0);
                    $tgt = (float) ($targetRows[$uid]->bingo_target ?? 0);
                    if ($tgt <= 0 && $ach <= 0) {
                        continue;                   // nothing to say about them
                    }
                    $u = $nameOf->get($uid);
                    $staff[] = [
                        'id'   => $uid,
                        'name' => $u ? (method_exists($u, 'getFullName') ? $u->getFullName() : trim($u->first_name . ' ' . $u->last_name)) : ('#' . $uid),
                        'target' => $tgt, 'achieved' => $ach,
                        'pct' => $tgt > 0 ? round($ach / $tgt * 100, 1) : null,
                    ];
                }
                usort($staff, fn($a, $b) => $b['achieved'] <=> $a['achieved']);
                $sm['staff']    = $staff;
                $sm['scoreTop'] = array_slice($staff, 0, 5);

                // Furthest behind: only reps who actually have a target, or the
                // card just lists everyone without one at 0%.
                $behind = array_values(array_filter($staff, fn($r) => $r['pct'] !== null));
                usort($behind, fn($a, $b) => $a['pct'] <=> $b['pct']);
                $sm['scoreBehind'] = array_slice($behind, 0, 5);
                $sm['repsWithoutTarget'] = count(array_filter($staff, fn($r) => $r['pct'] === null));
            }
        } catch (\Throwable $e) {
            Log::warning('Manager scorecard: ' . $e->getMessage());
        }

        return $sm;
    }
}
