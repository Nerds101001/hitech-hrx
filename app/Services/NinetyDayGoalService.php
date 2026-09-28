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
 * Sales Pipeline (customer, product, brand, goal, monthly potential in lakh) and
 * reports a status on every Monday until the round ends — 13 weeks (W0 to W12)
 * for the usual 90-day round, but an admin-created program can run a different
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

    public static function isSalesStaff(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return str_contains(strtolower((string) optional($user->department)->name), 'sales');
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
        return self::seesEveryone($user) || self::isSalesStaff($user) || ($user && $user->hasRole(['manager', 'Manager']));
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

        $targets = self::salesStaff()->whereIn('id', $userIds)->values();
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

    /** The user's active round, starting one this week if they have none. */
    public static function roundFor(User $user, ?Carbon $today = null): NinetyDayRound
    {
        return self::activeRound($user)
            ?? self::lockedFor($user, fn () => self::activeRound($user) ?? self::startRound($user, $today ?: Carbon::today()));
    }

    /**
     * How many Mondays a round actually has, from its own starts_on/ends_on — not the fixed
     * 90-day/13-week assumption. A self-started round is always exactly self::WEEKS (13), since
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
     * The months ("Y-m") whose sales count for this round: every month that ENDS inside it.
     * Sales are only known per month, and a 90-day round touches four calendar months, so
     * counting every month it touched overstated achievement against a three-month target and
     * counted a boundary month in two consecutive rounds. With "the month belongs to the round
     * it ends in", each month counts exactly once and a 90-day round usually covers three.
     */
    public static function roundMonths(NinetyDayRound $round): array
    {
        $starts = Carbon::parse($round->starts_on)->startOfDay();
        $ends = Carbon::parse($round->ends_on)->startOfDay();
        $out = [];
        for ($m = $starts->copy()->startOfMonth(); $m->lte($ends); $m->addMonth()) {
            $monthEnd = $m->copy()->endOfMonth()->startOfDay();
            if ($monthEnd->gte($starts) && $monthEnd->lte($ends)) {
                $out[] = $m->format('Y-m');
            }
        }

        // A short program inside one month has no month-end in it: use the month it runs in.
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
        $saved = 0;

        if ($actor->id !== $target->id && !self::managesEveryone($actor)) {
            return [0, ['You cannot fill this in for someone else.']];
        }
        if (!$round) {
            return [0, ['You have no active 90-day round.']];
        }
        if ($week < 0 || $week >= self::weeksOf($round)) {
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
                        'next_follow_up_date' => $nextFollowUp ?: null,
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
        }

        self::completeTaskIfDone($user, $round, $week);

        return [$saved, $errors];
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
            $week = (int) $m[1];
            $open = NinetyDayGoalCheckin::where('week_no', $week)->whereNull('status')
                ->whereHas('goal', fn ($g) => $g->where('user_id', $task->user_id)->whereNull('closed_at')
                    ->whereHas('round', fn ($r) => $r->where('status', 'active')))
                ->exists();

            return $open ? "Give a status for every customer for week {$week} before marking this complete." : null;
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
        $out = ['closed' => 0, 'started' => 0, 'checkins' => 0, 'tasks' => 0, 'errors' => 0];

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
                    $out['tasks'] += self::ensureTask($user, self::weekTaskTitle($week, self::dueOn($round, $week)), self::dueOn($round, $week))->wasRecentlyCreated ? 1 : 0;
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
     * the round (lakh), against its monthly potential over the round's three months.
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
            $target = $potential * max(1, count($months));

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
        });

        // Every salesperson in scope, including anyone with no customers added yet (all zeros).
        $withRow = $users->keys();
        $idle = self::salesStaff()->when($scope !== null, fn ($c) => $c->whereIn('id', $scope))
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
            'customersConverted' => $rows->whereIn('status', ['Trial Order', 'Regular Order'])->count(),
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
