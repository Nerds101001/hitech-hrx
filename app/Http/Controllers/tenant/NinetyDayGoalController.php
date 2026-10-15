<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Helpers\NotificationHelper;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayGoalDeleteRequest;
use App\Models\NinetyDayGoalNote;
use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NinetyDayGoal\NinetyDayGoalDeleteDecision;
use App\Notifications\NinetyDayGoal\NinetyDayGoalDeleteRequested;
use App\Notifications\NinetyDayGoal\NinetyDayGoalNoteAdded;
use App\Notifications\NinetyDayGoal\ProgramInvite;
use App\Notifications\NinetyDayGoal\WeekReminder;
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
        // An Export person added to a program owns their own round too, so they land on it
        // rather than the admin dashboard they cannot open.
        $ownsProgram = $isSales || Goals::isParticipant($user);
        $scope    = Goals::visibleUserIds($user);
        $seesMany = $scope === null || count($scope) > 1;

        // The original Goals page is retired (2026-10-01): it ran beside the newer pages with
        // its own tab bar, and admin and salespeople ended up between two designs. Every old
        // link (tasks, notifications, bookmarks) now lands on the page that replaced it.
        if ($ownsProgram && ($request->input('view') === 'mine' || !$seesMany)) {
            return redirect()->route('performance.ninety-day-goals.my-program');
        }

        return redirect()->route('performance.ninety-day-goals.program-dashboard');
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
     * Retired: the old 90-Day Dashboard (the first design). Its sections live on in the current
     * pages - admin/HR and team managers get the Program Dashboard (every section, scoped to the
     * manager's team), a salesperson gets My Dashboard. Old links and bookmarks land there.
     */
    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $scope = Goals::visibleUserIds($user);
        if (Goals::seesEveryone($user) || count($scope ?? []) > 1) {
            return redirect()->route('performance.ninety-day-goals.program-dashboard', $request->query());
        }

        return (Goals::isSalesStaff($user) || Goals::isParticipant($user))
            ? redirect()->route('performance.ninety-day-goals.my-dashboard')
            : redirect()->route('tenant.dashboard');
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
            // One specific round (the round page's own export) - takes precedence over 'round'.
            'round_id'   => $request->integer('round_id') ?: null,
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
    /** $scope: null = everyone (admin/HR); a manager's team ids otherwise. */
    private function buildAdminOverviewData(Request $request, string $defaultRound = 'current', ?array $scope = null): array
    {
        $today = Carbon::today();
        $f = $this->adminViewFilters($request, $defaultRound);

        $programs = NinetyDayProgram::withCount('rounds')
            ->when($scope !== null, fn ($q) => $q->whereHas('rounds', fn ($r) => $r->whereIn('user_id', $scope)))
            ->orderByDesc('id')->get();
        if ($f['program_id'] && !$programs->contains('id', $f['program_id'])) {
            $f['program_id'] = null;
        }
        $people = Goals::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))->values();
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        $d = Goals::dashboard($scope, $f);
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
            'week' => 'W' . ($w + 1), 'submitted' => $s['done'], 'pending' => max(0, $s['total'] - $s['done']),
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
        $perPage = Goals::perPage($request->input('per_page'), 25, $rows->count());
        $page = max(1, $request->integer('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return [
            'filters'    => $f,
            // The one program the "Select Program" pills narrowed to, or null for "All Programs" —
            // lets the view swap the all-programs list for that program's own customer tracking.
            'selectedProgram' => $f['program_id'] ? $programs->firstWhere('id', $f['program_id']) : null,
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
            // Customers whose latest status is Trial Order (from the retired old Dashboard's watchlist).
            'trialing'   => $d['rows']->where('status', 'Trial Order')->sortByDesc('potential')->values(),
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
     * Retired: the old "Admin View" page. The Program Dashboard has every one of its sections
     * (Program Overview, Salesperson Performance, Customer Tracking, Weekly Submissions, Orders &
     * Results, At Risk, Reports) in the current design. Old links and bookmarks land there.
     */
    public function adminView(Request $request)
    {
        return redirect()->route('performance.ninety-day-goals.program-dashboard', $request->query());
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
        // The one 90-Day dashboard: admin/HR see everyone; a manager sees their own team (this
        // replaced the old /dashboard page, which was the only place a manager had team figures).
        $isAdmin = Goals::seesEveryone($user);
        $scope = $isAdmin ? null : (Goals::canOpenPage($user) ? Goals::visibleUserIds($user) : []);
        if (!$isAdmin && count($scope ?? []) <= 1) {
            return Goals::isSalesStaff($user)
                ? redirect()->route('performance.ninety-day-goals.my-dashboard')
                : redirect()->route('tenant.dashboard')->with('error', 'This page is for admin/HR and team managers.');
        }
        $data = $this->buildAdminOverviewData($request, 'all', $scope);

        // Real trend deltas only — "new in the last 30 days", never a fabricated percentage.
        // Left blank (shown as "—") wherever there is no honest way to compute one.
        $since = Carbon::today()->subDays(30);
        $teamPipelineIds = $scope === null ? null
            : NinetyDayGoal::whereIn('user_id', $scope)->whereNotNull('sales_pipeline_id')->pluck('sales_pipeline_id')->unique()->all();
        $data['trends'] = [
            'activePrograms' => NinetyDayProgram::where('created_at', '>=', $since)
                ->when($scope !== null, fn ($q) => $q->whereHas('rounds', fn ($r) => $r->whereIn('user_id', $scope)))->count(),
            'participants'   => NinetyDayRound::where('created_at', '>=', $since)
                ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))->distinct('user_id')->count('user_id'),
            'customers'      => NinetyDayGoal::where('created_at', '>=', $since)
                ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))->count(),
            'ordersThisMonth' => DB::table('sales_pipeline_months')->where('month_year', Carbon::today()->format('Y-m'))->where('sale_amount', '>', 0)
                ->when($teamPipelineIds !== null, fn ($q) => $q->whereIn('sales_pipeline_id', $teamPipelineIds ?: [0]))->count(),
        ];

        return view('tenant.performance.ninety-day-goals.program-dashboard', $data + ['tab' => 'admin', 'isAdmin' => $isAdmin, 'canManage' => Goals::managesEveryone($user)]);
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
                array_map(fn ($w) => 'W' . ($w + 1), range(0, $weeksSpan - 1)),
                ['Actual (₹L)', 'Achievement %', 'Current Status']
            ));
            // Customer/product are typed by salespeople: a cell starting with
            // = + - @ would run as a formula in Excel, so it is forced to text.
            $text = fn ($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
            foreach ($rows as $r) {
                $weekCells = [];
                for ($w = 0; $w < $weeksSpan; $w++) {
                    $ck = $r->goal->checkins->firstWhere('week_no', $w);
                    $weekCells[] = $ck && $ck->status ? $ck->status : '';
                }
                fputcsv($out, array_map($text, array_merge([
                    optional($r->program)->name ?? 'No program',
                    trim(optional($r->user)->first_name . ' ' . optional($r->user)->last_name),
                    $r->goal->client_name, $r->goal->product, $r->brand, $r->potential,
                ], $weekCells, [$r->achieved, $r->pct, $r->status])));
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
        // A salesperson's finished rounds are listed under My Programs (2026-10-01). Admin/HR and
        // team leads keep this page - its closed-round record by salesperson is not shown
        // anywhere else - reached from the Programs list.
        if (Goals::isSalesStaff($user) && $scope !== null && count($scope) <= 1) {
            return redirect()->route('performance.ninety-day-goals.my-program');
        }

        $rounds = NinetyDayRound::with('user.department')
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->where('status', 'closed')
            ->orderByDesc('starts_on')->orderBy('user_id')->get();

        $seesMany = $scope === null || count($scope) > 1;
        // Every closed round in scope, added together: the top-line picture before the per-person breakdown.
        $closed = Goals::dashboard($scope, ['round' => 'closed']);
        // Per-round figures from that same pass (was one full dashboard() per round - thousands
        // of queries for an admin once there are many closed rounds).
        $this->attachRoundStats($rounds, $closed['rows']);

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
        // Every round this person has ever had, added together — their whole record, not just what's closed.
        $career = Goals::dashboard([$target->id], ['round' => 'all']);
        $this->attachRoundStats($rounds, $career['rows']);
        $active = $rounds->firstWhere('status', 'active');

        return view('tenant.performance.ninety-day-goals.person', [
            'tab'      => null,
            'canSwitch' => Goals::isSalesStaff($viewer),
            'seesMany' => $scope === null || count($scope) > 1,
            'target'   => $target,
            'rounds'   => $rounds,
            'active'   => $active,
            'career'   => $career,
        ]);
    }

    /**
     * $round->stats (goals, potential, target, achieved, pct, converted) for each round, from
     * dashboard() rows already loaded - the same figures dashboard()'s own cards give, without
     * running it once per round.
     */
    private function attachRoundStats($rounds, $rows): void
    {
        $byRound = collect($rows)->groupBy(fn ($r) => $r->goal->round_id);
        foreach ($rounds as $r) {
            $c = $byRound->get($r->id, collect());
            $target = (float) $c->sum('target');
            $r->stats = [
                'goals'     => $c->count(),
                'potential' => round((float) $c->sum('potential'), 2),
                'target'    => round($target, 2),
                'achieved'  => round((float) $c->sum('achieved'), 2),
                'pct'       => $target > 0 ? round($c->sum('achieved') / $target * 100, 1) : null,
                'converted' => $c->filter(fn ($x) => in_array($x->status, Goals::ORDER_STATUSES, true) && $x->achieved > 0)->count(),
            ];
        }
    }

    /**
     * "My Dashboard": a salesperson's own self-service equivalent of the admin's
     * programDashboard() — same Goals::dashboard() underneath, scoped to just this one person
     * across every program/round they've ever had (or the one picked from "Select Program"),
     * instead of the whole company.
     */
    public function myDashboard(Request $request)
    {
        $user = Auth::user();
        if (!Goals::isSalesStaff($user) && !Goals::isParticipant($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'The 90-Day Goals tracker is for the Sales team.');
        }
        $today = Carbon::today();

        // Every round this person has ever had, newest first — the "Select Program" picker's real
        // source. Not every round has an admin-created program behind it: a self-started round
        // doesn't, so picking off $r->program alone left someone with only self-started rounds
        // seeing "0 programs" despite having real customers and real goals. The picker works off
        // rounds themselves — always real, always at least one — and just shows the program's
        // name where one exists.
        $myRounds = NinetyDayRound::with('program')
            ->withCount(['goals' => fn ($q) => $q->whereNull('closed_at')])
            ->where('user_id', $user->id)->orderByDesc('starts_on')->get();

        $roundId = $request->integer('round_id') ?: null;
        if ($roundId && !$myRounds->contains('id', $roundId)) {
            $roundId = null;
        }
        $f = ['round' => 'all'];
        if ($roundId) {
            $f['round_id'] = $roundId;
        }
        $d = Goals::dashboard([$user->id], $f);

        // The one round that drives the weekly charts and strip — the selected round, or this
        // person's own current/most recent round for "All Programs". A chart needs one real week
        // axis; this is the same "which round is live" roundFor() already uses.
        $focusRound = $roundId
            ? $myRounds->firstWhere('id', $roundId)
            : ($myRounds->first(fn ($r) => $r->status === 'active') ?? $myRounds->first());

        // Rounds an admin enrolled this person in (or they started themselves) that still have
        // zero customers — the dashboard's own visible equivalent of the "add your target
        // customers" task this module already assigns, so a freshly-invited person sees it right
        // here instead of having to go find the task or the program on their own.
        $needsEnrollment = $myRounds->filter(fn ($r) => $r->status === 'active' && $r->goals_count === 0)->values();

        // Every active round that is still short of the minimum, with how long is left to fix
        // it. Drives the banner: zero customers is not the only state worth warning about -
        // stopping at four is just as much of a round that will not be counted.
        $windowAlerts = $myRounds
            ->filter(fn ($r) => $r->status === 'active')
            ->map(fn ($r) => ['round' => $r] + Goals::windowState($r))
            ->reject(fn ($w) => $w['meets_min'] && $w['open'] === false)
            ->reject(fn ($w) => $w['meets_min'])
            ->values();

        $weeklyProgress = $focusRound ? $this->myWeeklyProgress($user, $focusRound) : ['labels' => [], 'target' => [], 'achieved' => []];
        $weekStrip = collect();
        $currentWeek = 0;
        if ($focusRound) {
            $currentWeek = Goals::weekOf($focusRound, $today);
            $fStrip = Goals::dashboard([$user->id], ['round_id' => $focusRound->id])['strip'];
            // The last 5 weeks up to and including the current one — same shape as every other
            // weekly strip in this module, just windowed to fit a small card.
            $weekStrip = collect($fStrip)->map(fn ($s, $w) => ['week' => $w, 'done' => $s['done'], 'pending' => max(0, $s['total'] - $s['done']), 'total' => $s['total']])
                ->slice(max(0, $currentWeek - 4), 5)->values();
        }

        // Same paginated customer table pattern the admin pages use, just this person's own rows.
        $rows = $d['rows'];
        $perPage = Goals::perPage($request->input('per_page'), 10, $rows->count());
        $page = max(1, $request->integer('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        // Open tasks this module already assigns on every Monday, same query the admin
        // dashboard's Tasks & Alerts runs, scoped to just this person.
        $pendingTasks = Task::where('user_id', $user->id)->where('title', 'like', '90-Day Goals:%')
            ->whereNotIn('status', Task::CLOSED_STATUSES)->orderBy('due_date')->limit(6)->get();

        $activity = Goals::comments([$user->id], [], 8);

        // Real trend deltas only — "new in the last 30 days" — same honest-or-blank rule the
        // admin dashboard's KPI cards already follow, just scoped to this one person.
        $since = $today->copy()->subDays(30);
        $myPipelineIds = $rows->pluck('goal.sales_pipeline_id')->filter()->unique()->values()->all();
        $trends = [
            'customers' => NinetyDayGoal::where('user_id', $user->id)->where('created_at', '>=', $since)->count(),
            'ordersThisMonth' => $myPipelineIds
                ? DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $myPipelineIds)
                    ->where('month_year', $today->format('Y-m'))->where('sale_amount', '>', 0)->count()
                : 0,
        ];

        // Team performance, summary only: target/achieved/% for the rest of this person's own
        // department, current round — reuses dashboard()'s own per-user rollup, never exposes a
        // teammate's customer names (the page itself says so, same restriction as the reference).
        $peerIds = Goals::salesStaff()->where('department_id', $user->department_id)
            ->pluck('id')->reject(fn ($id) => (int) $id === (int) $user->id)->values()->all();
        $team = $peerIds ? Goals::dashboard($peerIds, ['round' => 'current'])['users']->take(5)->values() : collect();

        return view('tenant.performance.ninety-day-goals.my-dashboard', [
            'tab'        => null,
            'today'      => $today,
            'myRounds'   => $myRounds,
            'selectedRoundId' => $roundId,
            'selectedRound'   => $roundId ? $myRounds->firstWhere('id', $roundId) : null,
            'needsEnrollment' => $needsEnrollment,
            'windowAlerts'    => $windowAlerts,
            'cards'      => $d['cards'],
            'statusCounts' => $d['statuses'],
            'focusRound' => $focusRound,
            'weeklyProgress' => $weeklyProgress,
            'weekStrip'  => $weekStrip,
            'currentWeek' => $currentWeek,
            'rows'       => $paged,
            'allRowsCount' => $rows->count(),
            'pendingTasks' => $pendingTasks,
            'activity'   => $activity,
            'team'       => $team,
            'statuses'   => Goals::STATUSES,
            'trends'     => $trends,
        ]);
    }

    /**
     * Week-by-week cumulative target vs. real achieved for the one round driving "My Progress" —
     * target accrues evenly across the round's own weeks (the same flat pacing every KPI card
     * already assumes); achieved is real Sales Report money, summed month by month up to each
     * week's due date, exactly the same lookup dashboard() itself runs for a goal's own achieved.
     */
    private function myWeeklyProgress(User $user, NinetyDayRound $round): array
    {
        $goals = NinetyDayGoal::where('round_id', $round->id)->where('user_id', $user->id)->get();
        // Same rule as Goals::dashboard(): the months whose 16th is inside the round, once each.
        $roundMonths = Goals::roundMonths($round);
        $totalTarget = (float) $goals->sum(fn ($g) => (float) $g->monthly_potential);   // overall target per customer
        $ids = $goals->pluck('sales_pipeline_id')->filter()->unique()->values()->all();

        $monthlySales = [];
        if ($ids) {
            foreach (DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $ids)
                ->selectRaw('month_year, SUM(sale_amount) s')->groupBy('month_year')->get() as $r) {
                $monthlySales[$r->month_year] = (float) $r->s;
            }
        }

        $weeks = Goals::weeksOf($round);
        $targetPerWeek = $weeks > 0 ? $totalTarget / $weeks : 0.0;
        $labels = [];
        $target = [];
        $achieved = [];
        foreach (range(0, $weeks - 1) as $w) {
            $due = Goals::dueOn($round, $w);
            $labels[] = 'W' . ($w + 1) . ' ' . $due->format('d M');
            $target[] = round($targetPerWeek * ($w + 1), 2);
            $cum = 0.0;
            foreach ($roundMonths as $ym) {
                if (Carbon::createFromFormat('Y-m-d', $ym . '-01')->gt($due)) {
                    break;
                }
                $cum += $monthlySales[$ym] ?? 0.0;
            }
            $achieved[] = round($cum, 2);
        }

        return ['labels' => $labels, 'target' => $target, 'achieved' => $achieved];
    }

    /** "90-Day Goals ▸ My Programs" always means the viewer's own current round — resolved here so
     *  the sidebar's static URL doesn't need to know which round that is. */
    public function myProgram()
    {
        $user = Auth::user();
        $isSales = Goals::isSalesStaff($user);
        if (!$isSales && !Goals::isParticipant($user)) {
            return redirect()->route('performance.ninety-day-goals.program-dashboard')
                ->with('error', 'Only the Sales team has a program of their own to view.');
        }

        // Sales self-start a round on visit; an Export person added to a program only ever views
        // the round an admin already gave them -- never auto-starts one.
        $current = $isSales ? Goals::roundFor($user) : Goals::activeRound($user);
        $finished = NinetyDayRound::with('program')->where('user_id', $user->id)->where('status', 'closed')->orderByDesc('starts_on')->get();

        // No active round (an Export participant between rounds): drop them straight onto their most
        // recent round rather than the summary page, which assumes a current one.
        if (!$current) {
            $recent = NinetyDayRound::where('user_id', $user->id)->orderByDesc('starts_on')->first();
            return $recent
                ? redirect()->route('performance.ninety-day-goals.round', $recent->id)
                : redirect()->route('tenant.dashboard')->with('error', 'No 90-Day program yet.');
        }

        // Nothing finished yet: straight into the one program they have.
        if ($finished->isEmpty()) {
            return redirect()->route('performance.ninety-day-goals.round', $current->id);
        }

        return view('tenant.performance.ninety-day-goals.my-programs', [
            'current'  => $current->load('program'),
            'finished' => $finished,
            'stats'    => $finished->mapWithKeys(fn ($r) => [$r->id => [
                'customers' => NinetyDayGoal::where('round_id', $r->id)->count(),
            ]]),
        ]);
    }

    /** Every program, with Open / Edit / Delete - the admin's one place to find and change them. */
    public function programs(Request $request)
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            return redirect()->route('performance.ninety-day-goals.program-dashboard');
        }
        $today = Carbon::today();
        $status = in_array($request->input('status'), ['active', 'upcoming', 'ended'], true) ? $request->input('status') : 'all';

        $all = NinetyDayProgram::with('creator')->orderByDesc('starts_on')->orderByDesc('id')->get();
        $stateOf = fn ($p) => $today->lt($p->starts_on) ? 'upcoming' : ($today->gt($p->ends_on) ? 'ended' : 'active');
        $counts = ['all' => $all->count(), 'active' => 0, 'upcoming' => 0, 'ended' => 0];
        foreach ($all as $p) {
            $counts[$stateOf($p)]++;
        }
        $programs = $status === 'all' ? $all : $all->filter(fn ($p) => $stateOf($p) === $status)->values();

        $ids = $programs->pluck('id');
        $roundIds = NinetyDayRound::whereIn('program_id', $ids)->get(['id', 'program_id'])->groupBy('program_id');
        $goalCounts = NinetyDayGoal::whereIn('round_id', NinetyDayRound::whereIn('program_id', $ids)->pluck('id'))
            ->selectRaw('round_id, count(*) n')->groupBy('round_id')->pluck('n', 'round_id');

        return view('tenant.performance.ninety-day-goals.programs', [
            'programs' => $programs,
            'status'   => $status,
            'counts'   => $counts,
            'today'    => $today,
            'stateOf'  => $stateOf,
            'participants' => $roundIds->map->count(),
            'customers' => $roundIds->map(fn ($rs) => $rs->sum(fn ($r) => $goalCounts[$r->id] ?? 0)),
            'canManage' => Goals::managesEveryone($user),
            'canDelete' => $user->hasRole(['admin', 'Admin', 'super_admin']),
        ]);
    }

    /**
     * One round of one program, in full: the customers in it, each one's weekly status and
     * comments, and the round's own summary — the individual view behind "My Programs" for a
     * salesperson, or an admin drilling into a named participant from the Program Wise Summary.
     */
    public function roundDetail(Request $request, $id)
    {
        [$user, $scope, $redirect] = $this->scopeOrRedirect();
        if ($redirect) {
            return $redirect;
        }
        $round = NinetyDayRound::with(['user.department', 'program'])->findOrFail($id);
        if ($scope !== null && !in_array((int) $round->user_id, $scope, true)) {
            abort(403, 'That round is not in your team.');
        }

        $goals = NinetyDayGoal::with('checkins.notes.author', 'pendingDeleteRequest.requestedBy')->where('round_id', $round->id)
            ->whereNull('closed_at')->orderBy('id')->get();
        $d = Goals::dashboard([$round->user_id], ['round_id' => $round->id]);
        $weeks = Goals::weeksOf($round);
        $today = Carbon::today();

        // The week picker: every week's real Monday date, which week is genuinely "now" for this
        // round, and which one the picker has open — same pattern the admin Program Details page
        // already uses, just scoped to this one person's one round.
        $dates = Goals::weekDates($round);
        $currentWeek = Goals::weekOf($round, $today);
        // Never a future week — this is a real submission form, not just a viewer, and
        // Goals::submit() already refuses to save a status for a week that isn't due yet. Capping
        // the picker itself to $currentWeek means the whole page (fields, Save, the week strip)
        // simply never opens on data that doesn't exist yet, instead of opening it disabled/dead
        // or letting someone fill it in only to have the save silently rejected.
        $selectedWeek = max(0, min($currentWeek, $request->integer('w', $currentWeek)));
        $weekDue = $dates[$selectedWeek];
        // $d['strip'] can run longer than this round's own real span — dashboard() falls back to
        // the default 13-week span when a round has no customers yet to derive a length from —
        // so it's capped to $weeks here, never indexed past this round's own real dates.
        $weekStrip = collect($d['strip'])->take($weeks)->map(fn ($s, $w) => ['week' => $w, 'done' => $s['done'], 'total' => $s['total'],
            'pct' => $s['total'] > 0 ? round($s['done'] / $s['total'] * 100) : 0])->values();

        // Selected week's summary, per customer: submitted, still pending, overdue (Monday
        // already passed), or nothing due yet for a goal added after that week started.
        $weekSummary = ['submitted' => 0, 'pending' => 0, 'overdue' => 0, 'notStarted' => 0];
        foreach ($goals as $g) {
            $ck = $g->checkins->firstWhere('week_no', $selectedWeek);
            if (!$ck) {
                $weekSummary['notStarted']++;
            } elseif ($ck->status !== null && $ck->status !== '') {
                $weekSummary['submitted']++;
            } elseif ($weekDue->lt($today)) {
                $weekSummary['overdue']++;
            } else {
                $weekSummary['pending']++;
            }
        }

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
            'canEdit'  => Goals::managesEveryone($user) || (int) $round->user_id === (int) $user->id,
            // Orders the Sales Report shows a second time for a customer: confirm upsell or not
            // (moved here from the retired Goals page, 2026-10-01).
            // Comments on a customer's week: who may add one (owner, their lead, admin/HR) and
            // who may remove one (admin/HR) - moved here from the retired Goals page, 2026-10-01.
            'canComment' => $goals->isNotEmpty() && Goals::canNote($user, $goals->first()),
            'canRemoveComment' => Goals::managesEveryone($user),
            // The customer rules, shown on the page (2026-10-01): minimum count, the add window
            // and its countdown, and which targets are already fixed by a reported status.
            'window'     => Goals::windowState($round),
            'managesAll' => Goals::managesEveryone($user),
            'lockedTargetIds' => NinetyDayGoalCheckin::whereIn('goal_id', $goals->pluck('id'))->whereNotNull('status')
                ->distinct()->pluck('goal_id')->map(fn ($id) => (int) $id)->all(),
            'pendingUpsells' => NinetyDayGoalCheckin::with('goal')->where('round_id', $round->id)
                ->where('needs_upsell_confirm', true)->orderBy('week_no')->get(),
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
            'dates'    => $dates,
            'currentWeek' => $currentWeek,
            'selectedWeek' => $selectedWeek,
            'weekDue'  => $weekDue,
            'weekStrip' => $weekStrip,
            'weekSummary' => $weekSummary,
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
        $people = Goals::programmableStaff()->whereIn('id', $participantIds);
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

        // Per participant: add-customer window state, RAG light on the customer
        // count, and whether the "finish adding" reminder has gone out - for the
        // Participants tab. Keyed by user id.
        $participantMeta = NinetyDayRound::where('program_id', $program->id)->get()
            ->mapWithKeys(fn ($r) => [$r->user_id => Goals::windowState($r)]);

        // A single "current status" per participant for the Participants tab:
        // while they are still adding customers that is the live concern
        // (adding / reminder sent / window closed); once all are in, it is the
        // current week's submission (W-n submitted / pending / overdue).
        $cwByUser = [];
        foreach ($d['rows'] as $r) {
            $ck = $r->goal->checkins->firstWhere('week_no', $currentWeek);
            if (!$ck) {
                continue;
            }
            $uid = $r->user->id;
            $cwByUser[$uid]['total'] = ($cwByUser[$uid]['total'] ?? 0) + 1;
            $cwByUser[$uid]['done'] = ($cwByUser[$uid]['done'] ?? 0) + ($ck->status !== null ? 1 : 0);
        }
        $currentWeekOverdue = $program->starts_on->copy()->addDays(7 * $currentWeek)->lt($today);
        $participantStatus = [];
        foreach ($participantIds as $uid) {
            $m = $participantMeta[$uid] ?? null;
            if (!$m) {
                $participantStatus[$uid] = ['label' => '—', 'tone' => 'muted'];
                continue;
            }
            $cnt = $m['count'];
            $mn = $m['min'];
            if (!$m['meets_min']) {
                // Still short of the minimum: colour by the same red/amber RAG light as the
                // Customers column (red below the amber threshold, amber up to the minimum),
                // and let the label carry which state they are in.
                $tone = $m['rag']['level'] === 'green' ? 'amber' : $m['rag']['level'];
                if ($m['reminder_sent_at']) {
                    $participantStatus[$uid] = ['label' => "Reminder sent · {$cnt}/{$mn}", 'tone' => $tone];
                } elseif ($m['open']) {
                    $participantStatus[$uid] = ['label' => "Adding customers · {$cnt}/{$mn}", 'tone' => $tone];
                } else {
                    $participantStatus[$uid] = ['label' => "Window closed · {$cnt}/{$mn}", 'tone' => 'red'];
                }
            } elseif ($m['open']) {
                // Still inside the add-customer window but already has the full set:
                // done adding, weekly tracking has not begun yet - don't raise a
                // premature "week overdue". This is the clean "all added" state.
                $participantStatus[$uid] = ['label' => "All {$cnt} customers added", 'tone' => 'green'];
            } else {
                // Add window has closed: weekly tracking is the live concern now.
                $wk = $cwByUser[$uid] ?? null;
                if ($wk && $wk['total'] > 0 && $wk['done'] >= $wk['total']) {
                    $participantStatus[$uid] = ['label' => "All {$cnt} added · W{$currentWeek} submitted", 'tone' => 'green'];
                } elseif ($wk && $wk['total'] > 0) {
                    $participantStatus[$uid] = ['label' => "All {$cnt} added · W{$currentWeek} " . ($currentWeekOverdue ? 'overdue' : 'pending'), 'tone' => $currentWeekOverdue ? 'red' : 'amber'];
                } else {
                    $participantStatus[$uid] = ['label' => "All {$cnt} customers added", 'tone' => 'green'];
                }
            }
        }

        return view('tenant.performance.ninety-day-goals.program-detail', [
            'participantMeta' => $participantMeta,
            'participantStatus' => $participantStatus,
            'tab'        => null,
            'isAdmin'    => true,
            'canManage'  => Goals::managesEveryone($user),
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
            // Manage program panel
            'manageRounds' => Goals::managesEveryone($user)
                ? NinetyDayRound::where('program_id', $program->id)->with('user:id,first_name,last_name,code')->get()
                    ->map(fn ($r) => (object) ['user' => $r->user, 'customers' => Goals::customerCount($r), 'window' => Goals::windowState($r)])
                    ->filter(fn ($r) => $r->user)->sortBy(fn ($r) => $r->user->first_name)->values()
                : collect(),
            'addablePeople' => Goals::managesEveryone($user) ? Goals::programmableStaff()->whereNotIn('id', $participantIds)->values() : collect(),
            'canDeleteProgram' => $user->hasRole(['admin', 'Admin', 'super_admin']),
            'footprint'  => $user->hasRole(['admin', 'Admin', 'super_admin']) ? Goals::programFootprint($program) : null,
            'startLocked' => NinetyDayGoalCheckin::whereIn('round_id', NinetyDayRound::where('program_id', $program->id)->pluck('id'))->whereNotNull('status')->exists(),
        ]);
    }

    /** "Send Reminder" on a program's Overview page: nudges only the participants who still have
     *  an unsubmitted customer status for the given week — nobody who is already done, nobody
     *  outside this program. In-app notification only, per this app's no-real-mail rule. */
    public function programSendReminder(Request $request, $id)
    {
        $admin = Auth::user();
        if (!Goals::managesEveryone($admin)) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can send reminders.'], 403);
        }
        $program = NinetyDayProgram::findOrFail($id);
        $week = max(0, $request->integer('week', 0));

        $participantIds = NinetyDayRound::where('program_id', $program->id)->pluck('user_id')->unique()->values();
        $d = Goals::dashboard($participantIds->all(), ['program_id' => $program->id, 'round' => 'all']);

        // Same "submitted every customer this week or not" tally programDetail() itself uses,
        // just kept to who's short instead of a summary count.
        $byUser = [];
        foreach ($d['rows'] as $r) {
            $ck = $r->goal->checkins->firstWhere('week_no', $week);
            if (!$ck) {
                continue;
            }
            $uid = $r->user->id;
            $byUser[$uid]['total'] = ($byUser[$uid]['total'] ?? 0) + 1;
            $byUser[$uid]['done']  = ($byUser[$uid]['done'] ?? 0) + ($ck->status !== null ? 1 : 0);
        }
        $targetIds = [];
        foreach ($participantIds as $uid) {
            $wk = $byUser[$uid] ?? null;
            if ($wk && $wk['total'] > 0 && $wk['done'] < $wk['total']) {
                $targetIds[] = $uid;
            }
        }
        if (!$targetIds) {
            return response()->json(['success' => true, 'message' => 'Everyone has already submitted week W' . ($week + 1) . '.', 'count' => 0]);
        }

        $weekDue = $program->starts_on->copy()->addDays(7 * $week);
        foreach (User::whereIn('id', $targetIds)->get() as $u) {
            try {
                $u->notify(new WeekReminder($program, $week, $weekDue));
            } catch (\Throwable $e) {
                Log::warning("90-Day Goals week reminder failed for user {$u->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Reminder sent to ' . count($targetIds) . ' ' . \Illuminate\Support\Str::plural('salesperson', count($targetIds)) . ' for week W' . ($week + 1) . '.',
            'count'   => count($targetIds),
        ]);
    }

    // ── editing a program (Program Details > Manage program) ─────────────

    private function programManager(): ?User
    {
        $u = Auth::user();

        return Goals::managesEveryone($u) ? $u : null;
    }

    /** Rename / re-date a program (admin/HR). */
    public function programUpdate(Request $request, $id)
    {
        if (!$this->programManager()) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can change a program.'], 403);
        }
        $program = NinetyDayProgram::findOrFail($id);
        $data = $request->validate([
            'name'      => 'required|string|max:150',
            'starts_on' => 'required|date',
            'ends_on'   => 'required|date|after:starts_on',
        ]);
        $error = Goals::updateProgram($program, $data['name'], Carbon::parse($data['starts_on']), Carbon::parse($data['ends_on']));
        if ($error) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }
        $program->refresh();

        return response()->json(['success' => true, 'message' => 'Saved: "' . $program->name . '", ' . $program->starts_on->format('d M Y') . ' – ' . $program->ends_on->format('d M Y') . '.']);
    }

    /** Add people to a program (admin/HR); each gets the add-customers task and an in-app invite. */
    public function programAddParticipants(Request $request, $id)
    {
        $admin = $this->programManager();
        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can add people to a program.'], 403);
        }
        $program = NinetyDayProgram::findOrFail($id);
        $data = $request->validate(['user_ids' => 'required|array|min:1', 'user_ids.*' => 'integer|exists:users,id']);
        $result = Goals::addToProgram($program, $admin, $data['user_ids']);

        $roundIds = array_map(fn ($e) => $e['round']->id, $result['enrolled']);
        app()->terminating(function () use ($program, $roundIds) {
            foreach (NinetyDayRound::whereIn('id', $roundIds)->with('user')->get() as $round) {
                try {
                    $round->user?->notify(new ProgramInvite($program, $round));
                } catch (\Throwable $e) {
                    Log::warning("90-Day Goals program invite failed for user {$round->user_id}: " . $e->getMessage());
                }
            }
        });

        $names = fn ($list) => collect($list)->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->implode(', ');

        return response()->json([
            'success' => (bool) $result['enrolled'],
            'message' => count($result['enrolled']) . ' added to "' . $program->name . '".'
                . ($result['skipped'] ? ' ' . count($result['skipped']) . ' skipped - already in a program or with customers in their current round: ' . $names($result['skipped']) . '.' : ''),
        ], $result['enrolled'] ? 200 : 422);
    }

    /** Take someone out of a program (admin/HR), with their round, customers, tasks and notifications. */
    public function programRemoveParticipant($id, $userId)
    {
        if (!$this->programManager()) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can remove people from a program.'], 403);
        }
        $program = NinetyDayProgram::findOrFail($id);
        $c = Goals::removeFromProgram($program, (int) $userId);
        if (!$c['rounds']) {
            return response()->json(['success' => false, 'message' => 'That person is not in this program.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Removed, with ' . $c['customers'] . ' customer(s), ' . $c['tasks'] . ' task(s) and ' . $c['notifications'] . ' notification(s).']);
    }

    /** Reopen the add-customers window until a chosen time, for everyone or the chosen people (admin/HR). */
    public function programOpenWindow(Request $request, $id)
    {
        if (!$this->programManager()) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can open the window.'], 403);
        }
        $program = NinetyDayProgram::findOrFail($id);
        $data = $request->validate(['until' => 'required|date|after:now', 'user_ids' => 'nullable|array', 'user_ids.*' => 'integer']);
        $until = Carbon::parse($data['until']);
        $n = Goals::openCustomerWindow($program, $until, $data['user_ids'] ?? null);

        return response()->json(['success' => true, 'message' => 'Adding customers is open for ' . $n . ' ' . \Illuminate\Support\Str::plural('person', $n) . ' until ' . $until->format('D d M Y, h:i A') . '.']);
    }

    /** Delete a program and everything it created - admin only. */
    public function programDestroy(Request $request, $id)
    {
        $u = Auth::user();
        if (!$u || !$u->hasRole(['admin', 'Admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Only an admin can delete a program.'], 403);
        }
        $program = NinetyDayProgram::findOrFail($id);
        if (trim((string) $request->input('confirm_name')) !== trim($program->name)) {
            return response()->json(['success' => false, 'message' => 'Type the program name exactly to confirm.'], 422);
        }
        $name = $program->name;
        $c = Goals::deleteProgram($program);
        Log::info("90-Day Goals program deleted by user {$u->id}: \"$name\" (#$id)", $c);

        return response()->json(['success' => true, 'redirect' => route('performance.ninety-day-goals.program-dashboard'),
            'message' => '"' . $name . '" deleted: ' . $c['rounds'] . ' participant round(s), ' . $c['customers'] . ' customer(s), ' . $c['tasks'] . ' task(s) and ' . $c['notifications'] . ' notification(s).']);
    }

    // ── admin-created programs ───────────────────────────────────────────

    /** The form: name, dates, and every salesperson to invite (all ticked, deselect to exclude). */
    public function programCreate()
    {
        $user = Auth::user();
        if (!Goals::managesEveryone($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Only admin/HR can start a new 90-Day Goals program.');
        }

        return view('tenant.performance.ninety-day-goals.program-create', [
            'tab'    => 'goals',
            'canSwitch' => false,
            'seesMany'  => true,
            'people' => Goals::programmableStaff(),
            'roundSpanDays' => Goals::ROUND_SPAN_DAYS,
        ]);
    }

    /** Creates the program, enrolls whoever isn't already mid-round, and notifies each of them. */
    public function programStore(Request $request)
    {
        $admin = Auth::user();
        if (!Goals::managesEveryone($admin)) {
            return response()->json(['success' => false, 'message' => 'Only admin/HR can start a new 90-Day Goals program.'], 403);
        }
        $data = $request->validate([
            'name'          => 'required|string|max:150',
            'starts_on'     => 'required|date',
            'ends_on'       => 'required|date|after:starts_on',
            'user_ids'      => 'required|array|min:1',
            'user_ids.*'    => 'integer|exists:users,id',
        ]);

        if (Carbon::parse($data['starts_on'])->diffInDays(Carbon::parse($data['ends_on'])) > 366) {
            return response()->json(['success' => false, 'message' => 'A program can run at most one year.'], 422);
        }

        $result = Goals::createProgram($admin, $data['name'], Carbon::parse($data['starts_on']), Carbon::parse($data['ends_on']), $data['user_ids']);

        if (!$result['program']) {
            return response()->json(['success' => false, 'message' => 'Nobody could be added - everyone chosen is already in a program or has customers in their current round'
                . ($result['skipped'] ? ': ' . collect($result['skipped'])->map(fn ($u) => trim($u->first_name . ' ' . $u->last_name))->implode(', ') : '') . '.'], 422);
        }
        $movedToMonday = $result['starts_on']->toDateString() !== Carbon::parse($data['starts_on'])->toDateString()
            ? ' Weeks run Monday to Monday, so it starts on Monday ' . $result['starts_on']->format('d M') . ' and ends ' . $result['ends_on']->format('d M Y') . '.'
            : '';

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
            'message' => count($result['enrolled']) . ' salesperson(s) added to "' . $result['program']->name . '".' . $movedToMonday
                . ($result['skipped'] ? ' ' . count($result['skipped']) . ' skipped - already in a program or with customers in their current round: '
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

        // The window shuts 48 hours after enrolment. Admin/HR keep adding after that, because
        // somebody has to be able to correct a mistake once the deadline has passed.
        if (!Goals::customerWindowOpen($round) && !Goals::managesEveryone($user)) {
            $closed = Goals::customerWindowClosesAt($round);

            return response()->json([
                'success' => false,
                'message' => 'The window for adding target customers closed on '
                    . $closed->format('d M Y, H:i') . '. Ask admin or HR if something still needs to go in.',
            ], 422);
        }

        $mine = Goals::pipelineCustomers($target)->keyBy('id');

        $added = 0;
        $skipped = [];
        $newGoalIds = [];
        // Under a lock on the person: the "already in this round" check and the insert happen
        // together, so a double-click or two tabs can't add the same customer twice.
        Goals::lockedFor($target, function () use ($data, $round, $mine, $target, $user, &$added, &$skipped, &$newGoalIds) {
        $have = NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->pluck('sales_pipeline_id')->filter()->all();
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
            $goal = NinetyDayGoal::create([
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
            $newGoalIds[] = $goal->id;
            $added++;
        }
        });
        Goals::ensureCheckins($round);

        // A brand new customer gets a sensible default next-follow-up date - one week out - on
        // its FIRST answerable week's row: this Monday's if it was added on a Monday, otherwise
        // next Monday's (firstWeek()). It used to go on the current week's row even mid-week,
        // whose Monday had already passed - so the customer showed as overdue the moment it was
        // added, and its first status counted as late. A row for next week is not due yet, so
        // it is neither pending nor late until that Monday arrives.
        if ($newGoalIds) {
            $followUp = Carbon::today()->addDays(7)->toDateString();
            foreach (NinetyDayGoal::whereIn('id', $newGoalIds)->get() as $goal) {
                $week = Goals::firstWeek($goal, $round);
                if ($week >= Goals::weeksOf($round)) {
                    continue;   // added in the round's last week: nothing left to report
                }
                NinetyDayGoalCheckin::firstOrCreate(
                    ['goal_id' => $goal->id, 'week_no' => $week],
                    ['round_id' => $round->id, 'tenant_id' => $goal->tenant_id, 'due_on' => Goals::dueOn($round, $week)->toDateString(), 'next_follow_up_date' => $followUp]
                );
            }
        }

        // Tell the caller where they now stand against the minimum and the deadline, so the
        // page can keep nudging without a second request.
        $state = Goals::windowState($round->fresh());

        return response()->json([
            'success' => true,
            'added'   => $added,
            'skipped' => $skipped,
            'window'  => [
                'count'     => $state['count'],
                'min'       => $state['min'],
                'short_by'  => $state['short_by'],
                'meets_min' => $state['meets_min'],
                'open'      => $state['open'],
                'closes_at' => $state['closes_at']->toIso8601String(),
            ],
            'message' => $state['meets_min']
                ? $added . ' customer(s) added. You now have ' . $state['count'] . ' — the minimum of ' . $state['min'] . ' is met.'
                : $added . ' customer(s) added. You have ' . $state['count'] . ' of ' . $state['min']
                    . ' — add ' . $state['short_by'] . ' more before ' . $state['closes_at']->format('d M, H:i')
                    . ' or this round will not be counted.',
        ]);
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
            'entries.*.next_action' => 'nullable|string|max:255',
            'entries.*.next_follow_up_date' => 'nullable|date',
        ]);

        $entries = [];
        foreach ($data['entries'] as $e) {
            $entries[(int) $e['goal_id']] = [
                'status' => $e['status'] ?? '', 'comment' => $e['comment'] ?? '',
                'next_action' => $e['next_action'] ?? '', 'next_follow_up_date' => $e['next_follow_up_date'] ?? null,
            ];
        }
        [$saved, $errors, $notes] = Goals::submit($target, (int) $data['week_no'], $entries, null, $actor);

        return response()->json(['success' => $saved > 0 && !$errors, 'saved' => $saved, 'errors' => $errors, 'notes' => $notes,
            'message' => trim(($errors ? implode(' ', $errors) : "Saved {$saved} status" . ($saved === 1 ? '' : 'es') . '.') . ' ' . implode(' ', $notes))], $errors && !$saved ? 422 : 200);
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

        // monthly_potential holds the customer's overall target that achievement % and the rankings are
        // measured against, so the salesperson can't move it once it has been reported on or the
        // round is over - lowering it after the fact inflated their achievement. Admin/HR still can.
        if (isset($data['monthly_potential']) && (float) $data['monthly_potential'] !== (float) $goal->monthly_potential
            && !Goals::managesEveryone(Auth::user())) {
            $closed = $goal->closed_at || optional($goal->round)->status !== 'active';
            $reported = NinetyDayGoalCheckin::where('goal_id', $goal->id)->whereNotNull('status')->exists();
            if ($closed || $reported) {
                return response()->json(['success' => false, 'message' => 'The target is fixed once you have reported on it or the round has ended. Ask an admin if it has to change.'], 422);
            }
        }
        $goal->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(['success' => true, 'message' => 'Goal updated.']);
    }

    public function destroy($id)
    {
        // Once a customer is added, the sales team cannot remove it: the history stays on record.
        // Only admin/HR can delete, and the check-ins and comments go with it.
        if (!Goals::managesEveryone(Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Once a customer is added it stays on record. Ask an admin if one has to be removed.'], 403);
        }
        $goal = NinetyDayGoal::findOrFail($id);
        $goal->checkins()->each(fn ($c) => $c->notes()->delete());
        $goal->checkins()->delete();
        $goal->delete();

        return response()->json(['success' => true, 'message' => 'Customer removed.']);
    }

    // ── ask to remove a goal (sales team can't delete outright; admin/HR decides) ────────

    /** The goal's own owner asks for it to be removed. Admin/HR gets notified and decides. */
    public function requestDelete(Request $request, $id)
    {
        $user = Auth::user();
        $goal = NinetyDayGoal::findOrFail($id);
        if ((int) $goal->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Only the owner of this customer can ask for it to be removed.'], 403);
        }
        if ($goal->pendingDeleteRequest) {
            return response()->json(['success' => false, 'message' => 'A removal request for this customer is already pending.'], 422);
        }
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $deleteRequest = NinetyDayGoalDeleteRequest::create([
            'goal_id' => $goal->id, 'requested_by_id' => $user->id, 'reason' => $data['reason'] ?? null, 'status' => 'pending',
        ]);
        $deleteRequest->setRelation('goal', $goal)->setRelation('requestedBy', $user);
        NotificationHelper::notifyHRAndAdminOnly(new NinetyDayGoalDeleteRequested($deleteRequest));

        return response()->json(['success' => true, 'message' => 'Removal request sent to admin/HR.']);
    }

    /** Admin/HR approves (the goal is actually removed) or rejects a pending removal request. */
    public function decideDelete(Request $request, $id)
    {
        $user = Auth::user();
        if (!Goals::managesEveryone($user)) {
            return response()->json(['success' => false, 'message' => 'Not allowed.'], 403);
        }
        $deleteRequest = NinetyDayGoalDeleteRequest::with('goal', 'requestedBy')->findOrFail($id);
        if (!$deleteRequest->isPending()) {
            return response()->json(['success' => false, 'message' => 'This request was already decided.'], 422);
        }
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'admin_remarks' => 'nullable|string|max:500']);

        if ($data['status'] === 'approved' && $deleteRequest->goal) {
            $goal = $deleteRequest->goal;
            $goal->checkins()->each(fn ($c) => $c->notes()->delete());
            $goal->checkins()->delete();
            $goal->delete();
        }
        $deleteRequest->update([
            'status' => $data['status'], 'decided_by_id' => $user->id, 'decided_at' => now(),
            'admin_remarks' => $data['admin_remarks'] ?? null,
        ]);
        if ($deleteRequest->requestedBy) {
            $deleteRequest->requestedBy->notify(new NinetyDayGoalDeleteDecision($deleteRequest));
        }

        return response()->json(['success' => true, 'message' => 'Request ' . $data['status'] . '.']);
    }

    /** Every removal request in one place, admin/HR only — Pending first so nothing sits unseen. */
    public function deleteRequestsIndex(Request $request)
    {
        $user = Auth::user();
        if (!Goals::managesEveryone($user)) {
            abort(403, 'Only admin/HR review removal requests.');
        }
        $tab = in_array($request->get('tab'), ['approved', 'rejected'], true) ? $request->get('tab') : 'pending';

        $requests = NinetyDayGoalDeleteRequest::with('goal.round.user', 'requestedBy', 'decidedBy')
            ->where('status', $tab)
            ->orderByDesc($tab === 'pending' ? 'created_at' : 'decided_at')
            ->paginate(25)->withQueryString();

        $counts = NinetyDayGoalDeleteRequest::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('tenant.performance.ninety-day-goals.delete-requests', [
            'navTab'   => 'delete-requests',
            'requests' => $requests,
            'tab'      => $tab,
            'counts'   => $counts,
        ]);
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
        // Someone other than the goal's own owner replied (their lead, admin/HR) — let them know.
        if ((int) $goal->user_id !== (int) $user->id && $goal->user) {
            $goal->user->notify(new NinetyDayGoalNoteAdded($goal, $user, (int) $data['week_no'], $note->body));
        }

        return response()->json(['success' => true, 'note' => [
            'id' => $note->id, 'by' => trim($user->first_name . ' ' . $user->last_name), 'body' => $note->body,
            'at' => $note->created_at->format('d M'), 'can_delete' => Goals::managesEveryone($user),
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
        if (!Goals::managesEveryone(Auth::user())) {
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
            if (!Goals::managesEveryone($user)) {
                return null;
            }
            $other = User::notTerminated()->find((int) $request->input('user_id'));

            // A participant (anyone enrolled, incl. Export-department staff an admin
            // added to a program) may manage their own customers too — not only
            // Sales-department staff. Mirrors canOpenPage(), which already lets a
            // participant open the page; the add/store gate was stricter and left
            // enrolled non-Sales people unable to add any customers (403).
            return $other && (Goals::isSalesStaff($other) || Goals::isParticipant($other)) ? $other : null;
        }

        return (Goals::isSalesStaff($user) || Goals::isParticipant($user)) ? $user : null;
    }

    private function mayChange(NinetyDayGoal $goal): bool
    {
        $user = Auth::user();

        return Goals::managesEveryone($user) || (int) $goal->user_id === (int) $user->id;
    }
}
