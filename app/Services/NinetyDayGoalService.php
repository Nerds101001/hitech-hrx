<?php

namespace App\Services;

use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayGoalNote;
use App\Models\NinetyDayRound;
use App\Models\SalesPipeline;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The 90-Day Goals tracker, following the sales team's own sheet.
 *
 * Each person in the Sales department picks target customers from their own
 * Sales Pipeline (customer, product, brand, goal, monthly potential in lakh) and
 * reports a status on every Monday for 13 weeks (W0 to W12). Then the round
 * closes and a new one starts the following Monday.
 *
 * A Monday that has passed without a status is late: the person can still fill
 * it in, but must choose a status AND write a comment saying why. Each Monday
 * also puts one task on their list, which completes itself when they submit.
 */
class NinetyDayGoalService
{
    /** The choices on the sheet's drop-down, in its order. */
    public const STATUSES = ['On Track', 'Off Track', 'Trial Order', 'Regular Order', 'No Report', 'Dropped', 'Transferred'];

    /** Chip colours [background, text], close to the sheet's so the team recognises them at a glance. */
    public const STATUS_COLORS = [
        'On Track'    => ['#0f7b5c', '#ffffff'],
        'Off Track'   => ['#b42318', '#ffffff'],
        'Trial Order' => ['#6d3aa5', '#ffffff'],
        'Regular Order' => ['#b9d9ea', '#0b3b53'],
        'No Report'   => ['#3f4652', '#ffffff'],
        'Dropped'     => ['#4c2a7a', '#ffffff'],
        'Transferred' => ['#f9d0cf', '#9f1d1a'],
    ];

    /** W0 to W12. */
    public const WEEKS = 13;

    /** A round is about three months, so a customer's round target is its monthly potential times this. */
    public const ROUND_MONTHS = 3;

    /** Days from a round's first Monday to its last day (13 weeks). */
    public const ROUND_SPAN_DAYS = 90;

    /** Brand => spellings found in the free-text product column (same list the Sales Pipeline filters on). */
    public const BRANDS = [
        'Rust-X'     => ['rust-x', 'rustx', 'rust x'],
        'Dr. Bio'    => ['dr.bio', 'dr. bio', 'dr bio', 'drbio'],
        'Fillezy'    => ['fillezy', 'filezy'],
        'KIF'        => ['kif'],
        'Zorbit'     => ['zorbit'],
        'Tuffpaulin' => ['tuffpaulin', 'tuff paulin'],
        'Neutrasafe' => ['neutrasafe'],
    ];

    // ── who ──────────────────────────────────────────────────────────────

    /** Active people in a department with "Sales" in its name. */
    public static function salesStaff(): Collection
    {
        return User::notTerminated()
            ->whereHas('department', fn ($d) => $d->where('name', 'like', '%sales%'))
            ->orderBy('first_name')->get();
    }

    public static function isSalesStaff(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return str_contains(strtolower((string) optional($user->department)->name), 'sales');
    }

    /** Admin, HR, or anyone delegated goals.manage / scorecards.manage sees everyone. */
    public static function seesEveryone(?User $user): bool
    {
        return $user && ($user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])
            || $user->can('goals.manage') || $user->can('scorecards.manage'));
    }

    public static function canOpenPage(?User $user): bool
    {
        return self::seesEveryone($user) || self::isSalesStaff($user) || ($user && $user->hasRole(['manager', 'Manager']));
    }

    /** The ids whose goals this viewer may see: null = everyone. */
    public static function visibleUserIds(User $viewer): ?array
    {
        if (self::seesEveryone($viewer)) {
            return null;
        }
        if ($viewer->hasRole(['manager', 'Manager'])) {
            return User::where('reporting_to_id', $viewer->id)->pluck('id')->push($viewer->id)->all();
        }

        return [$viewer->id];
    }

    // ── rounds and weeks ─────────────────────────────────────────────────

    public static function mondayOf(Carbon $day): Carbon
    {
        return $day->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    public static function activeRound(User $user): ?NinetyDayRound
    {
        return NinetyDayRound::where('user_id', $user->id)->where('status', 'active')->orderByDesc('starts_on')->first();
    }

    public static function startRound(User $user, Carbon $monday): NinetyDayRound
    {
        $monday = self::mondayOf($monday);

        return NinetyDayRound::create([
            'tenant_id' => $user->tenant_id,
            'user_id'   => $user->id,
            'round_no'  => (int) NinetyDayRound::where('user_id', $user->id)->max('round_no') + 1,
            'starts_on' => $monday->toDateString(),
            'ends_on'   => $monday->copy()->addDays(self::ROUND_SPAN_DAYS)->toDateString(),
            'status'    => 'active',
        ]);
    }

    /** The user's active round, starting one this week if they have none. */
    public static function roundFor(User $user, ?Carbon $today = null): NinetyDayRound
    {
        return self::activeRound($user) ?? self::startRound($user, $today ?: Carbon::today());
    }

    /** Which week (0..12) a date falls in for the round. */
    public static function weekOf(NinetyDayRound $round, ?Carbon $today = null): int
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();
        $days = Carbon::parse($round->starts_on)->startOfDay()->diffInDays($today, false);

        return max(0, min(self::WEEKS - 1, intdiv((int) $days, 7)));
    }

    public static function dueOn(NinetyDayRound $round, int $week): Carbon
    {
        return Carbon::parse($round->starts_on)->startOfDay()->addDays(7 * $week);
    }

    /** Monday-by-Monday dates for the column headers. */
    public static function weekDates(NinetyDayRound $round): array
    {
        return array_map(fn ($w) => self::dueOn($round, $w), range(0, self::WEEKS - 1));
    }

    // ── check-ins ────────────────────────────────────────────────────────

    /**
     * The first Monday a goal is answerable for. Added on a Monday, that Monday
     * counts; added any other day, it starts next Monday, so nobody is late on
     * day one for a week that was already over.
     */
    public static function firstWeek(NinetyDayGoal $goal, NinetyDayRound $round): int
    {
        $added = Carbon::parse($goal->created_at)->startOfDay();
        $days = max(0, Carbon::parse($round->starts_on)->startOfDay()->diffInDays($added, false));
        $week = intdiv((int) $days, 7);

        return $added->isMonday() ? $week : $week + 1;
    }

    /** Make sure every open goal has a check-in row for each Monday up to today. */
    public static function ensureCheckins(NinetyDayRound $round, ?Carbon $today = null): int
    {
        $current = self::weekOf($round, $today);
        $made = 0;
        foreach (NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->get() as $goal) {
            for ($w = self::firstWeek($goal, $round); $w <= $current; $w++) {
                $row = NinetyDayGoalCheckin::firstOrCreate(
                    ['goal_id' => $goal->id, 'week_no' => $w],
                    ['round_id' => $round->id, 'tenant_id' => $goal->tenant_id, 'due_on' => self::dueOn($round, $w)->toDateString()]
                );
                $made += $row->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $made;
    }

    /** Pending check-ins that are due today or earlier, oldest week first. */
    public static function pending(NinetyDayRound $round, ?Carbon $today = null): Collection
    {
        $today = ($today ?: Carbon::today())->toDateString();

        return NinetyDayGoalCheckin::with('goal')
            ->where('round_id', $round->id)->whereNull('status')->where('due_on', '<=', $today)
            ->whereHas('goal', fn ($g) => $g->whereNull('closed_at'))
            ->orderBy('week_no')->get();
    }

    /**
     * Submit one Monday's statuses. $entries is [goal_id => ['status' => ..., 'comment' => ...]].
     * A Monday already past is late and needs a comment. Returns [saved, errors[]].
     */
    public static function submit(User $user, int $week, array $entries, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $today = $now->copy()->startOfDay();
        $round = self::activeRound($user);
        $errors = [];
        $saved = 0;

        if (!$round) {
            return [0, ['You have no active 90-day round.']];
        }
        if ($week < 0 || $week >= self::WEEKS) {
            return [0, ['That week does not exist.']];
        }
        $due = self::dueOn($round, $week);
        if ($due->gt($today)) {
            return [0, ['Week ' . $week . ' is not due until ' . $due->format('D d M') . '.']];
        }
        $late = $today->gt($due);

        $goals = NinetyDayGoal::where('round_id', $round->id)->where('user_id', $user->id)->whereNull('closed_at')
            ->whereIn('id', array_keys($entries))->get()->keyBy('id');

        foreach ($entries as $goalId => $e) {
            $goal = $goals->get($goalId);
            $status = trim((string) ($e['status'] ?? ''));
            $comment = trim((string) ($e['comment'] ?? ''));
            if (!$goal) {
                $errors[] = "Goal #{$goalId} is not one of yours.";
                continue;
            }
            if ($status === '') {
                continue;   // left blank: stays pending
            }
            if (!in_array($status, self::STATUSES, true)) {
                $errors[] = "{$goal->client_name}: \"{$status}\" is not a status you can pick.";
                continue;
            }
            if ($late && $comment === '') {
                $errors[] = "{$goal->client_name}: this Monday has passed, so a comment is needed with the status.";
                continue;
            }
            $onRecord = NinetyDayGoalCheckin::where('goal_id', $goal->id)->where('week_no', $week)->whereNotNull('status')->where('status', '!=', '')->exists();
            if ($onRecord) {
                $errors[] = "{$goal->client_name}: week {$week} already has a status, which stays on record.";
                continue;
            }

            DB::transaction(function () use ($goal, $round, $week, $due, $status, $comment, $late, $user, $now, &$saved) {
                NinetyDayGoalCheckin::updateOrCreate(
                    ['goal_id' => $goal->id, 'week_no' => $week],
                    ['round_id' => $round->id, 'tenant_id' => $goal->tenant_id, 'due_on' => $due->toDateString(), 'status' => $status,
                     'comment' => $comment !== '' ? $comment : null, 'submitted_at' => $now, 'submitted_by_id' => $user->id, 'is_late' => $late]
                );
                $goal->forceFill(["w{$week}" => $status])->save();   // the older columns the scorecard code still reads
                $saved++;
            });
        }

        self::completeTaskIfDone($user, $round, $week);

        return [$saved, $errors];
    }

    // ── the Monday task ──────────────────────────────────────────────────

    private static function weekTaskTitle(int $week, Carbon $due): string
    {
        return '90-Day Goals: submit W' . $week . ' status (due ' . $due->format('D d M') . ')';
    }

    private static function addCustomersTitle(NinetyDayRound $round): string
    {
        return '90-Day Goals: add your target customers (round ' . $round->round_no . ')';
    }

    /** One task per person per Monday; nothing is emailed, it just appears on their list. */
    public static function ensureTask(User $user, string $title, Carbon $due): Task
    {
        $existing = Task::where('user_id', $user->id)->where('title', $title)->first();
        if ($existing) {
            return $existing;
        }

        return Task::create([
            'user_id'        => $user->id,
            'assigned_by_id' => $user->id,
            'created_by_id'  => $user->id,
            'updated_by_id'  => $user->id,
            'title'          => $title,
            'description'    => 'Open 90-Day Goals and pick a status for each customer. If the Monday has passed you also need a comment.',
            'type'           => 'open',
            'status'         => 'new',
            'priority'       => 'medium',
            'for_date'       => $due->copy(),
            'due_date'       => $due->copy()->toDateString(),
            'tenant_id'      => $user->tenant_id,
        ]);
    }

    public static function completeTaskIfDone(User $user, NinetyDayRound $round, int $week): void
    {
        $open = NinetyDayGoalCheckin::where('round_id', $round->id)->where('week_no', $week)->whereNull('status')
            ->whereHas('goal', fn ($g) => $g->whereNull('closed_at')->where('user_id', $user->id))->exists();
        if ($open) {
            return;
        }
        $title = self::weekTaskTitle($week, self::dueOn($round, $week));
        Task::where('user_id', $user->id)->where('title', $title)->whereNotIn('status', Task::CLOSED_STATUSES)
            ->update(['status' => 'completed', 'completed_at' => now()]);
    }

    // ── the weekly run ───────────────────────────────────────────────────

    /**
     * Every Monday: close rounds that have ended, start a round for anyone in Sales
     * without one, open this week's check-ins and put the task on each list.
     */
    public static function runWeekly(?Carbon $today = null): array
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();
        $out = ['closed' => 0, 'started' => 0, 'checkins' => 0, 'tasks' => 0];

        foreach (NinetyDayRound::where('status', 'active')->whereDate('ends_on', '<', $today->toDateString())->get() as $round) {
            NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->update(['closed_at' => now()]);
            $round->update(['status' => 'closed', 'closed_at' => now()]);
            $out['closed']++;
        }

        foreach (self::salesStaff() as $user) {
            $round = self::activeRound($user);
            if (!$round) {
                $round = self::startRound($user, $today);
                $out['started']++;
            }
            $week = self::weekOf($round, $today);
            $out['checkins'] += self::ensureCheckins($round, $today);

            $hasGoals = NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->exists();
            if (!$hasGoals) {
                if ($week === 0) {
                    $out['tasks'] += self::ensureTask($user, self::addCustomersTitle($round), $today)->wasRecentlyCreated ? 1 : 0;
                }
                continue;
            }
            if (self::pending($round, $today)->where('week_no', $week)->isNotEmpty()) {
                $out['tasks'] += self::ensureTask($user, self::weekTaskTitle($week, self::dueOn($round, $week)), self::dueOn($round, $week))->wasRecentlyCreated ? 1 : 0;
            }
        }

        return $out;
    }

    // ── the dashboard ────────────────────────────────────────────────────

    /** The latest status a goal has been given, or null if nothing has been reported. */
    public static function latestStatus(NinetyDayGoal $goal): ?string
    {
        return optional($goal->checkins->whereNotNull('status')->sortByDesc('week_no')->first())->status;
    }

    /**
     * Everything the dashboard shows, for the goals in $scope (null = everyone).
     *
     * Achievement is real sales: what the customer has bought in the Sales Report during
     * the round (lakh), against its monthly potential over the round's three months.
     * Filters: round (current|all), user_id, department_id, brand.
     */
    public static function dashboard(?array $scope, array $f = [], ?Carbon $today = null): array
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();

        $q = NinetyDayGoal::with(['user.department', 'round', 'checkins'])->whereNotNull('round_id');
        if ($scope !== null) {
            $q->whereIn('user_id', $scope);
        }
        if (!empty($f['user_id'])) {
            $q->where('user_id', (int) $f['user_id']);
        }
        if (!empty($f['department_id'])) {
            $q->whereHas('user', fn ($u) => $u->where('department_id', (int) $f['department_id']));
        }
        if (($f['round'] ?? 'current') === 'current') {
            $q->whereHas('round', fn ($r) => $r->where('status', 'active'));
        }
        if (!empty($f['brand'])) {
            $f['brand'] === 'Other'
                ? $q->where(fn ($w) => $w->whereNull('brand')->orWhere('brand', ''))
                : $q->where('brand', $f['brand']);
        }
        $goals = $q->get();

        // Actual sales per pipeline customer per month, in lakh.
        $sales = [];
        $ids = $goals->pluck('sales_pipeline_id')->filter()->unique()->values()->all();
        if ($ids) {
            foreach (DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $ids)
                ->selectRaw('sales_pipeline_id, month_year, SUM(sale_amount) s')->groupBy('sales_pipeline_id', 'month_year')->get() as $r) {
                $sales[$r->sales_pipeline_id][$r->month_year] = (float) $r->s;
            }
        }

        $rows = $goals->map(function (NinetyDayGoal $g) use ($sales, $today) {
            $from = Carbon::parse($g->round->starts_on)->startOfMonth();
            $to = Carbon::parse($g->round->ends_on)->min($today->copy())->startOfMonth();
            $achieved = 0.0;
            for ($m = $from->copy(); $m->lte($to); $m->addMonth()) {
                $achieved += $sales[$g->sales_pipeline_id][$m->format('Y-m')] ?? 0.0;
            }
            $potential = (float) $g->monthly_potential;
            $target = $potential * self::ROUND_MONTHS;

            return (object) [
                'goal'      => $g,
                'user'      => $g->user,
                'brand'     => $g->brand ?: 'Other',
                'potential' => $potential,
                'target'    => $target,
                'achieved'  => round($achieved, 2),
                'pct'       => $target > 0 ? round($achieved / $target * 100, 1) : null,
                'status'    => self::latestStatus($g),
            ];
        });

        $sum = fn ($c) => [
            'goals'     => $c->count(),
            'potential' => round($c->sum('potential'), 2),
            'target'    => round($c->sum('target'), 2),
            'achieved'  => round($c->sum('achieved'), 2),
            'pct'       => $c->sum('target') > 0 ? round($c->sum('achieved') / $c->sum('target') * 100, 1) : null,
        ];

        // Reporting discipline: every Monday that has come due.
        $due = $goals->flatMap(fn ($g) => $g->checkins->filter(fn ($c) => $c->due_on->lte($today)));
        $onTime = $due->filter(fn ($c) => $c->status !== null && !$c->is_late)->count();
        $discipline = fn ($c) => $c->count() > 0 ? round($c->filter(fn ($x) => $x->status !== null && !$x->is_late)->count() / $c->count() * 100) : null;

        $converted = ['Trial Order', 'Regular Order'];
        $cards = $sum($rows) + [
            'people'     => $rows->pluck('user.id')->unique()->count(),
            'converted'  => $rows->whereIn('status', $converted)->count(),
            'onTrack'    => $rows->where('status', 'On Track')->count(),
            'discipline' => $discipline($due),
            'dueTotal'   => $due->count(),
            'onTimeTotal' => $onTime,
        ];

        $users = $rows->groupBy('user.id')->map(function ($c) use ($sum, $discipline, $goals, $converted) {
            $u = $c->first()->user;
            $mine = $goals->where('user_id', $u->id)->flatMap(fn ($g) => $g->checkins->filter(fn ($x) => $x->due_on->lte(Carbon::today())));

            return $sum($c) + [
                'id'         => $u->id,
                'name'       => trim($u->first_name . ' ' . $u->last_name),
                'department' => optional($u->department)->name,
                'converted'  => $c->whereIn('status', $converted)->count(),
                'discipline' => $discipline($mine),
                'late'       => $mine->where('is_late', true)->count(),
                'missing'    => $mine->whereNull('status')->count(),
            ];
        })->values()->sortByDesc(fn ($u) => [$u['pct'] === null ? -1 : $u['pct'], $u['achieved']])->values();

        $topBrands = $rows->groupBy('brand')->map(fn ($c, $b) => $sum($c) + ['brand' => $b])->sortByDesc('potential')->take(10)->values();

        $statuses = [];
        foreach (self::STATUSES as $st) {
            $statuses[$st] = $rows->where('status', $st)->count();
        }
        $statuses['Nothing reported yet'] = $rows->whereNull('status')->count();

        $strip = [];
        for ($w = 0; $w < self::WEEKS; $w++) {
            $cs = $goals->map(fn ($g) => $g->checkins->firstWhere('week_no', $w))->filter();
            $strip[$w] = ['total' => $cs->count(), 'done' => $cs->whereNotNull('status')->count(), 'late' => $cs->where('is_late', true)->count()];
        }

        return [
            'cards'        => $cards,
            'topCustomers' => $rows->sortByDesc('potential')->take(10)->values(),
            'topBrands'    => $topBrands,
            'topAchieved'  => $rows->filter(fn ($r) => $r->achieved > 0)->sortByDesc('achieved')->take(10)->values(),
            'users'        => $users,
            'statuses'     => $statuses,
            'strip'        => $strip,
            'rows'         => $rows,
        ];
    }

    /** The salesperson themselves, and whoever oversees them (admin, HR, their manager), may comment on a goal's week. */
    public static function canNote(User $user, NinetyDayGoal $goal): bool
    {
        $scope = self::visibleUserIds($user);

        return $scope === null || in_array((int) $goal->user_id, array_map('intval', $scope), true);
    }

    /** Add a comment to a week. The week must already exist (it does from its Monday onward). */
    public static function addNote(User $user, NinetyDayGoal $goal, int $week, string $body, ?Carbon $now = null): ?NinetyDayGoalNote
    {
        $body = trim($body);
        $checkin = NinetyDayGoalCheckin::where('goal_id', $goal->id)->where('week_no', $week)->first();
        if ($body === '' || !$checkin || !self::canNote($user, $goal)) {
            return null;
        }

        return NinetyDayGoalNote::create(['tenant_id' => $goal->tenant_id, 'checkin_id' => $checkin->id, 'goal_id' => $goal->id,
            'week_no' => $week, 'user_id' => $user->id, 'body' => $body, 'created_at' => $now ?: now(), 'updated_at' => $now ?: now()]);
    }

    /**
     * Every comment in scope, newest first: the ones written with a weekly status and the ones
     * added afterwards by the salesperson or an admin. Filters: user_id, status, late.
     * Each item: kind (status|note), goal, week_no, due_on, status, is_late, text, by (User), at.
     */
    public static function comments(?array $scope, array $f = [], int $limit = 300): Collection
    {
        $ofPerson = function ($g) use ($scope, $f) {
            if ($scope !== null) {
                $g->whereIn('user_id', $scope);
            }
            if (!empty($f['user_id'])) {
                $g->where('user_id', (int) $f['user_id']);
            }
        };

        $status = NinetyDayGoalCheckin::with(['goal.user.department'])
            ->whereNotNull('comment')->where('comment', '!=', '')
            ->whereHas('goal', $ofPerson)
            ->when(!empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['late']), fn ($q) => $q->where('is_late', true))
            ->orderByDesc('submitted_at')->limit($limit)->get()
            ->map(fn ($c) => (object) ['kind' => 'status', 'goal' => $c->goal, 'week_no' => $c->week_no, 'due_on' => $c->due_on,
                'status' => $c->status, 'is_late' => $c->is_late, 'text' => $c->comment, 'by' => optional($c->goal)->user, 'at' => $c->submitted_at]);

        $notes = NinetyDayGoalNote::with(['goal.user.department', 'author', 'checkin'])
            ->whereHas('goal', $ofPerson)
            ->when(!empty($f['status']), fn ($q) => $q->whereHas('checkin', fn ($c) => $c->where('status', $f['status'])))
            ->when(!empty($f['late']), fn ($q) => $q->whereHas('checkin', fn ($c) => $c->where('is_late', true)))
            ->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($n) => (object) ['kind' => 'note', 'goal' => $n->goal, 'week_no' => $n->week_no, 'due_on' => optional($n->checkin)->due_on,
                'status' => optional($n->checkin)->status, 'is_late' => (bool) optional($n->checkin)->is_late, 'text' => $n->body, 'by' => $n->author, 'at' => $n->created_at]);

        return $status->concat($notes)->sortByDesc(fn ($i) => optional($i->at)->timestamp ?? 0)->take($limit)->values();
    }
    // ── the customer picker ──────────────────────────────────────────────

    /**
     * The person's own Sales Pipeline AND Sales Report customers (as salesperson or
     * extra rep). This is the only list goals can be built from: nobody types a
     * customer in, and nobody can pick another person's.
     */
    public static function pipelineCustomers(User $user): Collection
    {
        return SalesPipeline::query()
            ->where(function ($q) use ($user) {
                $q->where('salesperson_id', $user->id)
                  ->orWhereExists(fn ($s) => $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                      ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')->where('er.user_id', $user->id));
            })
            ->orderBy('party_name')
            ->get(['id', 'party_name', 'product', 'total_business_potential', 'is_locked'])
            ->map(fn ($p) => [
                'id'        => $p->id,
                'customer'  => $p->party_name,
                'product'   => (string) $p->product,
                'brand'     => self::brandOf((string) $p->product),
                'potential' => (float) $p->total_business_potential,
                'source'    => $p->is_locked ? 'Sales Report' : 'Pipeline',
            ]);
    }

    public static function brandOf(string $product): string
    {
        $p = strtolower($product);
        foreach (self::BRANDS as $brand => $spellings) {
            foreach ($spellings as $s) {
                if ($s !== '' && str_contains($p, $s)) {
                    return $brand;
                }
            }
        }

        return '';
    }
}
