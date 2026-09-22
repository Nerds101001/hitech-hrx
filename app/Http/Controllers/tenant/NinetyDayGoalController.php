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

        return view('tenant.performance.ninety-day-goals.index', [
            'mode'      => 'mine',
            'isAdmin'   => Goals::seesEveryone($user),
            'canSwitch' => Goals::visibleUserIds($user) !== [$user->id],
            'round'     => $round,
            'week'      => Goals::weekOf($round, $today),
            'dates'     => Goals::weekDates($round),
            'goals'     => $goals,
            'pending'   => $pending,
            'today'     => $today,
            'statuses'  => Goals::STATUSES,
            'brands'    => array_keys(Goals::BRANDS),
            'weeks'     => Goals::WEEKS,
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

        $strip = [];
        for ($w = 0; $w < Goals::WEEKS; $w++) {
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
            'weeks'      => Goals::WEEKS,
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

        $weeklyGoals = NinetyDayGoal::with(['user.department', 'checkins'])
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->whereHas('round', fn ($r) => $r->where('status', 'active'))
            ->get()->sortBy(fn ($g) => strtolower(trim(optional($g->user)->first_name . ' ' . optional($g->user)->last_name)))->values();

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
            'weeks'    => Goals::WEEKS,
            'team'      => $team,
            'weeklyGoals' => $weeklyGoals,
            'statuses'  => $current['statuses'],
            'trialing'  => $trialing,
            'currentTotal' => $current['cards']['goals'],
            'attention' => $attention,
        ]);
    }

    /** Every filter this page takes, read the same way in adminView() and adminViewExport(). */
    private function adminViewFilters(Request $request): array
    {
        return [
            'program_id' => $request->integer('program_id') ?: null,
            'user_id'    => $request->integer('user_id') ?: null,
            'customer'   => trim((string) $request->input('customer')) ?: null,
            'status'     => in_array($request->input('status'), Goals::STATUSES, true) ? $request->input('status') : null,
            'week'       => $request->input('week') !== null && $request->input('week') !== '' ? (int) $request->input('week') : null,
            'brand'      => $request->input('brand') ?: null,
            'round'      => $request->input('round') === 'all' ? 'all' : 'current',
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
     * The "Admin View" page: every program, every salesperson, every customer, in one place —
     * reference screenshot's Program Overview / Salesperson Performance / Customer Tracking /
     * Weekly Submissions / Orders & Results / At Risk / Reports, as sections of one page (never
     * separate URLs — the team asked for that explicitly), switched client-side.
     */
    public function adminView(Request $request)
    {
        $user = Auth::user();
        if (!Goals::seesEveryone($user)) {
            return redirect()->route('tenant.dashboard')->with('error', 'Admin View is for admin/HR.');
        }
        $today = Carbon::today();
        $f = $this->adminViewFilters($request);

        $programs = NinetyDayProgram::orderByDesc('id')->get();
        if ($f['program_id'] && !$programs->contains('id', $f['program_id'])) {
            $f['program_id'] = null;
        }
        $people = Goals::salesStaff();
        if ($f['user_id'] && !$people->contains('id', $f['user_id'])) {
            $f['user_id'] = null;
        }

        $d = Goals::dashboard(null, $f);
        $rows = $this->adminViewRows($d, $f, $today);

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

        // Recent activity: real weekly submissions and comments, newest first — not a separate log.
        $recentCheckins = NinetyDayGoalCheckin::whereNotNull('status')->whereNotNull('submitted_at')
            ->with(['goal.user', 'goal.round'])
            ->when($f['program_id'], fn ($q) => $q->whereHas('goal.round', fn ($r) => $r->where('program_id', $f['program_id'])))
            ->orderByDesc('submitted_at')->limit(6)->get();
        $recentNotes = NinetyDayGoalNote::with(['goal.user', 'author'])
            ->when($f['program_id'], fn ($q) => $q->whereHas('goal.round', fn ($r) => $r->where('program_id', $f['program_id'])))
            ->orderByDesc('created_at')->limit(6)->get();
        $activity = $recentCheckins->map(fn ($c) => (object) [
            'at' => $c->submitted_at, 'who' => trim(optional($c->goal->user)->first_name . ' ' . optional($c->goal->user)->last_name),
            'what' => 'updated ' . $c->goal->client_name . ' — ' . $c->status . ' (W' . $c->week_no . ')',
        ])->concat($recentNotes->map(fn ($n) => (object) [
            'at' => $n->created_at, 'who' => trim(optional($n->author)->first_name . ' ' . optional($n->author)->last_name),
            'what' => 'commented on ' . $n->goal->client_name,
        ]))->sortByDesc('at')->take(8)->values();

        // Server-side pagination for the big customer table — the whole filtered set is already
        // in memory (this dataset is hundreds of rows, not millions), but the DOM only ever
        // gets one page of it.
        $perPage = 25;
        $page = max(1, $request->integer('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('tenant.performance.ninety-day-goals.admin-view', [
            'tab'        => 'admin',
            'isAdmin'    => true,
            'filters'    => $f,
            'programs'   => $programs,
            'people'     => $people,
            'brands'     => array_merge(array_keys(Goals::BRANDS), ['Other']),
            'weeks'      => Goals::WEEKS,
            'statuses'   => Goals::STATUSES,
            'today'      => $today,
            'cards'      => $cards,
            'activePrograms' => $activePrograms,
            'byProgram'  => $d['byProgram'],
            'statusCounts' => $d['statuses'],
            'orderResults' => $d['orderResults'],
            'compliance' => $compliance,
            'salespeople' => $salespeople,
            'rows'       => $paged,
            'allRowsCount' => $rows->count(),
            'atRisk'     => $atRisk,
            'activity'   => $activity,
        ]);
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

        $filename = '90-day-goals-' . now()->format('Y-m-d-His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                ['Program', 'Salesperson', 'Customer', 'Product', 'Brand', 'Goal (Monthly ₹L)'],
                array_map(fn ($w) => 'W' . $w, range(0, Goals::WEEKS - 1)),
                ['Actual (₹L)', 'Achievement %', 'Current Status']
            ));
            foreach ($rows as $r) {
                $weekCells = [];
                for ($w = 0; $w < Goals::WEEKS; $w++) {
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
            'weeks'    => Goals::WEEKS,
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
        $data = $request->validate([
            'week_no'             => 'required|integer|min:0|max:' . (Goals::WEEKS - 1),
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
        $data = $request->validate([
            'goal_id' => 'required|integer',
            'week_no' => 'required|integer|min:0|max:' . (Goals::WEEKS - 1),
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
