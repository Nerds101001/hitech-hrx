<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayGoalNote;
use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NinetyDayGoal\ProgramInvite;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 90-Day Goals. Open to everyone in the Sales department for their own goals;
 * admin, HR and anyone given goals.manage / scorecards.manage see everyone's;
 * a manager sees their team's. These routes are login-only, so every action
 * checks who is asking here.
 */
class NinetyDayGoalController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!Goals::canOpenPage($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'The 90-Day Goals tracker is for the Sales team.');
        }

        $isSales  = Goals::isSalesStaff($user);
        $scope    = Goals::visibleUserIds($user);
        $seesMany = $scope === null || count($scope) > 1;
        $view     = $request->input('view', $seesMany ? 'overview' : 'mine');
        if ($view === 'mine' && !$isSales) {
            $view = 'overview';
        }
        if ($view === 'overview' && !$seesMany) {
            $view = 'mine';
        }

        return $view === 'mine' ? $this->mine($user) : $this->overview($request, $user, $scope, $isSales);
    }

    // ── my goals ─────────────────────────────────────────────────────────

    private function mine(User $user)
    {
        $today = Carbon::today();
        $round = Goals::roundFor($user, $today);
        Goals::ensureCheckins($round, $today);

        $goals = NinetyDayGoal::with('checkins.notes.author')->where('round_id', $round->id)->where('user_id', $user->id)
            ->whereNull('closed_at')->orderBy('id')->get();
        $pending = Goals::pending($round, $today)->groupBy('week_no');
        // A repeat month's Sales Report sale never silently becomes an upsell — it waits here
        // until the salesperson (or admin on their behalf) says yes or no.
        $pendingUpsells = NinetyDayGoalCheckin::with('goal')->where('round_id', $round->id)->where('needs_upsell_confirm', true)->orderBy('week_no')->get();

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'      => 'mine',
            'isAdmin'   => Goals::seesEveryone($user),
            'canSwitch' => Goals::visibleUserIds($user) !== [$user->id],
            'round'     => $round,
            'week'      => Goals::weekOf($round, $today),
            'dates'     => Goals::weekDates($round),
            'goals'     => $goals,
            'pending'   => $pending,
            'pendingUpsells' => $pendingUpsells,
            'today'     => $today,
            'statuses'  => Goals::STATUSES,
            'brands'    => array_keys(Goals::BRANDS),
            'weeks'     => Goals::weeksOf($round),
        ]);
    }

    // ── everyone's goals ─────────────────────────────────────────────────

    private function overview(Request $request, User $viewer, ?array $scope, bool $isSales)
    {
        $today = Carbon::today();
        $roundFilter = $request->input('round') === 'all' ? 'all' : 'current';

        $q = NinetyDayGoal::with(['user.department', 'round', 'checkins.notes.author'])->orderBy('id');
        if ($scope !== null) {
            $q->whereIn('user_id', $scope);
        }
        if ($roundFilter === 'current') {
            $q->whereHas('round', fn ($r) => $r->where('status', 'active'));
        }
        // One list for the whole team, people in name order; the page filters and sorts it.
        $goals = $q->get()->sortBy(fn ($g) => strtolower(trim(optional($g->user)->first_name . ' ' . optional($g->user)->last_name)) . '|' . str_pad($g->id, 9, '0', STR_PAD_LEFT))->values();

        // "This week" only means something for a round that is actually running right now; the cards,
        // the strip and the overdue count follow whichever round filter is selected, so choosing "All
        // rounds, including closed" visibly changes the numbers instead of silently staying on the
        // active round only.
        $trueActive = $goals->filter(fn ($g) => $g->round && $g->round->status === 'active' && !$g->closed_at);
        $scoped = $roundFilter === 'current' ? $trueActive : $goals;
        $weekOfGoal = fn ($g) => Goals::weekOf($g->round, $today);
        $people = Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))->values();

        $summary = [
            'staff'    => $people->count(),
            'people'   => $scoped->pluck('user_id')->unique()->count(),
            'goals'    => $scoped->count(),
            'potential' => round((float) $scoped->sum('monthly_potential'), 2),
            'thisWeekDone'  => $trueActive->filter(fn ($g) => optional($g->checkins->firstWhere('week_no', $weekOfGoal($g)))->status !== null)->count(),
            'thisWeekTotal' => $trueActive->filter(fn ($g) => $g->checkins->firstWhere('week_no', $weekOfGoal($g)) !== null)->count(),
            'overdue'  => $scoped->sum(fn ($g) => $g->checkins->filter(fn ($c) => $c->status === null && $c->due_on->lt($today))->count()),
        ];

        // Rounds in view can be different lengths (an admin-created program can run longer or
        // shorter than the usual 90 days) — span the longest one so nothing gets truncated.
        $weeksSpan = $scoped->isEmpty() ? Goals::WEEKS : $scoped->max(fn ($g) => Goals::weeksOf($g->round));
        $strip = [];
        for ($w = 0; $w < $weeksSpan; $w++) {
            $rows = $scoped->map(fn ($g) => $g->checkins->firstWhere('week_no', $w))->filter();
            $strip[$w] = ['total' => $rows->count(), 'done' => $rows->whereNotNull('status')->count(), 'late' => $rows->where('is_late', true)->count()];
        }

        // Everyone on the team appears, including people who have not added a customer yet.
        $withGoals = $goals->pluck('user_id')->unique();
        $notStarted = $roundFilter === 'current' ? $people->reject(fn ($u) => $withGoals->contains($u->id))->values() : collect();

        $isAdmin = Goals::seesEveryone($viewer);
        // Admin-created programs, newest first, so "New program" has somewhere visible to land —
        // without this an admin who just created one had no way to see it existed at all.
        $programs = $isAdmin
            ? NinetyDayProgram::withCount('rounds')->with('creator')->orderByDesc('id')->limit(20)->get()
                ->map(function ($p) use ($today) {
                    $p->isLive = $today->between($p->starts_on, $p->ends_on);
                    return $p;
                })
            : collect();

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'       => 'overview',
            'canSwitch'  => $isSales,
            'goals'      => $goals,
            'summary'    => $summary,
            'strip'      => $strip,
            'notStarted' => $notStarted,
            'people'     => $people,
            'isAdmin'    => $isAdmin,
            'programs'   => $programs,
            'preUser'    => $request->integer('user_id') ?: null,
            'filters'    => ['round' => $roundFilter],
            'today'      => $today,
            'statuses'   => Goals::STATUSES,
            'weeks'      => $weeksSpan,
            'brands'     => array_keys(Goals::BRANDS),
        ]);
    }

    // ── dashboard and comments ───────────────────────────────────────────

    /** Same rule as the tracker: a salesperson sees their own numbers, a lead sees their team's, admin/HR see all. */
    private function scopeOrRedirect(): array
    {
        $user = Auth::user();
        if (!Goals::canOpenPage($user)) {
            return [null, null, redirect()->route('tenant.dashboard')->with('error', 'The 90-Day Goals tracker is for the Sales team.')];
        }

        return [$user, Goals::visibleUserIds($user), null];
    }

    private function peopleIn(?array $scope)
    {
        return Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))->values();
    }

    /**
     * Which of these dashboard() rows need someone to step in, worst first: off track, silent,
     * dropped, or a Monday that never came. Shared by the live Dashboard and every Past round
     * report, since "needs attention" is a question every round can be asked, not just the
     * one running now.
     */
    private function attentionRows($rows, Carbon $today)
    {
        return $rows->map(function ($r) use ($today) {
            // Same rule as the cards' own overdue count: due today still has until end of day.
            $overdue = $r->goal->checkins->filter(fn ($c) => $c->status === null && $c->due_on->lt($today));
            $r->overdueWeeks = $overdue->count();
            $r->oldestOverdue = $overdue->min('due_on');
            $r->urgency = $r->overdueWeeks > 0 ? (2 + $r->overdueWeeks) : match ($r->status) {
                'Dropped' => 0, 'Off Track' => 3, 'No Report' => 2, default => -1,
            };

            return $r;
        })->filter(fn ($r) => $r->urgency >= 0)->sortByDesc('urgency')->values();
    }

    /**
     * Everything about the current round lives here as one dashboard, in sections — the summary
     * cards, top customers/brands and per-person achievement (already here), then the team as
     * tiles, the week-by-week matrix, the trial-order funnel, and who needs attention. These are
     * all facets of the same round, not separate destinations to click through to.
     */
    public function dashboard(Request $request)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }
        $today = Carbon::today();

        $f = [
            'round'      => $request->input('round') === 'all' ? 'all' : 'current',
            'user_id'    => $request->integer('user_id') ?: null,
            'brand'      => $request->input('brand') ?: null,
            'program_id' => $request->integer('program_id') ?: null,
        ];
        $people = $this->peopleIn($scope);
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }
        // Only programs that actually reach someone in scope — a manager filtering by program
        // should never see a program that has none of their own people in it.
        $programs = NinetyDayProgram::whereHas('rounds', fn ($r) => $r->when($scope !== null, fn ($rr) => $rr->whereIn('user_id', $scope)))
            ->orderByDesc('id')->get();
        if ($f['program_id'] && !$programs->contains('id', $f['program_id'])) {
            $f['program_id'] = null;
        }
        $d = Goals::dashboard($scope, $f);

        // The current round specifically, regardless of the filter above — the team tiles, the
        // matrix, the funnel and the attention list are all inherently about right now.
        $current = Goals::dashboard($scope, ['round' => 'current']);
        $byId = $current['users']->keyBy('id');
        $team = $people->map(function ($p) use ($byId) {
            return $byId->get($p->id, ['id' => $p->id, 'name' => trim($p->first_name . ' ' . $p->last_name), 'department' => optional($p->department)->name,
                'goals' => 0, 'potential' => 0.0, 'achieved' => 0.0, 'pct' => null, 'converted' => 0, 'missing' => 0]);
        })->sortByDesc(fn ($p) => [$p['pct'] ?? -1, $p['achieved']])->values();

        $weeklyGoals = NinetyDayGoal::with(['user.department', 'round', 'checkins'])
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->whereHas('round', fn ($r) => $r->where('status', 'active'))
            ->get()->sortBy(fn ($g) => strtolower(trim(optional($g->user)->first_name . ' ' . optional($g->user)->last_name)))->values();
        // Rounds here can be different lengths (an admin-created program can run longer or
        // shorter than 90 days) — the matrix spans the longest one so nothing is cut off.
        $weeklyGoalsSpan = $weeklyGoals->isEmpty() ? Goals::WEEKS : $weeklyGoals->max(fn ($g) => Goals::weeksOf($g->round));

        $trialing = $current['rows']->where('status', 'Trial Order')->sortByDesc('potential')->values();
        $attention = $this->attentionRows($current['rows'], $today);

        $activePrograms = $programs->filter(fn ($p) => $today->between($p->starts_on, $p->ends_on))->count();

        // Tasks & Alerts: the real "add your customers" / "submit W<n> status" tasks this app
        // already creates, for people in scope, nearest due date first — not a separate feed.
        $tasksAlerts = Task::where('title', 'like', '90-Day Goals:%')
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->whereNotIn('status', Task::CLOSED_STATUSES)
            ->with('user')->orderBy('due_date')->limit(8)->get();

        return view('tenant.performance.ninety-day-goals.dashboard', [
            'tab'      => 'dashboard',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany' => $scope === null || count($scope) > 1,
            'isAdmin'  => Goals::seesEveryone($user),
            'd'        => $d,
            'filters'  => $f,
            'people'   => $people,
            'programs' => $programs,
            'activePrograms' => $activePrograms,
            'tasksAlerts' => $tasksAlerts,
            'today'    => $today,
            'brands'   => array_merge(array_keys(Goals::BRANDS), ['Other']),
            'weeks'    => $weeklyGoalsSpan,
            'team'      => $team,
            'weeklyGoals' => $weeklyGoals,
            'statuses'  => $current['statuses'],
            'trialing'  => $trialing,
            'currentTotal' => $current['cards']['goals'],
            'attention' => $attention,
        ]);
    }

    /** Every filter this page takes, read the same way in adminView(), programDashboard() and adminViewExport(). */
    private function adminViewFilters(Request $request, string $defaultRound = 'current'): array
    {
        return [
            'program_id' => $request->integer('program_id') ?: null,
            'user_id'    => $request->integer('user_id') ?: null,
            'customer'   => trim((string) $request->input('customer')) ?: null,
            'status'     => in_array($request->input('status'), Goals::STATUSES, true) ? $request->input('status') : null,
            'week'       => $request->input('week') !== null && $request->input('week') !== '' ? (int) $request->input('week') : null,
            'brand'      => $request->input('brand') ?: null,
            'round'      => in_array($request->input('round'), ['all', 'current'], true) ? $request->input('round') : $defaultRound,
        ];
    }

    /**
     * The rows for the Customer Submissions / Customer Tracking tables: dashboard()'s own
     * achievement-scored rows, narrowed by the customer/status/week filters that Goals::dashboard()
     * itself doesn't know about (it only takes round/user/brand/program).
     */
    private function adminViewRows(array $d, array $f, Carbon $today)
    {
        return $d['rows']->filter(function ($r) use ($f, $today) {
            if ($f['customer'] && !str_contains(strtolower($r->goal->client_name), strtolower($f['customer']))) {
                return false;
            }
            if ($f['status'] && $r->status !== $f['status']) {
                return false;
            }
            if ($f['week'] !== null) {
                $ck = $r->goal->checkins->firstWhere('week_no', $f['week']);
                if (!$ck || $ck->status === null) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Everything both admin overview pages (adminView() and programDashboard()) show, built once
     * so the two pages can never quietly drift apart on how a number is worked out. Only the
     * default round scope differs between callers (adminView() keeps its original 'current'
     * default; programDashboard() defaults to 'all' — "every program, past and live" per the
     * team's own words), and each page's own controller method adds whatever is specific to it
     * (programDashboard() adds the real trend deltas its KPI cards show).
     */
    private function buildAdminOverviewData(Request $request, string $defaultRound = 'current'): array
    {
        $today = Carbon::today();
        $f = $this->adminViewFilters($request, $defaultRound);

        $programs = NinetyDayProgram::withCount('rounds')->orderByDesc('id')->get();
        if ($f['program_id'] && !$programs->contains('id', $f['program_id'])) {
            $f['program_id'] = null;
        }
        $people = Goals::salesStaff();
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        $d = Goals::dashboard(null, $f);
        $rows = $this->adminViewRows($d, $f, $today);
        // Rounds here can be different lengths (an admin-created program can run longer or
        // shorter than 90 days) — the table spans the longest one in the filtered set, not just
        // whichever page is showing, so the header never changes width as you turn the page.
        $weeksSpan = $rows->isEmpty() ? Goals::WEEKS : $rows->max(fn ($r) => Goals::weeksOf($r->goal->round));

        // Program Overview KPI cards.
        $activePrograms = $programs->filter(fn ($p) => $today->between($p->starts_on, $p->ends_on))->count();
        $cards = $d['cards'];

        // Weekly submission compliance, done ÷ expected, for the chart.
        $compliance = collect($d['strip'])->map(fn ($s, $w) => [
            'week' => 'W' . $w, 'submitted' => $s['done'], 'pending' => max(0, $s['total'] - $s['done']),
            'pct'  => $s['total'] > 0 ? round($s['done'] / $s['total'] * 100) : 0,
        ])->values();

        // Salesperson performance: reuse dashboard()'s own per-user rollup, add "pending this week".
        $thisWeekByUser = [];
        foreach ($d['rows'] as $r) {
            $wk = Goals::weekOf($r->goal->round, $today);
            $ck = $r->goal->checkins->firstWhere('week_no', $wk);
            $thisWeekByUser[$r->user->id]['total'] = ($thisWeekByUser[$r->user->id]['total'] ?? 0) + ($ck ? 1 : 0);
            $thisWeekByUser[$r->user->id]['submitted'] = ($thisWeekByUser[$r->user->id]['submitted'] ?? 0) + ($ck && $ck->status !== null ? 1 : 0);
        }
        $salespeople = $d['users']->map(function ($u) use ($thisWeekByUser) {
            $wk = $thisWeekByUser[$u['id']] ?? ['total' => 0, 'submitted' => 0];
            $u['weekTotal'] = $wk['total'];
            $u['weekSubmitted'] = $wk['submitted'];
            $u['weekPending'] = max(0, $wk['total'] - $wk['submitted']);

            return $u;
        });

        // At risk, same rule as the live Dashboard.
        $atRisk = $this->attentionRows($rows, $today);

        // Program Wise Summary: every program, joined to its own achievement stats (zeros if the
        // current filters leave it with no rows) — a table version of the same byProgram data
        // the bar chart uses, plus real duration/participant counts from the program itself.
        $byProgramById = $d['byProgram']->keyBy('id');
        $programSummary = $programs->map(function ($p) use ($byProgramById) {
            $stats = $byProgramById->get($p->id, ['target' => 0.0, 'achieved' => 0.0, 'pct' => null, 'goals' => 0]);

            return (object) [
                'program' => $p, 'target' => $stats['target'], 'achieved' => $stats['achieved'], 'pct' => $stats['pct'],
                'customers' => $stats['goals'], 'participants' => $p->rounds_count,
            ];
        })->sortByDesc(fn ($r) => $r->program->id)->values();

        // Weekly Update Compliance: this week only, for everyone with an active round in scope —
        // reuses the same weekTotal/weekSubmitted this loop already worked out above.
        $weekCompliance = [
            'updated'    => $salespeople->filter(fn ($u) => $u['weekTotal'] > 0 && $u['weekPending'] === 0)->count(),
            'pending'    => $salespeople->filter(fn ($u) => $u['weekTotal'] > 0 && $u['weekPending'] > 0)->count(),
            'notStarted' => $salespeople->filter(fn ($u) => $u['weekTotal'] === 0)->count(),
        ];
        $weekComplianceTotal = max(1, $weekCompliance['updated'] + $weekCompliance['pending'] + $weekCompliance['notStarted']);
        $weekCompliancePct = round($weekCompliance['updated'] / $weekComplianceTotal * 100);

        // Recent Orders (From Sales Report): real sale_amount rows, newest month first — the
        // Sales Report only stores month-level granularity (no day of sale), so "Month" is what's
        // shown, not a fabricated exact date.
        $pipelineIds = $rows->pluck('goal.sales_pipeline_id')->filter()->unique()->values()->all();
        $rowsByPipeline = $rows->keyBy(fn ($r) => $r->goal->sales_pipeline_id);
        $recentOrders = $pipelineIds
            ? DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $pipelineIds)->where('sale_amount', '>', 0)
                ->orderByDesc('month_year')->limit(8)->get()
                ->map(fn ($m) => $rowsByPipeline->has($m->sales_pipeline_id) ? (object) [
                    'month' => $m->month_year, 'amount' => (float) $m->sale_amount, 'row' => $rowsByPipeline->get($m->sales_pipeline_id),
                ] : null)->filter()->values()
            : collect();

        // Tasks & Alerts: real open tasks this module already creates, plus a few real,
        // computed alerts (at-risk count, the most recent real order, a program ending soon) —
        // never a fabricated activity feed.
        $openTasks = Task::where('title', 'like', '90-Day Goals:%')
            ->when($f['program_id'], fn ($q) => $q->whereIn('user_id', NinetyDayRound::where('program_id', $f['program_id'])->pluck('user_id')))
            ->whereNotIn('status', Task::CLOSED_STATUSES)->with('user')->orderBy('due_date')->limit(4)->get()
            ->map(fn ($t) => (object) [
                'icon' => 'bx-calendar-check', 'title' => $t->title, 'sub' => trim(optional($t->user)->first_name . ' ' . optional($t->user)->last_name),
                'when' => $t->due_date ? Carbon::parse($t->due_date)->format('d M') : '', 'urgent' => $t->due_date && Carbon::parse($t->due_date)->lt($today),
            ]);
        $alerts = collect();
        if ($atRisk->isNotEmpty()) {
            $alerts->push((object) ['icon' => 'bx-error', 'title' => $atRisk->count() . ' customer' . ($atRisk->count() === 1 ? '' : 's') . ' need attention',
                'sub' => 'off track, silent, or dropped', 'when' => 'Now', 'urgent' => true]);
        }
        $lastOrder = $rows->filter(fn ($r) => $r->achieved > 0)->sortByDesc('achieved')->first();
        if ($lastOrder) {
            $alerts->push((object) ['icon' => 'bx-receipt', 'title' => 'Real order on record: ' . $lastOrder->goal->client_name,
                'sub' => '₹' . number_format($lastOrder->achieved, 2) . ' L from the Sales Report', 'when' => '', 'urgent' => false]);
        }
        foreach ($programs->filter(fn ($p) => $today->between($p->starts_on, $p->ends_on) && $today->diffInDays($p->ends_on, false) <= 14) as $p) {
            $alerts->push((object) ['icon' => 'bx-time-five', 'title' => $p->name . ' is ending soon',
                'sub' => $today->diffInDays($p->ends_on) . ' days remaining', 'when' => $p->ends_on->format('d M'), 'urgent' => false]);
        }
        $tasksAlerts = $openTasks->concat($alerts)->take(6)->values();

        // Server-side pagination for the big customer table — the whole filtered set is already
        // in memory (this dataset is hundreds of rows, not millions), but the DOM only ever
        // gets one page of it.
        $perPage = 25;
        $page = max(1, $request->integer('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return [
            'filters'    => $f,
            'programs'   => $programs,
            'people'     => $people,
            'brands'     => array_merge(array_keys(Goals::BRANDS), ['Other']),
            'weeks'      => $weeksSpan,
            'statuses'   => Goals::STATUSES,
            'today'      => $today,
            'cards'      => $cards,
            'activePrograms' => $activePrograms,
            'byProgram'  => $d['byProgram'],
            'statusCounts' => $d['statuses'],
            'orderStatus' => $d['orderStatus'],
            'orderResults' => $d['orderResults'],
            'compliance' => $compliance,
            'salespeople' => $salespeople,
            'programSummary' => $programSummary,
            'weekCompliance' => $weekCompliance,
            'weekCompliancePct' => $weekCompliancePct,
            'recentOrders' => $recentOrders,
            'tasksAlerts' => $tasksAlerts,
            'rows'       => $paged,
            'allRowsCount' => $rows->count(),
            'atRisk'     => $atRisk,
        ];
    }

    /**
     * The "Admin View" page: every program, every salesperson, every customer, in one place —
     * Program Overview / Salesperson Performance / Customer Tracking / Weekly Submissions /
     * Orders & Results / At Risk / Reports, as sections of one page (never separate URLs — the
     * team asked for that explicitly), switched client-side.
     */
    public function adminView(Request $request)
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Admin View is for admin/HR.');
        }

        return view('tenant.performance.ninety-day-goals.admin-view',
            $this->buildAdminOverviewData($request, 'current') + ['tab' => 'admin', 'isAdmin' => true]);
    }

    /**
     * The sidebar's "90-Day Goals ▸ Dashboard" page: a separate, fresher-styled overview built to
     * match the team's own reference design exactly, left entirely apart from adminView() above
     * (which stays exactly as it was) rather than editing it in place. Defaults to every program,
     * past and live, together — the team's own words — with a Program picker to narrow to one.
     */
    public function programDashboard(Request $request)
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'This page is for admin/HR.');
        }
        $data = $this->buildAdminOverviewData($request, 'all');

        // Real trend deltas only — "new in the last 30 days", never a fabricated percentage.
        // Left blank (shown as "—") wherever there is no honest way to compute one.
        $since = Carbon::today()->subDays(30);
        $data['trends'] = [
            'activePrograms' => NinetyDayProgram::where('created_at', '>=', $since)->count(),
            'participants'   => NinetyDayRound::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
            'customers'      => NinetyDayGoal::where('created_at', '>=', $since)->count(),
            'ordersThisMonth' => DB::table('sales_pipeline_months')->where('month_year', Carbon::today()->format('Y-m'))->where('sale_amount', '>', 0)->count(),
        ];

        return view('tenant.performance.ninety-day-goals.program-dashboard', $data + ['tab' => 'admin', 'isAdmin' => true]);
    }

    /** Reports tab's "Export to Excel": the same filtered rows as the table, not just the visible page. */
    public function adminViewExport(Request $request)
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            abort(403);
        }
        $f = $this->adminViewFilters($request);
        $d = Goals::dashboard(null, $f);
        $rows = $this->adminViewRows($d, $f, Carbon::today());
        // Same reasoning as the on-screen table: span the longest round in the export, not a
        // fixed 13 — a shorter round just has blank cells past its own last week.
        $weeksSpan = $rows->isEmpty() ? Goals::WEEKS : $rows->max(fn ($r) => Goals::weeksOf($r->goal->round));

        $filename = '90-day-goals-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($rows, $weeksSpan) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                ['Program', 'Salesperson', 'Customer', 'Product', 'Brand', 'Goal (Monthly ₹L)'],
                array_map(fn ($w) => 'W' . $w, range(0, $weeksSpan - 1)),
                ['Actual (₹L)', 'Achievement %', 'Current Status']
            ));
            foreach ($rows as $r) {
                $weekCells = [];
                for ($w = 0; $w < $weeksSpan; $w++) {
                    $ck = $r->goal->checkins->firstWhere('week_no', $w);
                    $weekCells[] = $ck && $ck->status ? $ck->status : '';
                }
                fputcsv($out, array_merge([
                    optional($r->program)->name ?? 'No program',
                    trim(optional($r->user)->first_name . ' ' . optional($r->user)->last_name),
                    $r->goal->client_name, $r->goal->product, $r->brand, $r->potential,
                ], $weekCells, [$r->achieved, $r->pct, $r->status]));
            }
            fclose($out);
        }, $filename, $headers);
    }

    public function comments(Request $request)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }

        $f = [
            'user_id' => $request->integer('user_id') ?: null,
            'status'  => in_array($request->input('status'), Goals::STATUSES, true) ? $request->input('status') : null,
            'late'    => $request->boolean('late'),
        ];
        $people = $this->peopleIn($scope);
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        return view('tenant.performance.ninety-day-goals.comments', [
            'tab'       => 'comments',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany'  => $scope === null || count($scope) > 1,
            'comments'  => Goals::comments($scope, $f),
            'filters'   => $f,
            'people'    => $people,
            'statuses'  => Goals::STATUSES,
        ]);
    }

    // ── past rounds ──────────────────────────────────────────────────────

    /** Every finished round in scope, as its own pick — no merging different people's history together. */
    public function past(Request $request)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }

        $rounds = NinetyDayRound::with('user.department')
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->where('status', 'closed')
            ->orderByDesc('starts_on')->orderBy('user_id')->get();

        $rounds->each(function (NinetyDayRound $r) {
            $r->stats = Goals::dashboard([$r->user_id], ['round_id' => $r->id])['cards'];
        });
        $seesMany = $scope === null || count($scope) > 1;
        // Every closed round in scope, added together: the top-line picture before the per-person breakdown.
        $closed = Goals::dashboard($scope, ['round' => 'closed']);

        return view('tenant.performance.ninety-day-goals.past', [
            'tab'      => 'past',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany' => $seesMany,
            'rounds'   => $rounds,
            'cards'    => $closed['cards'],
            // Everyone's closed-round record side by side: who actually delivered, across every finished round.
            // Only people with at least one closed round appear — no point listing 58 people at 0/0.
            'leaderboard' => $seesMany ? $closed['users']->filter(fn ($u) => $u['goals'] > 0)->values() : null,
        ]);
    }

    /** The report for exactly one past round: its customers, week by week, and its comments. Nothing else mixed in. */
    public function pastRound($id)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }
        $round = NinetyDayRound::with('user.department')->findOrFail($id);
        if ($scope !== null && !in_array((int) $round->user_id, $scope, true)) {
            abort(403, 'That round is not in your team.');
        }

        $goals = NinetyDayGoal::with('checkins.notes.author')->where('round_id', $round->id)->orderBy('id')->get();
        $d = Goals::dashboard([$round->user_id], ['round_id' => $round->id]);

        return view('tenant.performance.ninety-day-goals.past-round', [
            'tab'      => 'past',
            'canSwitch' => Goals::isSalesStaff($user),
            'seesMany' => $scope === null || count($scope) > 1,
            'round'    => $round,
            'goals'    => $goals,
            'd'        => $d,
            'weeks'    => Goals::weeksOf($round),
            // Same reports as the live Dashboard, scoped to just this one round.
            'trialing'  => $d['rows']->where('status', 'Trial Order')->sortByDesc('potential')->values(),
            'attention' => $this->attentionRows($d['rows'], Carbon::today()),
        ]);
    }

    /** One salesperson's own page: their whole record, current round and every past one, in one place. */
    public function person($id)
    {
        [$viewer, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }
        $target = User::with('department')->findOrFail($id);
        if ($scope !== null && !in_array((int) $target->id, $scope, true)) {
            abort(403, 'That person is not in your team.');
        }

        $rounds = NinetyDayRound::where('user_id', $target->id)->orderByDesc('starts_on')->get();
        $rounds->each(function (NinetyDayRound $r) {
            $r->stats = Goals::dashboard([$r->user_id], ['round_id' => $r->id])['cards'];
        });
        $active = $rounds->firstWhere('status', 'active');

        return view('tenant.performance.ninety-day-goals.person', [
            'tab'      => null,
            'canSwitch' => Goals::isSalesStaff($viewer),
            'seesMany' => $scope === null || count($scope) > 1,
            'target'   => $target,
            'rounds'   => $rounds,
            'active'   => $active,
            // Every round this person has ever had, added together — their whole record, not just what's closed.
            'career'   => Goals::dashboard([$target->id], ['round' => 'all']),
        ]);
    }

    /** "90-Day Goals ▸ My Programs" always means the viewer's own current round — resolved here so
     *  the sidebar's static URL doesn't need to know which round that is. */
    public function myProgram()
    {
        $user = Auth::user();
        if (!Goals::isSalesStaff($user)) {
            return redirect()->route('performance.ninety-day-goals.program-dashboard')
                ->with('error', 'Only the Sales team has a program of their own to view.');
        }

        return redirect()->route('performance.ninety-day-goals.round', Goals::roundFor($user)->id);
    }

    /**
     * One round of one program, in full: the customers in it, each one's weekly status and
     * comments, and the round's own summary — the individual view behind "My Programs" for a
     * salesperson, or an admin drilling into a named participant from the Program Wise Summary.
     */
    public function roundDetail($id)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }
        $round = NinetyDayRound::with(['user.department', 'program'])->findOrFail($id);
        if ($scope !== null && !in_array((int) $round->user_id, $scope, true)) {
            abort(403, 'That round is not in your team.');
        }

        $goals = NinetyDayGoal::with('checkins.notes.author')->where('round_id', $round->id)
            ->whereNull('closed_at')->orderBy('id')->get();
        $d = Goals::dashboard([$round->user_id], ['round_id' => $round->id]);
        $weeks = Goals::weeksOf($round);
        $today = Carbon::today();

        // The KPI row's buckets, each goal counted exactly once, worst-first: dropped, then an
        // order already on the books, then at risk, then everything else still in progress.
        $dropped = $d['rows']->filter(fn ($r) => $r->status === 'Dropped')->pluck('goal.id');
        $stillOpen = $d['rows']->reject(fn ($r) => $dropped->contains($r->goal->id));
        $orders = $stillOpen->filter(fn ($r) => $r->achieved > 0)->pluck('goal.id');
        $notYetOrdered = $stillOpen->reject(fn ($r) => $orders->contains($r->goal->id));
        $atRisk = $this->attentionRows($notYetOrdered, $today)->pluck('goal.id');
        $counts = [
            'total'      => $d['rows']->count(),
            'orders'     => $orders->count(),
            'atRisk'     => $atRisk->count(),
            'dropped'    => $dropped->count(),
            'inProgress' => $d['rows']->count() - $orders->count() - $atRisk->count() - $dropped->count(),
        ];

        // Comments and status changes on this round only, newest first — for the Activity Log tab.
        $activity = Goals::comments([$round->user_id], ['round_id' => $round->id], 100);

        return view('tenant.performance.ninety-day-goals.round-detail', [
            'tab'      => null,
            'canSwitch' => Goals::isSalesStaff($user),
            'isAdmin'  => Goals::seesEveryone($user),
            'canEdit'  => Goals::seesEveryone($user) || (int) $round->user_id === (int) $user->id,
            'round'    => $round,
            'goals'    => $goals,
            'd'        => $d,
            'weeks'    => $weeks,
            'today'    => $today,
            'counts'   => $counts,
            'atRiskGoalIds' => $atRisk,
            'droppedGoalIds' => $dropped,
            'activity' => $activity,
            'statuses' => Goals::STATUSES,
            'brands'   => array_keys(Goals::BRANDS),
        ]);
    }

    /**
     * "Program Details": one admin-created program, every participant, every customer, in full —
     * reached from the "View" action on the main Dashboard's programs table. Everything here is
     * Goals::dashboard() filtered to this one program_id; nothing computed twice, two different ways.
     */
    public function programDetail(Request $request, $id)
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Program Details is for admin/HR.');
        }
        $program = NinetyDayProgram::with('creator')->withCount('rounds')->findOrFail($id);
        $today = Carbon::today();

        $f = [
            'program_id' => $program->id,
            'round'      => 'all',
            'user_id'    => $request->integer('user_id') ?: null,
            'customer'   => trim((string) $request->input('customer')) ?: null,
            'status'     => in_array($request->input('status'), Goals::STATUSES, true) ? $request->input('status') : null,
            'week'       => $request->input('week') !== null && $request->input('week') !== '' ? (int) $request->input('week') : null,
            'brand'      => $request->input('brand') ?: null,
        ];
        // Real participants: everyone with a round in this program, whether or not they have
        // added a customer yet — not just the people who show up in $rows. Passed as the scope
        // to dashboard() itself, not just used afterward — otherwise its own "every salesperson
        // in scope, including idle ones" fallback pulls in the whole company's sales team, not
        // just this program's, and Top Performing Salespersons shows people who were never in it.
        $participantIds = NinetyDayRound::where('program_id', $program->id)->pluck('user_id')->unique()->values();
        // Same reasoning for the salesperson filter dropdown: nobody outside this program has
        // anything to filter to, so the list only offers this program's own participants.
        $people = Goals::salesStaff()->whereIn('id', $participantIds);
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        $d = Goals::dashboard($participantIds->all(), $f);
        $rows = $this->adminViewRows($d, $f, $today);
        $weeks = $rows->isEmpty() ? Goals::WEEKS : $rows->max(fn ($r) => Goals::weeksOf($r->goal->round));
        $atRisk = $this->attentionRows($rows, $today);

        // Which week is "current" for this program specifically, clamped to its own real span —
        // the same math weekOf() uses for a round, applied to the program's own start date.
        $currentWeek = max(0, min($weeks - 1, intdiv($program->starts_on->diffInDays($today, false), 7)));
        $selectedWeek = $request->integer('w', $currentWeek);
        $selectedWeek = max(0, min($weeks - 1, $selectedWeek));
        $weekDue = $program->starts_on->copy()->addDays(7 * $selectedWeek);

        // The week strip: every week's done/expected, from the same per-goal checkin rows.
        $weekStrip = collect($d['strip'])->map(fn ($s, $w) => ['week' => $w, 'done' => $s['done'], 'total' => $s['total'],
            'pct' => $s['total'] > 0 ? round($s['done'] / $s['total'] * 100) : 0])->values();

        // Selected week's summary, per participant (not per customer): submitted every customer's
        // status for that week, has some still pending, or has none due yet.
        $weekByUser = [];
        foreach ($d['rows'] as $r) {
            $ck = $r->goal->checkins->firstWhere('week_no', $selectedWeek);
            if (!$ck) {
                continue;
            }
            $uid = $r->user->id;
            $weekByUser[$uid]['total'] = ($weekByUser[$uid]['total'] ?? 0) + 1;
            $weekByUser[$uid]['done'] = ($weekByUser[$uid]['done'] ?? 0) + ($ck->status !== null ? 1 : 0);
        }
        $weekSummary = ['submitted' => 0, 'pending' => 0, 'overdue' => 0, 'notStarted' => 0];
        foreach ($participantIds as $uid) {
            $wk = $weekByUser[$uid] ?? null;
            if (!$wk || $wk['total'] === 0) {
                $weekSummary['notStarted']++;
            } elseif ($wk['done'] >= $wk['total']) {
                $weekSummary['submitted']++;
            } elseif ($weekDue->lt($today)) {
                $weekSummary['overdue']++;
            } else {
                $weekSummary['pending']++;
            }
        }

        // Recent activity: every comment and status change from this program's own participants.
        $activity = Goals::comments($participantIds->all(), [], 20);

        return view('tenant.performance.ninety-day-goals.program-detail', [
            'tab'        => null,
            'isAdmin'    => true,
            'program'    => $program,
            'filters'    => $f,
            'people'     => $people,
            'brands'     => array_merge(array_keys(Goals::BRANDS), ['Other']),
            'weeks'      => $weeks,
            'statuses'   => Goals::STATUSES,
            'today'      => $today,
            'cards'      => $d['cards'],
            'statusCounts' => $d['statuses'],
            'atRisk'     => $atRisk,
            'participants' => $participantIds->count(),
            'currentWeek' => $currentWeek,
            'selectedWeek' => $selectedWeek,
            'weekDue'    => $weekDue,
            'weekStrip'  => $weekStrip,
            'weekSummary' => $weekSummary,
            'rows'       => $rows,
            'salespeople' => $d['users'],
            'activity'   => $activity,
        ]);
    }

    // ── admin-created programs ───────────────────────────────────────────

    /** The form: name, dates, and every salesperson to invite (all ticked, deselect to exclude). */
    public function programCreate()
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Only admin/HR can start a new 90-Day Goals program.');
        }

        return view('tenant.performance.ninety-day-goals.program-create', [
            'tab'    => 'goals',
            'canSwitch' => false,
            'seesMany'  => true,
            'people' => Goals::salesStaff(),
            'roundSpanDays' => Goals::ROUND_SPAN_DAYS,
        ]);
    }

    /** Creates the program, enrolls whoever isn't already mid-round, and notifies each of them. */
    public function programStore(Request $request)
    {
        $admin = Auth::user();
        if (!Goals::seesEveryone($admin)) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can start a new 90-Day Goals program.'], 403);
        }
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'starts_on'     => 'required|date',
            'ends_on'       => 'required|date|after:starts_on',
            'user_ids'      => 'required|array|min:1',
            'user_ids.*'    => 'integer|exists:users,id',
        ]);

        $result = Goals::createProgram($admin, $data['name'], Carbon::parse($data['starts_on']), Carbon::parse($data['ends_on']), $data['user_ids']);

        // No email: Goals::createProgram() already gives everyone enrolled a "add your target
        // customers" task in the existing Task Management module (due 3 days after the start
        // date), which is the notification — a real send through this app's local Gmail SMTP
        // is both slow and unwanted here. The in-app ('database') entry is a fast local insert,
        // sent right away, same deferred one-try/catch-per-person pattern as bulk task
        // assignment so one bad row never stops the rest.
        $programId = $result['program']->id;
        $enrolledRoundIds = array_map(fn ($e) => $e['round']->id, $result['enrolled']);
        app()->terminating(function () use ($programId, $enrolledRoundIds) {
            $program = NinetyDayProgram::find($programId);
            if (!$program || !$enrolledRoundIds) {
                return;
            }
            foreach (NinetyDayRound::whereIn('id', $enrolledRoundIds)->with('user')->get() as $round) {
                try {
                    $round->user?->notify(new ProgramInvite($program, $round));
                } catch (\Throwable $e) {
                    Log::warning("90-Day Goals program invite failed for user {$round->user_id}: " . $e->getMessage());
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => count($result['enrolled']) . ' salesperson(s) added to "' . $result['program']->name . '".'
                . ($result['skipped'] ? ' ' . count($result['skipped']) . ' already had an active round and were skipped: '
                    . collect($result['skipped'])->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->implode(', ') . '.' : ''),
            'program_id' => $result['program']->id,
            'enrolled'   => count($result['enrolled']),
            'skipped'    => count($result['skipped']),
        ]);
    }

    // ── the customer picker ──────────────────────────────────────────────

    /** The person's own pipeline customers that are not already in their round. */
    public function pipelineCustomers(Request $request)
    {
        $user = Auth::user();
        $target = $this->targetUser($request, $user);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        $round = Goals::activeRound($target);
        $taken = $round ? NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->pluck('sales_pipeline_id')->filter()->all() : [];

        return response()->json([
            'success'   => true,
            'customers' => Goals::pipelineCustomers($target)->reject(fn ($c) => in_array($c['id'], $taken, true))->values(),
            'brands'    => array_keys(Goals::BRANDS),
        ]);
    }

    /** Add customers to the round. */
    public function store(Request $request)
    {
        $user = Auth::user();
        $target = $this->targetUser($request, $user);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }

        $data = $request->validate([
            'customers'                     => 'required|array|min:1|max:200',
            'customers.*.sales_pipeline_id' => 'required|integer',
            'customers.*.product'           => 'nullable|string|max:255',
            'customers.*.brand'             => 'nullable|string|max:100',
            'customers.*.goal'              => 'required|string|max:255',
            'customers.*.monthly_potential' => 'required|numeric|min:0|max:100000',
        ]);

        $round = Goals::roundFor($target);
        $mine = Goals::pipelineCustomers($target)->keyBy('id');
        $have = NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->pluck('sales_pipeline_id')->filter()->all();

        $added = 0;
        $skipped = [];
        foreach ($data['customers'] as $row) {
            $pid = (int) $row['sales_pipeline_id'];
            $c = $mine->get($pid);
            if (!$c) {
                $skipped[] = "customer #{$pid} is not in this person's pipeline";
                continue;
            }
            if (in_array($pid, $have, true)) {
                $skipped[] = $c['customer'] . ' is already in this round';
                continue;
            }
            NinetyDayGoal::create([
                'user_id'           => $target->id,
                'round_id'          => $round->id,
                'sales_pipeline_id' => $pid,
                'client_name'       => $c['customer'],
                'product'           => $row['product'] ?? $c['product'],
                'brand'             => $row['brand'] ?? $c['brand'],
                'goal'              => $row['goal'],
                'goal_type'         => 'Order',
                'monthly_potential' => (float) $row['monthly_potential'],
                'target_value'      => 0,
                'is_active'         => true,
                'created_by_id'     => $user->id,
                'tenant_id'         => $target->tenant_id,
            ]);
            $have[] = $pid;
            $added++;
        }
        Goals::ensureCheckins($round);

        return response()->json(['success' => true, 'added' => $added, 'skipped' => $skipped]);
    }

    // ── the weekly status ────────────────────────────────────────────────

    /** Submits new statuses, and edits ones already on record — the owner, or admin/HR on their behalf. */
    public function checkin(Request $request)
    {
        $actor = Auth::user();
        $target = $this->targetUser($request, $actor);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Only the Sales team submits weekly statuses.'], 403);
        }
        // Just a sanity bound — Goals::submit() itself is the real, round-aware check. A round
        // with no active round yet falls back to the default 13-week span for this bound only.
        $maxWeek = (($round = Goals::activeRound($target)) ? Goals::weeksOf($round) : Goals::WEEKS) - 1;
        $data = $request->validate([
            'week_no'             => 'required|integer|min:0|max:' . $maxWeek,
            'entries'             => 'required|array|min:1',
            'entries.*.goal_id'   => 'required|integer',
            'entries.*.status'    => 'nullable|string|max:40',
            'entries.*.comment'   => 'nullable|string|max:1000',
        ]);

        $entries = [];
        foreach ($data['entries'] as $e) {
            $entries[(int) $e['goal_id']] = ['status' => $e['status'] ?? '', 'comment' => $e['comment'] ?? ''];
        }
        [$saved, $errors] = Goals::submit($target, (int) $data['week_no'], $entries, null, $actor);

        return response()->json(['success' => $saved > 0 && !$errors, 'saved' => $saved, 'errors' => $errors,
            'message' => $errors ? implode(' ', $errors) : "Saved {$saved} status" . ($saved === 1 ? '' : 'es') . '.'], $errors && !$saved ? 422 : 200);
    }

    // ── change / remove a goal ───────────────────────────────────────────

    public function update(Request $request, $id)
    {
        $goal = NinetyDayGoal::findOrFail($id);
        if (!$this->mayChange($goal)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        $data = $request->validate([
            'product'           => 'nullable|string|max:255',
            'brand'             => 'nullable|string|max:100',
            'goal'              => 'nullable|string|max:255',
            'monthly_potential' => 'nullable|numeric|min:0|max:100000',
            'expected_order_date' => 'nullable|date',
        ]);
        $goal->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Goal updated.']);
    }

    public function destroy($id)
    {
        // Once a customer is added, the sales team cannot remove it: the history stays on record.
        // Only admin/HR can delete, and the check-ins and comments go with it.
        if (!Goals::seesEveryone(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Once a customer is added it stays on record. Ask an admin if one has to be removed.'], 403);
        }
        $goal = NinetyDayGoal::findOrFail($id);
        $goal->checkins()->each(fn ($c) => $c->notes()->delete());
        $goal->checkins()->delete();
        $goal->delete();

        return response()->json(['success' => true, 'message' => 'Customer removed.']);
    }

    // ── comments on a week ───────────────────────────────────────────────

    /** The salesperson or an admin/manager adds a comment to one week of a goal. */
    public function note(Request $request)
    {
        // Just a sanity bound (the goal, and so its round, isn't resolved until after
        // validation) — Goals::addNote() is the real check: it needs an actual check-in row
        // for that goal/week, which only exists for weeks that round has really reached.
        $data = $request->validate([
            'goal_id' => 'required|integer',
            'week_no' => 'required|integer|min:0|max:51',
            'body'    => 'required|string|max:1000',
        ]);
        $user = Auth::user();
        $goal = NinetyDayGoal::find($data['goal_id']);
        if (!$goal || !Goals::canNote($user, $goal)) {
            return response()->json(['success' => false, 'message' => 'You cannot comment on this goal.'], 403);
        }
        $note = Goals::addNote($user, $goal, (int) $data['week_no'], $data['body']);
        if (!$note) {
            return response()->json(['success' => false, 'message' => 'That week has not started yet.'], 422);
        }

        return response()->json(['success' => true, 'note' => [
            'id' => $note->id, 'by' => trim($user->first_name . ' ' . $user->last_name), 'body' => $note->body,
            'at' => $note->created_at->format('d M'), 'can_delete' => Goals::seesEveryone($user),
        ]]);
    }

    // ── upsell confirmation ──────────────────────────────────────────────

    /**
     * A repeat month's Sales Report sale is flagged, never assumed — the goal's own owner, or
     * admin/HR/manager on their behalf, says yes (it's an upsell, sets the status) or no (just
     * clears the flag, leaves the week to be filled in by hand as usual).
     */
    public function confirmUpsell(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'checkin_id' => 'required|integer',
            'is_upsell'  => 'required|boolean',
        ]);
        $checkin = NinetyDayGoalCheckin::with('goal')->find($data['checkin_id']);
        if (!$checkin || !$checkin->goal || !Goals::canNote($user, $checkin->goal)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        if (!$checkin->needs_upsell_confirm) {
            return response()->json(['success' => false, 'message' => 'Nothing pending confirmation here.'], 422);
        }
        Goals::confirmUpsell($checkin, $user, (bool) $data['is_upsell']);

        return response()->json(['success' => true, 'is_upsell' => (bool) $data['is_upsell']]);
    }

    /** Only admin/HR remove a comment. */
    public function destroyNote($id)
    {
        if (!Goals::seesEveryone(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Comments stay on record.'], 403);
        }
        NinetyDayGoalNote::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    // ── who may act on whose goals ───────────────────────────────────────

    /** Sales staff act for themselves; admin/HR (goals.manage) may add for a named person. */
    private function targetUser(Request $request, User $user): ?User
    {
        if ($request->filled('user_id') && (int) $request->input('user_id') !== $user->id) {
            if (!Goals::seesEveryone($user)) {
                return null;
            }
            $other = User::notTerminated()->find((int) $request->input('user_id'));

            return $other && Goals::isSalesStaff($other) ? $other : null;
        }

        return Goals::isSalesStaff($user) ? $user : null;
    }

    private function mayChange(NinetyDayGoal $goal): bool
    {
        $user = Auth::user();

        return Goals::seesEveryone($user) || (int) $goal->user_id === (int) $user->id;
    }
}
