<?php

namespace App\Services;

use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayGoalNote;
use App\Models\NinetyDayProgram;
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
 * Sales Pipeline (customer, product, brand, goal, target for the round in lakh) and
 * reports a status on every Monday until the round ends — 12 weeks (shown W1 to
 * W12; week_no is 0-based internally) for the usual round, but an admin-created program can run a different
 * length, and its rounds stretch or shrink to match (see weeksOf()). Then the
 * round closes and a new one starts the following Monday.
 *
 * A Monday that has passed without a status is late: the person can still fill
 * it in, but must choose a status AND write a comment saying why. Each Monday
 * also puts one task on their list, which completes itself when they submit.
 */
class NinetyDayGoalService
{
    /** The choices on the sheet's drop-down, in its order. */
    public const STATUSES = ['On Track', 'Off Track', 'Trial Order', 'Regular Order', 'No Report', 'Dropped', 'Transferred'];

    /**
     * The statuses that claim an order. A claim only counts once the Sales Report shows a sale
     * for that customer during the round (see orderCheck()) - the amount is never typed here.
     */
    public const ORDER_STATUSES = ['Trial Order', 'Regular Order'];

    /** Chip colours [background, text], close to the sheet's so the team recognises them at a glance. */
    /**
     * [background, text]. A deliberate progression, not arbitrary colours: HRX teal for the
     * healthy/converting statuses (deepest teal is the best outcome, Regular Order), amber for
     * "still deciding" (Trial Order), a warmer orange for "needs attention" (Off Track), grey
     * for silence, red only for the one status that is actually a loss (Dropped), and a neutral
     * slate for Transferred, which is administrative, not a failure.
     */
    public const STATUS_COLORS = [
        'On Track'    => ['#007a7a', '#ffffff'],
        'Off Track'   => ['#c2540c', '#ffffff'],
        'Trial Order' => ['#f5a623', '#3a2a04'],
        'Regular Order' => ['#0b5b48', '#ffffff'],
        'No Report'   => ['#64748b', '#ffffff'],
        'Dropped'     => ['#b42318', '#ffffff'],
        'Transferred' => ['#cbd5e1', '#334155'],
    ];

    /** 12 weeks. Shown as W1..W12; week_no stays 0-based internally (0..11). */
    public const WEEKS = 12;

    /** Days from a round's first Monday to its last day (12 weeks). */
    public const ROUND_SPAN_DAYS = 84;

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

    /**
     * Statuses that mean someone no longer works here or can't use the app. notTerminated()
     * only excluded "terminated", so relieved/retired/inactive people still got rounds and tasks.
     */
    public const NOT_CURRENT_STATUSES = ['relieved', 'terminated', 'retired', 'inactive', 'suspended', 'blocked', 'rejected', 'deleted', 'probation_failed'];

    /** Constrain a users query to current employees. */
    public static function current($query)
    {
        return $query->where(fn ($q) => $q->whereNull('users.status')->orWhereNotIn('users.status', self::NOT_CURRENT_STATUSES));
    }

    /** Current employees in a department with "Sales" in its name. */
    public static function salesStaff(): Collection
    {
        return self::current(User::query())->with('department')
            ->whereHas('department', fn ($d) => $d->where('name', 'like', '%sales%'))
            ->orderBy('first_name')->get();
    }

    /**
     * Current employees who may be PUT INTO a program from the create / edit
     * picker: the Sales departments PLUS Export. Deliberately wider than
     * salesStaff(), which stays Sales-only because it also governs the daily
     * auto-enrolment and the self-start-on-visit -- Export people are only ever
     * added by hand, never auto-enrolled.
     */
    public static function programmableStaff(): Collection
    {
        return self::current(User::query())->with('department')
            ->whereHas('department', fn ($d) => $d->where('name', 'like', '%sales%')->orWhere('name', 'like', '%export%'))
            ->orderBy('first_name')->get();
    }

    public static function isSalesStaff(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return str_contains(strtolower((string) optional($user->department)->name), 'sales');
    }

    /**
     * Someone who already has a 90-Day round of their own -- e.g. an Export
     * person an admin added to a program. The page is theirs to open and fill
     * even though they are not Sales, but this never starts a round, so a
     * non-Sales employee who was never added stays out.
     */
    public static function isParticipant(?User $user): bool
    {
        return $user && NinetyDayRound::where('user_id', $user->id)->exists();
    }

    /**
     * Who sees every salesperson's goals: anyone who may manage them, plus anyone
     * given goals.view -- the read-only grant. Use this for what a page SHOWS.
     */
    public static function seesEveryone(?User $user): bool
    {
        return $user && (self::managesEveryone($user) || $user->can('goals.view'));
    }

    /**
     * Who may CHANGE anyone's goals: create programs, send reminders, edit or
     * remove goals, remove comments, act for a named person. Admin, HR, or
     * goals.manage / scorecards.manage -- never goals.view, which exists so
     * someone can see everything and alter nothing. Use this for what a page
     * lets you DO.
     */
    public static function managesEveryone(?User $user): bool
    {
        return $user && ($user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])
            || $user->can('goals.manage') || $user->can('scorecards.manage'));
    }

    public static function canOpenPage(?User $user): bool
    {
        return self::seesEveryone($user) || self::isSalesStaff($user) || self::isParticipant($user) || ($user && $user->hasRole(['manager', 'Manager']));
    }

    /** The ids whose goals this viewer may see: null = everyone. */
    public static function visibleUserIds(User $viewer): ?array
    {
        if (self::seesEveryone($viewer)) {
            return null;
        }
        if ($viewer->hasRole(['manager', 'Manager'])) {
            return self::current(User::where('reporting_to_id', $viewer->id))->pluck('id')->push($viewer->id)->all();
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

    /**
     * Run $fn while holding a row lock on the user, so two requests (a page load and the
     * Monday job, a double-click...) can't both see "no active round" and each start one.
     */
    public static function lockedFor(User $user, callable $fn)
    {
        return DB::transaction(function () use ($user, $fn) {
            User::whereKey($user->id)->lockForUpdate()->first();

            return $fn();
        });
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

    /**
     * Admin creates a named, dated 90-Day Goals program and invites a set of people into it —
     * several of these can run at once, each with its own start/end date and its own people,
     * quite separate from anyone's ordinary self-starting round. Anyone already mid-round is
     * skipped (their existing round stays exactly as it is) rather than silently replaced.
     * Each enrolled person gets a round dated exactly as the admin chose, an "add your
     * customers" task due 3 days from the start, and a notification + email — sent by the
     * caller via notify(new ProgramInvite(...)) for each entry in 'enrolled', same pattern as
     * bulk task assignment: after the response, one try/catch per person.
     */
    public static function createProgram(User $admin, string $name, Carbon $startsOn, Carbon $endsOn, array $userIds): array
    {
        $startsOn = $startsOn->copy()->startOfDay();
        $endsOn = $endsOn->copy()->startOfDay();
        // Weeks are Mondays: the weekly task, "late" and every week column count from starts_on,
        // so a Tuesday start made each week due the day after the Monday job ran (no task ever).
        // Snap a non-Monday start back to that week's Monday - the same rule a self-started round
        // uses - and keep the chosen length. Nobody is late for it: a customer added mid-week
        // starts from the next Monday (firstWeek()).
        if (!$startsOn->isMonday()) {
            $shift = self::mondayOf($startsOn)->diffInDays($startsOn);
            $startsOn->subDays($shift);
            $endsOn->subDays($shift);
        }

        $targets = self::programmableStaff()->whereIn('id', $userIds)->values();
        $enrolled = [];
        $skipped = [];
        $program = null;

        DB::transaction(function () use ($targets, $admin, $startsOn, $endsOn, $name, &$program, &$enrolled, &$skipped) {
            $program = NinetyDayProgram::create([
                'tenant_id' => $admin->tenant_id, 'name' => trim($name),
                'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(),
                'created_by_id' => $admin->id,
            ]);

            foreach ($targets as $user) {
                User::whereKey($user->id)->lockForUpdate()->first();
                $active = self::activeRound($user);
                if ($active) {
                    // The Monday job starts a round for every salesperson, so almost everyone has
                    // one. An empty self-started round (no program, no customers yet) is replaced
                    // by the program's; a program round or one with customers is left alone.
                    $inUse = $active->program_id || NinetyDayGoal::where('round_id', $active->id)->exists();
                    if ($inUse) {
                        $skipped[] = $user;
                        continue;
                    }
                    Task::where('user_id', $user->id)->where('title', self::addCustomersTitle($active))
                        ->whereNotIn('status', Task::CLOSED_STATUSES)->delete();
                    NinetyDayGoalCheckin::where('round_id', $active->id)->delete();
                    $active->delete();
                }

                $round = NinetyDayRound::create([
                    'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'program_id' => $program->id,
                    'round_no'  => (int) NinetyDayRound::where('user_id', $user->id)->max('round_no') + 1,
                    'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(),
                    'status'    => 'active',
                ]);

                $task = Task::create([
                    'user_id' => $user->id, 'assigned_by_id' => $admin->id, 'created_by_id' => $admin->id, 'updated_by_id' => $admin->id,
                    'title'   => self::addCustomersTitle($round),
                    'description' => 'You have been added to the "' . $name . '" 90-Day Goals program. Open 90-Day Goals and add your target customers within 3 days.',
                    'type' => 'open', 'status' => 'new', 'priority' => 'medium',
                    // 3 days from the start, or from today if the start was snapped back into the past.
                    'for_date' => $startsOn, 'due_date' => $startsOn->copy()->max(Carbon::today())->addDays(3)->toDateString(), 'tenant_id' => $user->tenant_id,
                ]);

                $enrolled[] = ['user' => $user, 'round' => $round, 'task' => $task];
            }

            // Nobody could be enrolled: don't leave an empty program behind.
            if (!$enrolled) {
                $program->delete();
                $program = null;
            }
        });

        return ['program' => $program, 'enrolled' => $enrolled, 'skipped' => $skipped, 'starts_on' => $startsOn, 'ends_on' => $endsOn];
    }

    /**
     * When this round stops accepting new target customers.
     *
     * Counted from enrolment, not from the round's start date: the round can be back-dated to
     * its Monday, and someone enrolled on a Wednesday would otherwise find the window already
     * gone before they were told about it.
     *
     * Rounds created before config('ninety_day.window_rule_from') are measured from that moment
     * instead, so introducing the rule does not retroactively shut a window on people who were
     * enrolled under the old one.
     */
    public static function customerWindowClosesAt(NinetyDayRound $round): Carbon
    {
        // Admin/HR reopened (or set) the window from Program Details: that time wins.
        if (!empty($round->customer_window_until)) {
            return Carbon::parse($round->customer_window_until);
        }

        $from = Carbon::parse($round->created_at ?: $round->starts_on);
        $ruleFrom = config('ninety_day.window_rule_from');

        if ($ruleFrom && $from->lt(Carbon::parse($ruleFrom))) {
            $from = Carbon::parse($ruleFrom);
        }

        return $from->copy()->addHours((int) config('ninety_day.customer_window_hours', 48));
    }

    /** Whether this round can still take new customers. Admin/HR bypass this to fix mistakes. */
    public static function customerWindowOpen(NinetyDayRound $round, ?Carbon $now = null): bool
    {
        return ($now ?: Carbon::now())->lt(self::customerWindowClosesAt($round));
    }

    /** Customers on this round that still count - a closed one is no longer part of the set. */
    public static function customerCount(NinetyDayRound $round): int
    {
        return NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->count();
    }

    /**
     * A round counts once it holds at least the minimum number of customers. Judged on the
     * round's running total, so adding four now and six later is fine.
     */
    public static function meetsMinimum(NinetyDayRound $round): bool
    {
        return self::customerCount($round) >= (int) config('ninety_day.min_customers', 10);
    }

    /** Everything a page needs to show the window and the minimum, in one call. */
    public static function windowState(NinetyDayRound $round, ?Carbon $now = null): array
    {
        $now = $now ?: Carbon::now();
        $closesAt = self::customerWindowClosesAt($round);
        $count = self::customerCount($round);
        $min = (int) config('ninety_day.min_customers', 10);

        return [
            'closes_at'   => $closesAt,
            'open'        => $now->lt($closesAt),
            // floor, and cast: Carbon returns a float here, so the banner printed
            // "47.9360405225 hours left". Rounding down is also the honest direction - with
            // 47.9 hours to go you have 47 whole hours, not 48.
            'hours_left'  => $now->lt($closesAt) ? (int) floor($now->diffInHours($closesAt)) : 0,
            'count'       => $count,
            'min'         => $min,
            'short_by'    => max(0, $min - $count),
            'meets_min'   => $count >= $min,
            'rag'         => self::customerRag($count),
            'reminder_sent_at' => $round->completion_reminder_sent_at,
        ];
    }

    /**
     * The add-customer progress light: green at/above the minimum, amber from
     * config('ninety_day.customers_amber_from') up to it, red below that.
     * Returns [level, label, dot colour, soft background, text colour].
     */
    public static function customerRag(int $count): array
    {
        $min = (int) config('ninety_day.min_customers', 10);
        $amber = (int) config('ninety_day.customers_amber_from', 7);
        if ($count >= $min) {
            return ['level' => 'green', 'label' => 'On target', 'dot' => '#16a34a', 'bg' => '#dcfce7', 'fg' => '#166534'];
        }
        if ($count >= $amber) {
            return ['level' => 'amber', 'label' => 'Almost there', 'dot' => '#d97706', 'bg' => '#fef3c7', 'fg' => '#92400e'];
        }
        return ['level' => 'red', 'label' => 'Behind', 'dot' => '#dc2626', 'bg' => '#fee2e2', 'fg' => '#991b1b'];
    }

    /** The user's active round, starting one this week if they have none. */
    public static function roundFor(User $user, ?Carbon $today = null): NinetyDayRound
    {
        return self::activeRound($user)
            ?? self::lockedFor($user, fn () => self::activeRound($user) ?? self::startRound($user, $today ?: Carbon::today()));
    }

    /**
     * How many Mondays a round actually has, from its own starts_on/ends_on — not the fixed
     * 12-week assumption. A self-started round is always exactly self::WEEKS (12), since
     * startRound() always uses ROUND_SPAN_DAYS; an admin-created program can run longer or
     * shorter, and its rounds must stretch or shrink the same way.
     */
    public static function weeksOf(NinetyDayRound $round): int
    {
        $span = Carbon::parse($round->starts_on)->startOfDay()->diffInDays(Carbon::parse($round->ends_on)->startOfDay());

        return max(1, (int) ceil($span / 7));
    }

    /** Which week a date falls in for the round, clamped to that round's own real span. */
    public static function weekOf(NinetyDayRound $round, ?Carbon $today = null): int
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();
        $days = Carbon::parse($round->starts_on)->startOfDay()->diffInDays($today, false);

        return max(0, min(self::weeksOf($round) - 1, intdiv((int) $days, 7)));
    }

    /**
     * The months ("Y-m") whose sales count for this round: every month whose 16th falls inside it.
     * Sales are only known per month, and a 90-day round touches four calendar months, so
     * counting every month it touched overstated achievement against a three-month target and
     * counted a boundary month in two consecutive rounds. The month belongs to the round that
     * holds most of it (its 16th): each month counts exactly once, and a round starting
     * 28 Sep no longer takes all of September (sales booked before it began) for 3 days.
     */
    public static function roundMonths(NinetyDayRound $round): array
    {
        $starts = Carbon::parse($round->starts_on)->startOfDay();
        $ends = Carbon::parse($round->ends_on)->startOfDay();
        $out = [];
        for ($m = $starts->copy()->startOfMonth(); $m->lte($ends); $m->addMonth()) {
            $mid = $m->copy()->day(16);
            if ($mid->gte($starts) && $mid->lte($ends)) {
                $out[] = $m->format('Y-m');
            }
        }

        // A short program that misses every 16th: use the month it starts in.
        return $out ?: [$starts->format('Y-m')];
    }

    public static function dueOn(NinetyDayRound $round, int $week): Carbon
    {
        return Carbon::parse($round->starts_on)->startOfDay()->addDays(7 * $week);
    }

    /** Monday-by-Monday dates for the column headers, for this round's own real span. */
    public static function weekDates(NinetyDayRound $round): array
    {
        return array_map(fn ($w) => self::dueOn($round, $w), range(0, self::weeksOf($round) - 1));
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

    /**
     * Whether $due's month is this goal's first-ever real sale within the round, or a later
     * (repeat/upsell) one — sales before the round's own start never count here, same rule the
     * achievement math already follows. Returns [hasSaleThisMonth, isFirst].
     */
    private static function orderSignal(NinetyDayRound $round, array $salesByMonth, Carbon $due): array
    {
        $hasSaleThisMonth = ($salesByMonth[$due->format('Y-m')] ?? 0) > 0;
        if (!$hasSaleThisMonth) {
            return [false, false];
        }
        $monthsWithSaleBefore = 0;
        $from = Carbon::parse($round->starts_on)->startOfMonth();
        for ($m = $from->copy(); $m->lt($due->copy()->startOfMonth()); $m->addMonth()) {
            if (($salesByMonth[$m->format('Y-m')] ?? 0) > 0) {
                $monthsWithSaleBefore++;
            }
        }

        return [true, $monthsWithSaleBefore === 0];
    }

    /**
     * Make sure every open goal has a check-in row for each Monday up to today. Status is never
     * typed in here by a person — a Monday whose month shows a real Sales Report sale for the
     * first time auto-fills straight to "Regular Order"; a later month's sale for a goal that
     * already had one is a possible upsell, so it is flagged (needs_upsell_confirm) instead of
     * silently assumed, and waits for confirmUpsell().
     */
    public static function ensureCheckins(NinetyDayRound $round, ?Carbon $today = null): int
    {
        $current = self::weekOf($round, $today);
        $made = 0;
        $goals = NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->get();
        if ($goals->isEmpty()) {
            return 0;
        }

        // One query each for every goal's sales and existing rows (this runs on page views too).
        $salesByPipeline = [];
        $pids = $goals->pluck('sales_pipeline_id')->filter()->unique()->values()->all();
        if ($pids) {
            foreach (DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', $pids)
                ->selectRaw('sales_pipeline_id, month_year, SUM(sale_amount) s')->groupBy('sales_pipeline_id', 'month_year')->get() as $r) {
                $salesByPipeline[$r->sales_pipeline_id][$r->month_year] = (float) $r->s;
            }
        }
        $existing = NinetyDayGoalCheckin::whereIn('goal_id', $goals->pluck('id'))->get()->groupBy('goal_id');
        $autoFill = fn () => [
            'status' => 'Regular Order', 'comment' => 'Auto-detected from the Sales Report — a real order was found for this month.',
            'submitted_at' => Carbon::now(), 'is_late' => false, 'auto_filled' => true,
        ];

        foreach ($goals as $goal) {
            $rows = ($existing[$goal->id] ?? collect())->keyBy('week_no');
            $sales = $salesByPipeline[$goal->sales_pipeline_id] ?? [];
            for ($w = self::firstWeek($goal, $round); $w <= $current; $w++) {
                $due = self::dueOn($round, $w);
                [$hasSale, $isFirst] = self::orderSignal($round, $sales, $due);
                $row = $rows->get($w);

                if ($row) {
                    // Sales Report entries arrive late (until ~the 5th of next month), so a week
                    // that is still pending re-checks for a sale instead of staying frozen at
                    // whatever was known the day its row was made. A declined upsell stays declined.
                    if ($row->status === null && $hasSale) {
                        if ($isFirst) {
                            $row->update($autoFill());
                        } elseif (!$row->needs_upsell_confirm && $row->upsell_confirmed === null) {
                            $row->update(['needs_upsell_confirm' => true]);
                        }
                    }
                    continue;
                }

                $attrs = ['goal_id' => $goal->id, 'week_no' => $w, 'round_id' => $round->id, 'tenant_id' => $goal->tenant_id, 'due_on' => $due->toDateString()];
                if ($hasSale && $isFirst) {
                    $attrs += $autoFill();
                } elseif ($hasSale) {
                    $attrs += ['needs_upsell_confirm' => true];
                }
                try {
                    NinetyDayGoalCheckin::create($attrs);
                    $made++;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Another request made this week's row a moment ago - fine.
                }
            }
        }

        return $made;
    }

    /**
     * A repeat month's Sales Report sale, for a goal that already had an earlier one, is never
     * assumed to be an upsell — someone (the salesperson, or admin/HR on their behalf) confirms
     * it first. Confirming sets the status; declining just clears the flag so the week goes back
     * to being an ordinary pending status, filled in by hand as usual.
     */
    public static function confirmUpsell(NinetyDayGoalCheckin $checkin, User $actor, bool $isUpsell, ?Carbon $now = null): void
    {
        $now = $now ?: Carbon::now();
        if ($isUpsell) {
            $checkin->update([
                'status'   => 'Regular Order',
                'comment'  => trim(($checkin->comment ? $checkin->comment . ' ' : '') . 'Confirmed as a repeat/upsell order from the Sales Report.'),
                'submitted_at'    => $checkin->submitted_at ?: $now,
                'submitted_by_id' => $checkin->submitted_by_id ?: $actor->id,
                'auto_filled'          => true,
                'needs_upsell_confirm' => false,
                'upsell_confirmed'     => true,
            ]);
            // Same follow-through as any other status: legacy week column, and the weekly
            // task closes if this was the last one missing.
            $goal = $checkin->goal;
            if ($goal) {
                self::writeLegacyWeek($goal, (int) $checkin->week_no, 'Regular Order');
                $round = $checkin->round_id ? NinetyDayRound::find($checkin->round_id) : null;
                if ($round && $goal->user) {
                    self::completeTaskIfDone($goal->user, $round, (int) $checkin->week_no);
                }
            }
        } else {
            $checkin->update(['needs_upsell_confirm' => false, 'upsell_confirmed' => false]);
        }
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
     * Submit or edit one Monday's statuses. $entries is [goal_id => ['status' => ..., 'comment' => ...]].
     * A Monday already past is late and needs a comment. $actor is who is doing this — the goal's own
     * owner, or an admin/HR/manager editing on their behalf; $target is whose round it is (defaults to
     * $actor). Editing an existing status keeps its original submitted_at/submitted_by/is_late — only
     * the status and comment themselves change, so the record of when it was first reported is never
     * rewritten. Returns [saved, errors[]].
     */
    public static function submit(User $target, int $week, array $entries, ?Carbon $now = null, ?User $actor = null): array
    {
        $actor = $actor ?: $target;
        $user = $target;
        $now = $now ?: Carbon::now();
        $today = $now->copy()->startOfDay();
        $round = self::activeRound($user);
        $errors = [];
        $notes = [];
        $saved = 0;

        if ($actor->id !== $target->id && !self::managesEveryone($actor)) {
            return [0, ['You cannot fill this in for someone else.'], []];
        }
        if (!$round) {
            return [0, ['You have no active 90-day round.'], []];
        }
        if ($week < 0 || $week >= self::weeksOf($round)) {
            return [0, ['That week does not exist.'], []];
        }
        $due = self::dueOn($round, $week);
        if ($due->gt($today)) {
            return [0, ['Week ' . $week . ' is not due until ' . $due->format('D d M') . '.'], []];
        }
        $late = $today->gt($due);

        $goals = NinetyDayGoal::where('round_id', $round->id)->where('user_id', $user->id)->whereNull('closed_at')
            ->whereIn('id', array_keys($entries))->get()->keyBy('id');

        foreach ($entries as $goalId => $e) {
            $goal = $goals->get($goalId);
            $status = trim((string) ($e['status'] ?? ''));
            $comment = trim((string) ($e['comment'] ?? ''));
            // Both optional, both real free-typed plans — never required, never validated
            // against a fixed list, same as the comment beside them.
            $nextAction = trim((string) ($e['next_action'] ?? ''));
            $nextFollowUp = $e['next_follow_up_date'] ?? null;
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
            $existing = NinetyDayGoalCheckin::where('goal_id', $goal->id)->where('week_no', $week)->first();
            $isFirst = !$existing || $existing->status === null || $existing->status === '';

            DB::transaction(function () use ($goal, $round, $week, $due, $status, $comment, $nextAction, $nextFollowUp, $late, $user, $actor, $now, $existing, $isFirst, &$saved) {
                NinetyDayGoalCheckin::updateOrCreate(
                    ['goal_id' => $goal->id, 'week_no' => $week],
                    [
                        'round_id' => $round->id, 'tenant_id' => $goal->tenant_id, 'due_on' => $due->toDateString(),
                        'status' => $status, 'comment' => $comment !== '' ? $comment : null,
                        'next_action' => $nextAction !== '' ? $nextAction : null,
                        // The weekly table no longer has a date box (2026-10-01): keep a date already on
                        // record unless a new one is sent, so re-saving a week never wipes it.
                        'next_follow_up_date' => $nextFollowUp ?: optional($existing)->next_follow_up_date,
                        // First time: this is when/who/how-late it was actually reported, and that never changes
                        // again. Editing later only updates the content and appends who touched it and when.
                        'submitted_at'   => $isFirst ? $now : $existing->submitted_at,
                        // Who actually entered it (an admin on someone's behalf is recorded as the admin).
                        'submitted_by_id' => $isFirst ? $actor->id : $existing->submitted_by_id,
                        'is_late'        => $isFirst ? $late : $existing->is_late,
                        'edited_at'      => $isFirst ? null : $now,
                        'edited_by_id'   => $isFirst ? null : $actor->id,
                    ]
                );
                self::writeLegacyWeek($goal, $week, $status);
                $saved++;
            });

            if (in_array($status, self::ORDER_STATUSES, true)) {
                if (self::moveToSalesReport($goal)) {
                    $notes[] = "{$goal->client_name} moved from your Sales Pipeline to the Sales Report.";
                }
                if (!self::orderCheck($goal)['confirmed']) {
                    $notes[] = "{$goal->client_name}: order noted - it counts once the sale appears on the Sales Report.";
                }
            }
        }

        self::completeTaskIfDone($user, $round, $week);

        return [$saved, $errors, $notes];
    }

    /**
     * An order marked on a customer that is still in the Sales Pipeline moves it to the Sales
     * Report - the same thing setting the Pipeline's own stage to "Order" does
     * (SalesPipelineController::update: status_stage Order => is_locked). Returns whether it moved.
     */
    public static function moveToSalesReport(NinetyDayGoal $goal): bool
    {
        if (!$goal->sales_pipeline_id) {
            return false;
        }
        $p = \App\Models\SalesPipeline::withoutGlobalScopes()->find($goal->sales_pipeline_id);
        if (!$p || $p->is_locked) {
            return false;   // gone, or already on the Sales Report
        }
        $p->update(['status_stage' => 'Order', 'is_locked' => true]);

        return true;
    }

    /**
     * Whether a customer's order is backed by the Sales Report: any sale for its pipeline row in
     * the round's months up to today. Accepts a goal or a dashboard() row (which already carries
     * achieved). ['claimed' => order status?, 'confirmed' => sale found?, 'amount' => lakh].
     */
    public static function orderCheck($goalOrRow): array
    {
        if ($goalOrRow instanceof NinetyDayGoal) {
            $goal = $goalOrRow;
            $status = optional($goal->checkins()->whereNotNull('status')->orderByDesc('week_no')->first())->status;
            $amount = 0.0;
            if ($goal->sales_pipeline_id && $goal->round) {
                $months = array_filter(self::roundMonths($goal->round), fn ($ym) => $ym <= Carbon::today()->format('Y-m'));
                $amount = $months ? (float) DB::table('sales_pipeline_months')->where('sales_pipeline_id', $goal->sales_pipeline_id)
                    ->whereIn('month_year', $months)->sum('sale_amount') : 0.0;
            }
        } else {
            $status = $goalOrRow->status ?? null;
            $amount = isset($goalOrRow->achieved) ? (float) $goalOrRow->achieved : null;
        }
        $claimed = in_array($status, self::ORDER_STATUSES, true);

        return ['claimed' => $claimed, 'confirmed' => $claimed && $amount !== null && $amount > 0, 'amount' => (float) $amount, 'known' => $amount !== null];
    }

    /**
     * Mirror a week's status into the older w0..w12 columns the scorecard code still reads.
     * Only 13 exist; a program longer than 13 weeks keeps later weeks in the check-in rows
     * only (writing w13 was an "unknown column" error that rolled the whole submit back).
     */
    private static function writeLegacyWeek(NinetyDayGoal $goal, int $week, string $status): void
    {
        if ($week >= 0 && $week < self::WEEKS) {
            $goal->forceFill(["w{$week}" => $status])->save();
        }
    }

    /** Rows per page for the customer tables: 10 / 25 / 50 / 100, or 'all'. */
    public const PER_PAGE_CHOICES = [10, 25, 50, 100];

    public static function perPage($choice, int $default, int $total): int
    {
        if ($choice === 'all') {
            return max(1, $total);
        }

        return in_array((int) $choice, self::PER_PAGE_CHOICES, true) ? (int) $choice : $default;
    }

    // ── editing a program (Program Details > Manage program) ─────────────

    /**
     * Add people to an existing program, by the same rules as creating one: anyone in another
     * program or with customers in their current round is skipped; an empty self-started
     * round is replaced. Each person gets the add-customers task; the caller sends the invite.
     * Their 48-hour window to add customers runs from now, when they are told.
     */
    public static function addToProgram(NinetyDayProgram $program, User $admin, array $userIds): array
    {
        $targets = self::programmableStaff()->whereIn('id', $userIds)->values();
        $enrolled = [];
        $skipped = [];

        DB::transaction(function () use ($targets, $admin, $program, &$enrolled, &$skipped) {
            foreach ($targets as $user) {
                User::whereKey($user->id)->lockForUpdate()->first();
                $active = self::activeRound($user);
                if ($active) {
                    $inUse = $active->program_id || NinetyDayGoal::where('round_id', $active->id)->exists();
                    if ($inUse) {
                        $skipped[] = $user;
                        continue;
                    }
                    Task::where('user_id', $user->id)->where('title', self::addCustomersTitle($active))
                        ->whereNotIn('status', Task::CLOSED_STATUSES)->delete();
                    NinetyDayGoalCheckin::where('round_id', $active->id)->delete();
                    $active->delete();
                }

                $round = NinetyDayRound::create([
                    'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'program_id' => $program->id,
                    'round_no'  => (int) NinetyDayRound::where('user_id', $user->id)->max('round_no') + 1,
                    'starts_on' => $program->starts_on->toDateString(), 'ends_on' => $program->ends_on->toDateString(),
                    'status'    => 'active',
                ]);
                $task = Task::create([
                    'user_id' => $user->id, 'assigned_by_id' => $admin->id, 'created_by_id' => $admin->id, 'updated_by_id' => $admin->id,
                    'title'   => self::addCustomersTitle($round),
                    'description' => 'You have been added to the "' . $program->name . '" 90-Day Goals program. Open 90-Day Goals and add your target customers within 3 days.',
                    'type' => 'open', 'status' => 'new', 'priority' => 'medium',
                    'for_date' => Carbon::today(), 'due_date' => Carbon::today()->addDays(3)->toDateString(), 'tenant_id' => $user->tenant_id,
                ]);
                $enrolled[] = ['user' => $user, 'round' => $round, 'task' => $task];
            }
        });

        return ['enrolled' => $enrolled, 'skipped' => $skipped];
    }

    /**
     * Rename / re-date a program and every round in it. Weeks run Monday to Monday, so a new
     * start is snapped back to its Monday as on creation. The start can only move while nobody
     * has submitted a weekly status - moving it afterwards would shift every week under data
     * already entered. Returns an error message, or null when saved.
     */
    public static function updateProgram(NinetyDayProgram $program, string $name, Carbon $startsOn, Carbon $endsOn): ?string
    {
        $startsOn = $startsOn->copy()->startOfDay();
        $endsOn = $endsOn->copy()->startOfDay();
        if (!$startsOn->isMonday()) {
            $startsOn = self::mondayOf($startsOn);
        }
        if ($endsOn->lte($startsOn->copy()->addDays(6))) {
            return 'The program must run at least one week.';
        }
        if ($startsOn->diffInDays($endsOn) > 366) {
            return 'A program can run at most one year.';
        }

        $roundIds = NinetyDayRound::where('program_id', $program->id)->pluck('id');
        $startMoves = !$startsOn->equalTo($program->starts_on);
        if ($startMoves && NinetyDayGoalCheckin::whereIn('round_id', $roundIds)->whereNotNull('status')->exists()) {
            return 'The start date cannot change: weekly statuses have already been submitted against the current weeks. The end date and name can still change.';
        }

        DB::transaction(function () use ($program, $name, $startsOn, $endsOn, $roundIds, $startMoves) {
            if ($startMoves) {
                // Unsubmitted check-ins and open weekly tasks were laid out on the old weeks;
                // drop them and the next page load / Monday run lays them out again.
                foreach (NinetyDayRound::whereIn('id', $roundIds)->get() as $round) {
                    self::deleteRoundTasks($round, true);
                }
                NinetyDayGoalCheckin::whereIn('round_id', $roundIds)->whereNull('status')->delete();
            }
            $program->update(['name' => trim($name), 'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString()]);
            NinetyDayRound::whereIn('id', $roundIds)->update(['starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString()]);
        });

        return null;
    }

    /**
     * Reopen (or set) the add-customers window until $until, for the whole
     * program or some of it. Admin action only (called from
     * NinetyDayGoalController::programOpenWindow) - never automatic.
     *
     * Also covers self-started rounds (program_id NULL) that are still active:
     * someone who got a round just by opening the page, without ever being
     * added to the program, is a 90-Day participant too. The program-level
     * reopen used to skip them, so their window stayed shut (the default
     * start+48h) and they showed "closed" while everyone in the program was
     * open. Only ACTIVE self-started rounds - a closed one stays closed.
     */
    public static function openCustomerWindow(NinetyDayProgram $program, Carbon $until, ?array $userIds = null): int
    {
        return NinetyDayRound::where(function ($q) use ($program) {
                $q->where('program_id', $program->id)
                  ->orWhere(fn ($s) => $s->whereNull('program_id')->where('status', 'active'));
            })
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            // A new deadline re-arms the "finish your customers" reminder.
            ->update(['customer_window_until' => $until->toDateTimeString(), 'completion_reminder_sent_at' => null]);
    }

    /**
     * Rounds that should get the "add your customers" reminder now: still
     * active, window open but closing within config('ninety_day.reminder_lead_hours'),
     * under the minimum, and not already reminded for this deadline.
     */
    public static function roundsNeedingAddReminder(?Carbon $now = null): \Illuminate\Support\Collection
    {
        $now = $now ?: Carbon::now();
        $lead = (int) config('ninety_day.reminder_lead_hours', 12);

        return NinetyDayRound::with('user')->where('status', 'active')->whereNull('completion_reminder_sent_at')->get()
            ->filter(function (NinetyDayRound $r) use ($now, $lead) {
                if (!$r->user) {
                    return false;
                }
                $closes = self::customerWindowClosesAt($r);
                return $closes->isAfter($now)
                    && $closes->lte($now->copy()->addHours($lead))
                    && self::customerCount($r) < (int) config('ninety_day.min_customers', 10);
            })
            ->values();
    }

    /**
     * Take someone out of a program: their round with its customers, weekly statuses and notes,
     * the tasks it gave them and the program's notifications to them. They start an ordinary
     * round again from the next page load or Monday run, like anyone without one.
     */
    public static function removeFromProgram(NinetyDayProgram $program, int $userId): array
    {
        $counts = ['rounds' => 0, 'customers' => 0, 'statuses' => 0, 'tasks' => 0, 'notifications' => 0];
        DB::transaction(function () use ($program, $userId, &$counts) {
            foreach (NinetyDayRound::where('program_id', $program->id)->where('user_id', $userId)->get() as $round) {
                $counts = self::sumCounts($counts, self::deleteRound($round));
            }
            $counts['notifications'] += self::deleteProgramNotifications($program, $userId);
        });

        return $counts;
    }

    /** Delete a program and everything it created. */
    public static function deleteProgram(NinetyDayProgram $program): array
    {
        $counts = ['rounds' => 0, 'customers' => 0, 'statuses' => 0, 'tasks' => 0, 'notifications' => 0];
        DB::transaction(function () use ($program, &$counts) {
            foreach (NinetyDayRound::where('program_id', $program->id)->get() as $round) {
                $counts = self::sumCounts($counts, self::deleteRound($round));
            }
            $counts['notifications'] += self::deleteProgramNotifications($program, null);
            $program->delete();
        });

        return $counts;
    }

    /** What deleting the program would remove - for the confirmation. */
    public static function programFootprint(NinetyDayProgram $program): array
    {
        $roundIds = NinetyDayRound::where('program_id', $program->id)->pluck('id');

        return [
            'participants'  => NinetyDayRound::whereIn('id', $roundIds)->distinct()->count('user_id'),
            'customers'     => NinetyDayGoal::whereIn('round_id', $roundIds)->count(),
            'statuses'      => NinetyDayGoalCheckin::whereIn('round_id', $roundIds)->whereNotNull('status')->count(),
            'notifications' => self::programNotifications($program, null)->count(),
        ];
    }

    private static function deleteRound(NinetyDayRound $round): array
    {
        $goalIds = NinetyDayGoal::where('round_id', $round->id)->pluck('id');
        $c = [
            'rounds'    => 1,
            'customers' => $goalIds->count(),
            'statuses'  => NinetyDayGoalCheckin::where('round_id', $round->id)->whereNotNull('status')->count(),
            'tasks'     => self::deleteRoundTasks($round, false),
            'notifications' => 0,
        ];
        NinetyDayGoalNote::whereIn('goal_id', $goalIds)->delete();
        NinetyDayGoalCheckin::where('round_id', $round->id)->delete();
        NinetyDayGoal::whereIn('id', $goalIds)->delete();
        $round->delete();

        return $c;
    }

    /** The add-customers task and every weekly task this round gave its owner. */
    private static function deleteRoundTasks(NinetyDayRound $round, bool $weeklyOpenOnly): int
    {
        $titles = [];
        for ($w = 0; $w < self::weeksOf($round); $w++) {
            $titles[] = self::weekTaskTitle($w, self::dueOn($round, $w));
        }
        $q = Task::where('user_id', $round->user_id);
        if ($weeklyOpenOnly) {
            $q->whereIn('title', $titles)->whereNotIn('status', Task::CLOSED_STATUSES);
        } else {
            $q->whereIn('title', array_merge($titles, [self::addCustomersTitle($round)]));
        }
        $n = 0;
        foreach ($q->get() as $task) {
            $task->delete();
            $n++;
        }

        return $n;
    }

    private static function programNotifications(NinetyDayProgram $program, ?int $userId)
    {
        return DB::table('notifications')
            ->whereIn('type', [\App\Notifications\NinetyDayGoal\ProgramInvite::class, \App\Notifications\NinetyDayGoal\WeekReminder::class])
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.program_id')) = ?", [(string) $program->id])
            ->when($userId, fn ($q) => $q->where('notifiable_id', $userId));
    }

    private static function deleteProgramNotifications(NinetyDayProgram $program, ?int $userId): int
    {
        return self::programNotifications($program, $userId)->delete();
    }

    private static function sumCounts(array $a, array $b): array
    {
        foreach ($b as $k => $v) {
            $a[$k] = ($a[$k] ?? 0) + $v;
        }

        return $a;
    }

    // ── the Monday task ──────────────────────────────────────────────────

    private static function weekTaskTitle(int $week, Carbon $due): string
    {
        // Title is 1-based for display (W1..W12); $week is the 0-based week_no.
        return '90-Day Goals: submit W' . ($week + 1) . ' status (due ' . $due->format('D d M') . ')';
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

    /**
     * A weekly or add-customers task is closed automatically once the underlying data is
     * actually done (see completeTaskIfDone / runWeekly). Someone ticking it complete by
     * hand, on My Tasks or Task Management, must be held to the same rule: this returns
     * why it cannot be closed yet, or null if it is one of ours and really is done (or it
     * is not one of ours at all, in which case the normal task rules apply).
     */
    public static function taskBlockReason(Task $task): ?string
    {
        $title = (string) $task->title;

        if (preg_match('/^90-Day Goals: submit W(\d+) status/', $title, $m)) {
            $display = (int) $m[1];       // title is 1-based (W1..W12)
            $week = $display - 1;         // week_no is 0-based
            $open = NinetyDayGoalCheckin::where('week_no', $week)->whereNull('status')
                ->whereHas('goal', fn ($g) => $g->where('user_id', $task->user_id)->whereNull('closed_at')
                    ->whereHas('round', fn ($r) => $r->where('status', 'active')))
                ->exists();

            return $open ? "Give a status for every customer for week W{$display} before marking this complete." : null;
        }

        if (str_starts_with($title, '90-Day Goals: add your target customers')) {
            $has = NinetyDayGoal::where('user_id', $task->user_id)->whereNull('closed_at')
                ->whereHas('round', fn ($r) => $r->where('status', 'active'))->exists();

            return $has ? null : 'Add at least one target customer on the 90-Day Goals page before marking this complete.';
        }

        return null;
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
        $out = ['closed' => 0, 'started' => 0, 'checkins' => 0, 'tasks' => 0, 'emails' => 0, 'errors' => 0];

        $close = function (NinetyDayRound $round) use (&$out) {
            NinetyDayGoal::where('round_id', $round->id)->whereNull('closed_at')->update(['closed_at' => now()]);
            $round->update(['status' => 'closed', 'closed_at' => now()]);
            $out['closed']++;
        };
        foreach (NinetyDayRound::where('status', 'active')->whereDate('ends_on', '<', $today->toDateString())->get() as $round) {
            $close($round);
        }
        // Someone who has left keeps no running round (it kept them in "current" lists).
        foreach (NinetyDayRound::where('status', 'active')
            ->whereHas('user', fn ($u) => $u->whereIn('status', self::NOT_CURRENT_STATUSES))->get() as $round) {
            $close($round);
        }

        foreach (self::salesStaff() as $user) {
            // One person's bad data must not stop everyone after them.
            try {
                $round = self::activeRound($user);
                if (!$round) {
                    $round = self::lockedFor($user, fn () => self::activeRound($user) ?? self::startRound($user, $today));
                    $out['started'] += $round->wasRecentlyCreated ? 1 : 0;
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
                    $task = self::ensureTask($user, self::weekTaskTitle($week, self::dueOn($round, $week)), self::dueOn($round, $week));
                    if ($task->wasRecentlyCreated) {
                        $out['tasks']++;
                        // When the week's task first appears (its Monday), send the same
                        // TaskAssigned notification the Task Management module sends on
                        // assignment — email + in-app + push. Only on creation, so the daily
                        // run never re-notifies; a mail failure must not stop the loop.
                        try {
                            $user->notify(new \App\Notifications\Task\TaskAssigned($task));
                            $out['emails']++;
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("90-Day weekly task email failed for user {$user->id}: " . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                $out['errors']++;
                \Illuminate\Support\Facades\Log::error("90-Day Goals weekly run failed for user {$user->id}: " . $e->getMessage());
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
     * the round (lakh), against the customer's target for the whole round. The target is
     * stored in monthly_potential (the column's old name) and is NOT multiplied by months.
     * Filters: round (current|all), user_id, department_id, brand.
     */
    public static function dashboard(?array $scope, array $f = [], ?Carbon $today = null): array
    {
        $today = ($today ?: Carbon::today())->copy()->startOfDay();

        // whereHas('user'): a goal whose owner was removed (soft-deleted) had a null user and
        // crashed every admin page that reads $row->user->id.
        $q = NinetyDayGoal::with(['user.department', 'round.program', 'checkins.notes.author'])->whereNotNull('round_id')->whereHas('user');
        if ($scope !== null) {
            $q->whereIn('user_id', $scope);
        }
        if (!empty($f['user_id'])) {
            $q->where('user_id', (int) $f['user_id']);
        }
        if (!empty($f['department_id'])) {
            $q->whereHas('user', fn ($u) => $u->where('department_id', (int) $f['department_id']));
        }
        if (!empty($f['round_id'])) {
            $q->where('round_id', (int) $f['round_id']);
        } elseif (($f['round'] ?? 'current') === 'current') {
            $q->whereHas('round', fn ($r) => $r->where('status', 'active'));
        } elseif (($f['round'] ?? '') === 'closed') {
            $q->whereHas('round', fn ($r) => $r->where('status', 'closed'));
        }
        if (!empty($f['program_id'])) {
            $q->whereHas('round', fn ($r) => $r->where('program_id', (int) $f['program_id']));
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
            $months = self::roundMonths($g->round);
            $achieved = 0.0;
            $monthsWithSale = 0;
            foreach ($months as $ym) {
                if (Carbon::createFromFormat('Y-m-d', $ym . '-01')->gt($today)) {
                    break;   // months that haven't started yet
                }
                $inMonth = $sales[$g->sales_pipeline_id][$ym] ?? 0.0;
                $achieved += $inMonth;
                if ($inMonth > 0) {
                    $monthsWithSale++;
                }
            }
            $potential = (float) $g->monthly_potential;
            $target = $potential;   // the overall target for the round, as typed

            return (object) [
                'goal'      => $g,
                'user'      => $g->user,
                'brand'     => $g->brand ?: 'Other',
                'program'   => $g->round->program,
                'potential' => $potential,
                'target'    => $target,
                'achieved'  => round($achieved, 2),
                'pct'       => $target > 0 ? round($achieved / $target * 100, 1) : null,
                'status'    => self::latestStatus($g),
                // "Repeat" here means a real order landed in more than one calendar month of the
                // round — the closest signal available without a distinct per-order ledger.
                'repeat'    => $monthsWithSale >= 2,
            ];
        });

        $sum = fn ($c) => [
            'goals'     => $c->count(),
            'people'    => $c->pluck('user.id')->unique()->count(),
            'potential' => round($c->sum('potential'), 2),
            'target'    => round($c->sum('target'), 2),
            'achieved'  => round($c->sum('achieved'), 2),
            'pct'       => $c->sum('target') > 0 ? round($c->sum('achieved') / $c->sum('target') * 100, 1) : null,
        ];

        // Reporting discipline: every Monday that has come due.
        $due = $goals->flatMap(fn ($g) => $g->checkins->filter(fn ($c) => $c->due_on->lte($today)));
        $onTime = $due->filter(fn ($c) => $c->status !== null && !$c->is_late)->count();
        $discipline = fn ($c) => $c->count() > 0 ? round($c->filter(fn ($x) => $x->status !== null && !$x->is_late)->count() / $c->count() * 100) : null;

        // Converted = an order status the Sales Report backs up with a sale in the round. An order
        // marked with no sale yet is "awaiting Sales Report" and does not count until it arrives.
        $isConverted = fn ($r) => in_array($r->status, self::ORDER_STATUSES, true) && $r->achieved > 0;

        // "Total Participants" means people enrolled, not people who have already nominated
        // customers. It counted distinct owners of goal rows, so a brand-new program read 0
        // participants right up until someone added their first customer - while the table
        // below it listed names. Count the rounds instead, which is what enrolment actually is.
        $enrolledCount = NinetyDayRound::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('user_id', $scope))
            ->when(!empty($f['program_id']), fn ($q) => $q->where('program_id', (int) $f['program_id']))
            ->when(empty($f['program_id']), fn ($q) => $q->where('status', 'active'))
            ->distinct('user_id')->count('user_id');

        // array_merge, not `+`: the union operator keeps the LEFT value on a duplicate key, so
        // $sum($rows)'s own 'people' silently won and every override here was discarded. That
        // went unnoticed because the old override computed the same number $sum already had.
        $cards = array_merge($sum($rows), [
            'people'     => $enrolledCount,
            'withGoals'  => $rows->pluck('user.id')->unique()->count(),
            'converted'  => $rows->filter($isConverted)->count(),
            'ordersAwaiting' => $rows->filter(fn ($r) => in_array($r->status, self::ORDER_STATUSES, true) && $r->achieved <= 0)->count(),
            'onTrack'    => $rows->where('status', 'On Track')->count(),
            'discipline' => $discipline($due),
            'dueTotal'   => $due->count(),
            'onTimeTotal' => $onTime,
        ]);

        $users = $rows->groupBy('user.id')->map(function ($c) use ($sum, $discipline, $goals, $isConverted) {
            $u = $c->first()->user;
            $mine = $goals->where('user_id', $u->id)->flatMap(fn ($g) => $g->checkins->filter(fn ($x) => $x->due_on->lte(Carbon::today())));

            return $sum($c) + [
                'id'         => $u->id,
                'name'       => trim($u->first_name . ' ' . $u->last_name),
                'department' => optional($u->department)->name,
                'converted'  => $c->filter($isConverted)->count(),
                'discipline' => $discipline($mine),
                'late'       => $mine->where('is_late', true)->count(),
                'missing'    => $mine->whereNull('status')->count(),
            ];
        });

        // Every salesperson in scope, including anyone with no customers added yet (all zeros).
        //
        // When a program is selected this must be the program's OWN participants, not every
        // salesperson. The program filter above only reaches $goals, so picking a program used
        // to leave the customer figures correctly filtered while this list still padded the
        // table with all 63 sales staff - a 3-person program rendered 63 rows of zeros.
        $enrolledIds = !empty($f['program_id'])
            ? NinetyDayRound::where('program_id', (int) $f['program_id'])->pluck('user_id')->unique()->all()
            : null;

        $withRow = $users->keys();
        $idle = self::programmableStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))
            ->when($enrolledIds !== null, fn ($c) => $c->whereIn('id', $enrolledIds))
            ->reject(fn ($u) => $withRow->contains($u->id))
            ->mapWithKeys(fn ($u) => [$u->id => [
                'id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name), 'department' => optional($u->department)->name,
                'goals' => 0, 'potential' => 0.0, 'target' => 0.0, 'achieved' => 0.0, 'pct' => null,
                'converted' => 0, 'discipline' => null, 'late' => 0, 'missing' => 0,
            ]]);

        $users = $users->union($idle)->values()->sortByDesc(fn ($u) => [$u['pct'] === null ? -1 : $u['pct'], $u['achieved']])->values();

        $topBrands = $rows->groupBy('brand')->map(fn ($c, $b) => $sum($c) + ['brand' => $b])->sortByDesc('potential')->take(10)->values();

        // Target vs actual per admin-created program (self-started rounds with no program
        // group under "No program"), for the Program Performance chart and Program Wise Summary.
        $byProgram = $rows->groupBy(fn ($r) => optional($r->program)->id ?: 0)
            ->map(fn ($c, $pid) => $sum($c) + ['id' => (int) $pid, 'name' => (int) $pid ? $c->first()->program->name : 'No program'])
            ->sortByDesc('target')->values();

        // "Order Results from Sales Report": real orders only, never typed by a salesperson.
        // A qualifying order is a goal with at least one real rupee of Sales Report sale inside
        // the round; "repeat" means it landed in more than one calendar month of the round.
        $withOrders = $rows->filter(fn ($r) => $r->achieved > 0);
        $orderResults = [
            'qualifyingOrders'  => $withOrders->count(),
            'totalOrderValue'   => round((float) $withOrders->sum('achieved'), 2),
            'avgOrderValue'     => $withOrders->count() > 0 ? round($withOrders->sum('achieved') / $withOrders->count(), 2) : null,
            'customersConverted' => $rows->filter($isConverted)->count(),
            'repeatOrders'      => $withOrders->where('repeat', true)->count(),
        ];

        // A coarser order-status split for the "Order Status" donut, built only from fields that
        // already exist (achieved, target, status) — never a separate typed-in order status.
        // Full: sold at least the target. Partial: some real sale, short of target. Cancelled:
        // the goal itself was marked Dropped. Pending: no sale yet and not dropped.
        $orderStatus = [
            'Full Order'    => $rows->filter(fn ($r) => $r->achieved > 0 && $r->achieved >= $r->target && $r->target > 0)->count(),
            'Partial Order' => $rows->filter(fn ($r) => $r->achieved > 0 && ($r->achieved < $r->target || $r->target <= 0))->count(),
            'Cancelled'     => $rows->filter(fn ($r) => $r->achieved <= 0 && $r->status === 'Dropped')->count(),
            'Pending'       => $rows->filter(fn ($r) => $r->achieved <= 0 && $r->status !== 'Dropped')->count(),
        ];

        $statuses = [];
        foreach (self::STATUSES as $st) {
            $statuses[$st] = $rows->where('status', $st)->count();
        }
        $statuses['Nothing reported yet'] = $rows->whereNull('status')->count();

        // Rounds in this scope can be different lengths (an admin-created program can run
        // longer or shorter than the usual 90 days) — the strip spans the longest one included,
        // so nothing gets truncated; a shorter round just has no rows past its own last week.
        $weeksSpan = $goals->isEmpty() ? self::WEEKS : $goals->max(fn ($g) => self::weeksOf($g->round));
        $strip = [];
        for ($w = 0; $w < $weeksSpan; $w++) {
            $cs = $goals->map(fn ($g) => $g->checkins->firstWhere('week_no', $w))->filter();
            $strip[$w] = ['total' => $cs->count(), 'done' => $cs->whereNotNull('status')->count(), 'late' => $cs->where('is_late', true)->count()];
        }

        return [
            'cards'        => $cards,
            'topCustomers' => $rows->sortByDesc('potential')->take(10)->values(),
            'topBrands'    => $topBrands,
            'topAchieved'  => $rows->filter(fn ($r) => $r->achieved > 0)->sortByDesc('achieved')->take(10)->values(),
            'byProgram'    => $byProgram,
            'orderResults' => $orderResults,
            'orderStatus'  => $orderStatus,
            'users'        => $users,
            'statuses'     => $statuses,
            'strip'        => $strip,
            'weeksSpan'    => $weeksSpan,
            'rows'         => $rows,
        ];
    }

    /** The salesperson themselves, and whoever oversees them (admin, HR, their manager), may comment on a goal's week. */
    public static function canNote(User $user, NinetyDayGoal $goal): bool
    {
        if (self::managesEveryone($user)) {
            return true;
        }
        // Adding a comment changes a goal, so seeing everyone (goals.view) is not
        // enough: outside the managers, it's your own goals or your own team's.
        $scope = $user->hasRole(['manager', 'Manager'])
            ? self::current(User::where('reporting_to_id', $user->id))->pluck('id')->push($user->id)->all()
            : [$user->id];

        return in_array((int) $goal->user_id, array_map('intval', $scope), true);
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
            ->when(!empty($f['round_id']), fn ($q) => $q->where('round_id', (int) $f['round_id']))
            ->when(!empty($f['status']), fn ($q) => $q->where('status', $f['status']))
            ->when(!empty($f['late']), fn ($q) => $q->where('is_late', true))
            ->orderByDesc('submitted_at')->limit($limit)->get()
            ->map(fn ($c) => (object) ['kind' => 'status', 'goal' => $c->goal, 'week_no' => $c->week_no, 'due_on' => $c->due_on,
                'status' => $c->status, 'is_late' => $c->is_late, 'text' => $c->comment, 'by' => optional($c->goal)->user, 'at' => $c->submitted_at]);

        $notes = NinetyDayGoalNote::with(['goal.user.department', 'author', 'checkin'])
            ->whereHas('goal', $ofPerson)
            ->when(!empty($f['round_id']), fn ($q) => $q->whereHas('checkin', fn ($c) => $c->where('round_id', (int) $f['round_id'])))
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
