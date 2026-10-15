<?php

namespace App\Http\Controllers\tenant;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;
use App\Notifications\Task\TaskAssigned;
use App\Notifications\Task\TaskObjection;
use App\Services\TaskEscalationService;
use App\Services\TaskScheduleService;
use App\Support\TaskAccess;
use App\Support\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Task Management - open to every signed-in user. Anyone can assign work to
 * anyone (one person, a hand-picked group, or whole departments at once) -
 * there is no reach restriction on who a task can be assigned TO.
 *
 * What differs by role is VISIBILITY: admin/hr/tasks.manage
 * (TaskAccess::isUnrestricted()) see every task in the system, assigned to
 * or by anyone. Everyone else sees only tasks that touch them directly -
 * assigned to them, or assigned by them - never a task between two other
 * people. scopeVisible() below is the one place that rule is applied, so
 * the listing, the stats tiles, and the Pendency tab can never disagree
 * about what counts as "mine."
 *
 * One Task row is created PER PERSON even for a department-wide assignment,
 * grouped by batch_id so this page can still show it as a single entry. That
 * is deliberate: it is what lets each person's own status, remarks and
 * overdue notification exist independently of everyone else's - the same
 * assignment finished by five people and still open with two others.
 */
class TaskManagementController extends Controller
{
    /** Everything but the direct task_id lookups, plus who can edit/delete, still goes through TaskAccess::canManage() for those single-task actions. */
    private function scopeVisible(\Illuminate\Database\Eloquent\Builder $query, $user): \Illuminate\Database\Eloquent\Builder
    {
        if (TaskAccess::isUnrestricted($user)) {
            return $query;
        }
        return $query->where(function ($q) use ($user) {
            $q->where('tasks.user_id', $user->id)->orWhere('tasks.assigned_by_id', $user->id);
        });
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $canSeeAll = TaskAccess::isUnrestricted($user);

        // Still-open work leads the unfiltered list (highest priority first,
        // then nearest deadline, undated tasks pushed to the end), with
        // closed tasks trailing behind it, most recently completed first -
        // otherwise a task finished weeks ago but with an old due date
        // outranks something still open just because they share a priority.
        // Same ordering My Tasks' "All" tab uses.
        $closedCase = "CASE WHEN tasks.status IN ('" . implode("','", Task::CLOSED_STATUSES) . "') THEN 1 ELSE 0 END";
        $query = Task::with(['user.department', 'assignedBy', 'ccUsers', 'taskUpdates' => fn ($q) => $q->with('createdBy:id,first_name,last_name')->latest()->limit(1)])
            ->orderByRaw($closedCase)
            ->orderByRaw("CASE WHEN $closedCase = 1 THEN NULL ELSE FIELD(tasks.priority, 'high', 'medium', 'low') END")
            ->orderByRaw("CASE WHEN $closedCase = 1 THEN NULL ELSE tasks.due_date IS NULL END")
            ->orderByRaw("CASE WHEN $closedCase = 1 THEN NULL ELSE tasks.due_date END")
            ->orderByDesc('tasks.completed_at')
            ->orderByDesc('tasks.created_at');

        // Column sorting - the table headers link here. There was none: the
        // order was fixed to priority, then due date.
        $sort = $request->input('sort');
        $dir = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $nameOf = fn (string $col) => "(SELECT CONCAT(u.first_name, ' ', COALESCE(u.last_name, '')) FROM users u WHERE u.id = tasks.$col)";
        $sortSql = [
            'title'       => 'tasks.title',
            'assignee'    => $nameOf('user_id'),
            'assigned_by' => $nameOf('assigned_by_id'),
            'priority'    => "FIELD(tasks.priority, 'low', 'medium', 'high')",
            'status'      => "FIELD(tasks.status, 'objection', 'new', 'in_progress', 'completed', 'resolved', 'closed', 'cancelled')",
            'created'     => 'tasks.created_at',
        ][$sort] ?? null;
        if ($sort === 'due') {
            $query->reorder()->orderByRaw('tasks.due_date IS NULL')->orderBy('tasks.due_date', $dir)->orderByDesc('tasks.id');
        } elseif ($sortSql) {
            $query->reorder()->orderByRaw("$sortSql $dir")->orderByDesc('tasks.id');
        }

        $query = $this->scopeVisible($query, $user);

        // One person's tasks - what Pendency's "View tasks" opens (it used to
        // search by first name, which also matched everyone sharing it).
        if ($request->filled('user_id')) {
            $query->where('tasks.user_id', (int) $request->user_id);
        }

        // "Assigned by me" answers the exact question asked for: which
        // tasks did I hand to someone else, and what's their status - as
        // distinct from "assigned to me", my own work. Available to
        // everyone, including admin/hr, to narrow their otherwise-unscoped
        // view down to just their own activity.
        if ($request->input('mine') === 'to') {
            $query->where('tasks.user_id', $user->id);
        } elseif ($request->input('mine') === 'by') {
            $query->where('tasks.assigned_by_id', $user->id);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'overdue'    => $query->overdue(),
                'due_today'  => $query->dueToday(),
                'open'       => $query->whereNotIn('tasks.status', Task::CLOSED_STATUSES),
                'completed'  => $query->whereIn('tasks.status', Task::CLOSED_STATUSES),
                default      => $query->where('tasks.status', $request->status)
                    ->whereNotIn('tasks.status', Task::CLOSED_STATUSES),
            };
            // Once it's done, priority/due date no longer say anything
            // useful - what was just submitted belongs on top instead. Only
            // when nobody clicked a column header to sort it some other way.
            if ($request->status === 'completed' && !$sortSql && $sort !== 'due') {
                $query->reorder()->orderByDesc('tasks.completed_at')->orderByDesc('tasks.id');
            }
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%"));
            });
        }

        // One row per batch for the summary list, counted from the same
        // scoped query so a manager never sees a batch's true size if it
        // reaches beyond their own team.
        // reorder() first: the base query's ORDER BY created_at is not one of
        // the GROUP BY columns below, which MySQL's only_full_group_by mode
        // (the default here) rejects outright rather than just ignoring.
        // keyBy (not pluck) - the view needs both total and done to show a
        // "3/101 done" progress badge on the collapsed summary row.
        $batchCounts = (clone $query)->reorder()->whereNotNull('batch_id')
            ->select('batch_id', DB::raw('count(*) as total'), DB::raw("sum(case when status in ('completed','cancelled','resolved','closed') then 1 else 0 end) as done"))
            ->groupBy('batch_id')
            ->get()->keyBy('batch_id');

        // 'all' is not literally unpaginated - LengthAwarePaginator with a
        // huge per-page still works fine (no meaningful page links, which is
        // exactly what "show everything" should look like) without needing
        // a second code path for the view to render.
        $perPageOptions = ['10' => 10, '20' => 20, '50' => 50, '1000' => 1000, 'all' => 1000000];
        $perPage = $perPageOptions[(string) $request->input('per_page', '20')] ?? 20;
        $page = max(1, (int) $request->input('page', 1));

        // Paginating $query directly counted every task row, but a batch
        // collapses to ONE row in the view - a single 101-person batch used
        // to fill an entire 20-per-page page on its own, so it rendered as
        // one lonely row while "Showing 1 to 20 of 119" and six page links
        // sat underneath it. Paginate the same list the view actually shows
        // instead: one id per batch (its first/representative task) plus one
        // id per unbatched task, in the exact order $query already sorts by.
        // toBase() skips Eloquent's eager-loading for this id-only pass -
        // ~100-200 rows city-wide, cheap either way, but no reason to
        // hydrate 'user'/'assignedBy'/'taskUpdates' for rows about to be
        // discarded.
        $seenBatches = [];
        $displayIds = [];
        foreach ((clone $query)->toBase()->get(['tasks.id', 'tasks.batch_id']) as $row) {
            if ($row->batch_id && ($batchCounts[$row->batch_id]->total ?? 1) > 1) {
                if (isset($seenBatches[$row->batch_id])) {
                    continue;
                }
                $seenBatches[$row->batch_id] = true;
            }
            $displayIds[] = $row->id;
        }

        $total = count($displayIds);
        $pageIds = array_slice($displayIds, ($page - 1) * $perPage, $perPage);
        $pageTasksById = (clone $query)->whereIn('tasks.id', $pageIds)->get()->keyBy('id');
        $pageTasks = collect($pageIds)->map(fn ($id) => $pageTasksById->get($id))->filter()->values();

        $tasks = (new \Illuminate\Pagination\LengthAwarePaginator($pageTasks, $total, $perPage, $page, [
            'path'  => $request->url(),
            'query' => $request->query(),
        ]));

        // Every department and every active employee, for everyone - nobody
        // is restricted in who they can assign a task TO, only in whose
        // existing tasks they can see (scopeVisible() above).
        $departments = Department::cachedActive();

        // The department comes along for the assignee pickers: names are not
        // unique here. Two MANISHA KUMARIs are active right now, one in
        // Accounts and one in Billing, and a list showing only names gives
        // the assigner no way to tell which one they are picking.
        // Eager-loaded rather than read per row, since this list renders four
        // times on the page.
        $employees = User::where('status', UserAccountStatus::ACTIVE)
            ->with('department:id,name')
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'department_id']);

        $stats = [
            'total'      => (clone $query)->count(),
            'overdue'    => (clone $query)->overdue()->count(),
            'due_today'  => (clone $query)->dueToday()->count(),
            'completed'  => (clone $query)->whereIn('tasks.status', Task::CLOSED_STATUSES)->count(),
            'pending'    => (clone $query)->whereNotIn('tasks.status', Task::CLOSED_STATUSES)->count(),
        ];

        // Status filter: a schedule is normally "active" (still firing); a
        // stopped one used to be unreachable here once it stopped looping -
        // there was no way to look back at what it was or who was on it.
        $recurringStatus = in_array($request->input('recurring_status'), ['active', 'stopped', 'all'], true)
            ? $request->input('recurring_status') : 'active';
        $scheduleQuery = TaskSchedule::query()->with('assignedBy');
        match ($recurringStatus) {
            'stopped' => $scheduleQuery->where('is_active', false),
            'all'     => null,
            default   => $scheduleQuery->where('is_active', true),
        };
        // A regular user sees schedules they assigned OR are a target of. The
        // target match is on a JSON-ish column, so resolve the visible ids in
        // PHP (a small set) and constrain the paginated query to them — this
        // keeps "assigned to me or by me" while still allowing search + paging.
        if (!$canSeeAll) {
            $visibleScheduleIds = TaskSchedule::query()
                ->get(['id', 'assigned_by_id', 'target_user_ids'])
                ->filter(fn ($s) => (int) $s->assigned_by_id === $user->id || in_array($user->id, $s->target_user_ids ?? []))
                ->pluck('id');
            $scheduleQuery->whereIn('id', $visibleScheduleIds);
        }
        // Search by task title.
        if ($request->filled('recurring_search')) {
            $scheduleQuery->where('title', 'like', '%' . $request->recurring_search . '%');
        }
        if ($request->filled('recurring_priority')) {
            $scheduleQuery->where('priority', $request->recurring_priority);
        }
        if ($request->filled('recurring_frequency')) {
            $scheduleQuery->where('frequency', $request->recurring_frequency);
        }
        // Sort: task title, how it repeats, priority, or newest (default).
        $recurringSort = in_array($request->input('recurring_sort'), ['title', 'frequency', 'priority', 'created'], true)
            ? $request->input('recurring_sort') : 'created';
        $recurringDir = $request->input('recurring_dir') === 'asc' ? 'asc' : 'desc';
        if ($recurringSort === 'priority') {
            $scheduleQuery->orderByRaw("FIELD(priority, 'low', 'medium', 'high') {$recurringDir}");
        } else {
            $scheduleQuery->orderBy(match ($recurringSort) {
                'title' => 'title', 'frequency' => 'frequency', default => 'created_at',
            }, $recurringDir);
        }
        // Same per-page options as the One-time tab.
        $recurringPerPageOptions = ['10' => 10, '20' => 20, '50' => 50, '100' => 100, 'all' => 1000000];
        $recurringPerPage = $recurringPerPageOptions[(string) $request->input('recurring_per_page', '15')] ?? 15;
        $schedules = $scheduleQuery->paginate($recurringPerPage, ['*'], 'recurring_page')
            ->withQueryString()->fragment('tmRecurring');
        // Resolved once here rather than per-row in the view (N+1) — the id
        // list is small (schedule targets), so one query per page is fine.
        $allTargetIds = $schedules->flatMap(fn ($s) => $s->target_user_ids ?? [])->unique();
        $targetNames = User::whereIn('id', $allTargetIds)->get(['id', 'first_name', 'last_name', 'status'])->keyBy('id');
        // Who on each schedule is still here - a schedule whose people have
        // all left creates nothing (TaskScheduleService::fireIfDue).
        $activeTargetIds = $targetNames->filter(fn ($u) => $u->status === UserAccountStatus::ACTIVE)->keys()->flip();

        // "Was this actually firing?" was the whole reason the recurring
        // table needed redoing — last_run_on alone doesn't say whether that
        // run reached everyone. One grouped query for every schedule's most
        // recent day's outcome, keyed by schedule id so the view does no
        // querying of its own.
        $scheduleIds = $schedules->pluck('id');
        $lastFireStats = $scheduleIds->isEmpty() ? collect() : Task::whereIn('task_schedule_id', $scheduleIds)
            ->select('task_schedule_id', DB::raw('DATE(for_date) as fire_date'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status in ('completed','cancelled','resolved','closed') then 1 else 0 end) as done"))
            ->groupBy('task_schedule_id', DB::raw('DATE(for_date)'))
            ->orderByDesc('fire_date')
            ->get()
            ->groupBy('task_schedule_id')
            ->map(fn ($rows) => $rows->first()); // most recent fire_date per schedule

        $pendency = $this->buildPendency($request, $user);
        $escalations = $this->buildEscalations($request, $user, $canSeeAll);

        return view('tenant.task-management.index', compact('tasks', 'departments', 'employees', 'stats', 'batchCounts', 'schedules', 'targetNames', 'activeTargetIds', 'lastFireStats', 'pendency', 'escalations', 'canSeeAll', 'sort', 'dir', 'recurringSort', 'recurringDir', 'recurringStatus'));
    }

    /**
     * Every currently-overdue open task with a due date, scoped to what this
     * viewer may see, and marked with its escalation status - 'escalated'
     * (already sent), 'will_escalate' (past the threshold, the next cron run
     * will send it) or 'pending' (overdue but not there yet). Gives a
     * manager visibility into exactly what TaskEscalationService /
     * EscalateOverdueTasks would do, before and after it runs.
     *
     * Visible to NOBODY else: the task's creator, its assignee, whoever
     * assigned it, anyone CC'd on it, the manager it escalates TO (the same
     * recipientFor() the real cron uses), or HR/Admin/tasks.manage
     * (canSeeAll) - not any other logged-in user.
     */
    private function buildEscalations(Request $request, $user, bool $canSeeAll): array
    {
        $base = Task::whereNotIn('status', Task::CLOSED_STATUSES)
            ->whereNotNull('due_date')
            ->whereRaw(Task::DEADLINE_SQL . ' < ?', [now()])
            ->with(['user:id,first_name,last_name', 'assignedBy:id,first_name,last_name', 'taskCcs']);

        $rows = (clone $base)->get()->filter(function ($task) use ($user, $canSeeAll) {
            if ($canSeeAll) {
                return true;
            }
            $uid = (int) $user->id;
            if ((int) $task->user_id === $uid || (int) $task->assigned_by_id === $uid || (int) $task->created_by_id === $uid) {
                return true; // assignee, assigner, or creator
            }
            if ($task->taskCcs->contains(fn ($cc) => (int) $cc->user_id === $uid)) {
                return true; // CC'd
            }
            $recipient = TaskEscalationService::recipientFor($task);

            return $recipient && (int) $recipient->id === $uid; // the manager it escalates to
        })->map(function ($task) {
            $escalatedTo = TaskEscalationService::recipientFor($task);

            return [
                'taskId'       => $task->id,
                'title'        => $task->title,
                'assignedBy'   => optional($task->assignedBy)->getFullName() ?? '—',
                'assignee'     => optional($task->user)->getFullName() ?? '—',
                'escalatedTo'  => $escalatedTo ? $escalatedTo->getFullName() : '— (no manager set)',
                'dueLabel'     => $task->dueLabel(),
                'daysOverdue'  => TaskEscalationService::daysOverdue($task),
                'status'       => TaskEscalationService::statusFor($task),
                'escalatedAt'  => $task->escalated_at,
            ];
        })->values();

        if ($request->filled('escalation_search')) {
            $term = mb_strtolower($request->input('escalation_search'));
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['title']), $term)
                || str_contains(mb_strtolower($r['assignee']), $term)
                || str_contains(mb_strtolower($r['assignedBy']), $term)
                || str_contains(mb_strtolower($r['escalatedTo']), $term));
        }
        if ($request->filled('escalation_status')) {
            $rows = $rows->where('status', $request->input('escalation_status'));
        }

        $escSort = in_array($request->input('escalation_sort'), ['title', 'days', 'status'], true)
            ? $request->input('escalation_sort') : 'days';
        $escDir = $request->input('escalation_dir') === 'asc' ? 'asc' : 'desc';
        $rows = match ($escSort) {
            'title'  => $escDir === 'asc' ? $rows->sortBy('title') : $rows->sortByDesc('title'),
            'status' => $escDir === 'asc' ? $rows->sortBy('status') : $rows->sortByDesc('status'),
            default  => $escDir === 'asc' ? $rows->sortBy('daysOverdue') : $rows->sortByDesc('daysOverdue'),
        };
        $rows = $rows->values();

        $perPageOptions = ['10' => 10, '15' => 15, '25' => 25, '50' => 50, 'all' => 1000000];
        $perPage = $perPageOptions[(string) $request->input('escalation_per_page', '15')] ?? 15;
        $page = max(1, (int) $request->input('escalation_page', 1));
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => 'escalation_page']
        );
        $paginated->fragment('tmEscalations');

        return [
            'rows'              => $paginated,
            'escalatedCount'    => $rows->where('status', 'escalated')->count(),
            'willEscalateCount' => $rows->where('status', 'will_escalate')->count(),
            'pendingCount'      => $rows->where('status', 'pending')->count(),
            'exemptCount'       => $rows->where('status', 'exempt')->count(),
            'sort'              => $escSort,
            'dir'               => $escDir,
            'thresholdDays'     => max(1, (int) config('tasks.escalate_after_days', 2)),
        ];
    }

    /**
     * Org-wide (or, for a regular user, only-mine) backlog: who and which
     * department it's actually sitting with, not just "how many tasks" -
     * the same scopeVisible() rule as the rest of this controller, so a
     * regular user's Pendency tab never reveals anyone else's numbers.
     */
    private function buildPendency(Request $request, $user): array
    {
        // Table-qualified throughout - departments/users both carry a
        // 'status' column of their own once joined below, and an
        // unqualified 'status' here is ambiguous rather than defaulting to
        // tasks.status the way it would with no join in play.
        $base = Task::whereNotIn('tasks.status', Task::CLOSED_STATUSES);
        $base = $this->scopeVisible($base, $user);

        // The same overdue rule as the tiles and the list (Task::DEADLINE_SQL),
        // against the app's clock. It used to be "due before NOW()": overdue
        // from midnight instead of 6pm, and on the database's clock, which
        // runs on UTC - five and a half hours behind - so the tab's own total
        // and its breakdowns could disagree with each other and with the tiles.
        $overdueCase = 'sum(case when tasks.due_date is not null and ' . Task::DEADLINE_SQL . ' < ? then 1 else 0 end) as overdue';
        $now = now()->toDateTimeString();

        // The department breakdown table has its own sort, independent of the
        // by-person table below it - "Pending" (worst-first) by default.
        $deptSort = in_array($request->input('pendency_dept_sort'), ['pending', 'overdue', 'name'], true)
            ? $request->input('pendency_dept_sort') : 'pending';
        $deptDir = $request->input('pendency_dept_dir') === 'asc' ? 'asc' : 'desc';
        $deptOrderColumn = match ($deptSort) {
            'overdue' => 'overdue',
            'name'    => 'department_name',
            default   => 'pending',
        };

        $byDepartment = (clone $base)
            ->join('users', 'users.id', '=', 'tasks.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select(
                'departments.id as department_id',
                DB::raw("COALESCE(departments.name, 'No Department') as department_name"),
                DB::raw('count(*) as pending')
            )
            ->selectRaw($overdueCase, [$now])
            ->groupBy('departments.id', 'departments.name')
            ->orderBy($deptOrderColumn, $deptDir)
            ->get();

        $sort = in_array($request->input('pendency_sort'), ['pending', 'overdue', 'name', 'department'], true)
            ? $request->input('pendency_sort') : 'pending';
        $dir = $request->input('pendency_dir') === 'asc' ? 'asc' : 'desc';

        $personQuery = (clone $base)
            ->join('users', 'users.id', '=', 'tasks.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->select(
                'users.id as user_id', 'users.first_name', 'users.last_name',
                DB::raw("COALESCE(departments.name, 'No Department') as department_name"),
                DB::raw('count(*) as pending')
            )
            ->selectRaw($overdueCase, [$now])
            ->groupBy('users.id', 'users.first_name', 'users.last_name', 'departments.name');

        if ($request->filled('pendency_department_id')) {
            $personQuery->where('users.department_id', $request->pendency_department_id);
        }

        // Search a person by name.
        if ($request->filled('pendency_search')) {
            $term = $request->pendency_search;
            $personQuery->where(fn ($q) => $q->where('users.first_name', 'like', "%{$term}%")
                ->orWhere('users.last_name', 'like', "%{$term}%"));
        }

        $orderColumn = match ($sort) {
            'overdue'    => 'overdue',
            'name'       => 'users.first_name',
            'department' => 'departments.name',
            default      => 'pending',
        };
        $pendencyPerPageOptions = ['10' => 10, '15' => 15, '25' => 25, '50' => 50, 'all' => 1000000];
        $pendencyPerPage = $pendencyPerPageOptions[(string) $request->input('pendency_per_page', '15')] ?? 15;
        $byPerson = $personQuery->orderBy($orderColumn, $dir)
            ->paginate($pendencyPerPage, ['*'], 'pendency_page')->withQueryString()->fragment('tmPendency');

        return [
            'totalPending'        => (clone $base)->count(),
            'totalOverdue'        => (clone $base)->overdue()->count(),
            'departmentsAffected' => $byDepartment->count(),
            'peopleAffected'      => $byPerson->total(),
            'byDepartment'        => $byDepartment,
            'byPerson'            => $byPerson,
            'sort'                => $sort,
            'dir'                 => $dir,
            'deptSort'            => $deptSort,
            'deptDir'             => $deptDir,
        ];
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'due_date'       => 'nullable|date|after_or_equal:today',
            'priority'       => 'nullable|in:' . implode(',', Task::PRIORITIES),
            // A recurring pick: creates a TaskSchedule instead of one-off
            // tasks, and fires today's occurrence immediately if it matches.
            'interval'       => 'nullable|in:daily,weekly,monthly',
            'weekday'        => 'required_if:interval,weekly|nullable|integer|min:0|max:6',
            'day_of_month'   => 'required_if:interval,monthly|nullable|integer|min:1|max:31',
            // Optional end date for a recurring task — after this it stops on its own.
            'ends_on'        => 'nullable|date|after_or_equal:today',
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:users,id',
            'department_ids'   => 'nullable|array',
            'department_ids.*' => 'integer|exists:departments,id',
            // People watching this task without owning it - e.g. Sadhna CC'd
            // on a task Mukul assigned to someone else, so she can follow its
            // status and reply without it counting as her own work.
            'cc_user_ids'    => 'nullable|array',
            'cc_user_ids.*'  => 'integer|exists:users,id',
            'attachment'     => TaskAttachment::RULES,
        ]);

        if (empty($validated['employee_ids']) && empty($validated['department_ids'])) {
            return response()->json(['success' => false, 'message' => 'Pick at least one employee or department.'], 422);
        }

        // after_or_equal:today only compares days, so a time earlier today
        // passed and the task was born overdue.
        if ($message = self::pastDueMessage($validated['due_date'] ?? null)) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        // Resolve the full target list: explicit picks plus every active
        // person in the picked departments, deduplicated.
        $targetIds = collect($validated['employee_ids'] ?? []);
        if (!empty($validated['department_ids'])) {
            $targetIds = $targetIds->merge(
                User::whereIn('department_id', $validated['department_ids'])
                    ->where('status', UserAccountStatus::ACTIVE)
                    ->pluck('id')
            );
        }
        // No reach restriction - anyone can assign a task to anyone, so
        // nothing gets silently dropped here the way it used to when this
        // page was manager-scoped.
        $targetIds = $targetIds->map(fn ($id) => (int) $id)->unique()->values();

        if ($targetIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nobody active was found in the selected department(s).'], 422);
        }

        // Not scoped by TaskAccess like $targetIds: CC is watching, not being
        // assigned work, so it deliberately isn't confined to the assigner's
        // own reports. Excludes anyone already a target - being CC'd on your
        // own task is meaningless.
        $ccIds = collect($validated['cc_user_ids'] ?? [])->unique()->diff($targetIds)->values();

        if (!empty($validated['interval'])) {
            return $this->storeSchedule($validated, $targetIds, $ccIds, $user);
        }

        $batchId = (string) Str::uuid();
        $created = [];

        // One reference image, shared by every task this batch creates -
        // storing it once and re-pointing every row at the same path avoids
        // saving up to hundreds of identical copies of the same upload.
        $attachmentPath = $request->hasFile('attachment')
            ? TaskAttachment::store($request->file('attachment'), 'task-attachments')
            : null;

        DB::transaction(function () use ($targetIds, $ccIds, $validated, $user, $batchId, $attachmentPath, &$created) {
            foreach ($targetIds as $uid) {
                $task = Task::create([
                    'batch_id'       => $batchId,
                    'title'          => $validated['title'],
                    'description'    => $validated['description'] ?? null,
                    'type'           => 'open',
                    'assigned_by_id' => $user->id,
                    'user_id'        => $uid,
                    'for_date'       => now(),
                    'due_date'       => $validated['due_date'] ?? null,
                    'status'         => 'new',
                    'priority'       => $validated['priority'] ?? 'medium',
                    'attachment_path' => $attachmentPath,
                    'created_by_id'  => $user->id,
                    'updated_by_id'  => $user->id,
                ]);

                foreach ($ccIds as $ccId) {
                    $task->taskCcs()->create(['user_id' => $ccId, 'created_by_id' => $user->id]);
                }

                $created[] = $task;
            }
        });

        // Sent AFTER the response goes out, not before. This mailer's real
        // SMTP host is slow (sometimes unreachable) from a dev box, and every
        // notify() here used to block the whole request on it — the tasks
        // were already committed, so "Assigning..." sat frozen on the button
        // for as long as the mail attempt hung, even though nothing was
        // actually still in progress.
        //
        // app()->terminating() rather than dispatch(...)->afterResponse():
        // the latter routes through the queue Dispatcher, which serializes
        // the closure via reflection on its *source file* -- obfuscated on
        // prod, so file_get_contents() can't find it and every notify() here
        // silently never ran (confirmed from real prod error emails: user
        // 178 at /task-management, five times in one day). terminating()
        // just holds the closure in memory and calls it directly.
        $createdIds = array_map(fn ($t) => $t->id, $created);
        app()->terminating(function () use ($createdIds) {
            foreach (Task::whereIn('id', $createdIds)->with('user')->get() as $task) {
                try {
                    $task->user?->notify(new TaskAssigned($task));
                } catch (\Throwable $e) {
                    // One broken address must not stop the rest of the batch
                    // from being notified.
                    Log::warning("Task-assigned notify failed for task {$task->id}: " . $e->getMessage());
                }
            }
        });

        $message = count($created) . ' task(s) assigned.';

        return response()->json(['success' => true, 'message' => $message, 'batch_id' => $batchId, 'assigned' => count($created)]);
    }

    /** A due date+time that has already passed (a day-only date of today is fine - it runs to 6pm). */
    private static function pastDueMessage(?string $due): ?string
    {
        if (!$due) {
            return null;
        }
        $at = \Carbon\Carbon::parse($due);
        $passed = $at->format('H:i:s') === '00:00:00'
            ? $at->copy()->startOfDay()->lt(today())
            : $at->lt(now()->subMinutes(5));

        return $passed ? 'That due date and time has already passed - pick a later one.' : null;
    }

    /**
     * The recurring path: one TaskSchedule row, target_user_ids snapshotted
     * once (already TaskAccess-scoped by the caller) rather than re-resolved
     * on every future firing — see the migration for why. Fires today's
     * occurrence right away if the chosen day matches, instead of making the
     * assigner wait for tomorrow's cron run to see anything happen.
     */
    private function storeSchedule(array $validated, \Illuminate\Support\Collection $targetIds, \Illuminate\Support\Collection $ccIds, $user)
    {
        // The same recurring task twice: the same people got it twice a day.
        // 145 such pairs on prod came from five schedules created twice.
        $weekday = $validated['interval'] === 'weekly' ? (int) $validated['weekday'] : null;
        $dayOfMonth = $validated['interval'] === 'monthly' ? (int) $validated['day_of_month'] : null;
        $twin = TaskSchedule::where('is_active', true)
            ->where('frequency', $validated['interval'])
            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower(trim($validated['title']))])
            ->get()
            ->first(fn ($s) => $s->weekday === $weekday && $s->day_of_month === $dayOfMonth
                && collect($s->target_user_ids ?? [])->map(fn ($id) => (int) $id)->intersect($targetIds)->isNotEmpty());
        if ($twin) {
            $overlap = collect($twin->target_user_ids ?? [])->map(fn ($id) => (int) $id)->intersect($targetIds)->count();
            return response()->json(['success' => false, 'message' => 'This recurring task already exists - "' . $twin->title . '", '
                . $twin->recurrenceLabel() . ', set by ' . (optional($twin->assignedBy)->getFullName() ?? 'someone')
                . ', for ' . $overlap . ' of these people. Stop that one first, or change the title.'], 422);
        }

        $schedule = TaskSchedule::create([
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'priority'        => $validated['priority'] ?? 'medium',
            'frequency'       => $validated['interval'],
            'weekday'         => $validated['interval'] === 'weekly' ? $validated['weekday'] : null,
            'day_of_month'    => $validated['interval'] === 'monthly' ? $validated['day_of_month'] : null,
            'ends_on'         => !empty($validated['ends_on']) ? $validated['ends_on'] : null,
            'target_user_ids' => $targetIds->values()->all(),
            'cc_user_ids'     => $ccIds->values()->all(),
            'assigned_by_id'  => $user->id,
            'is_active'       => true,
            'created_by_id'   => $user->id,
            'updated_by_id'   => $user->id,
        ]);

        $firstRun = TaskScheduleService::fireIfDue($schedule);

        $message = 'Recurring task created — ' . $schedule->recurrenceLabel() . ', ' . $targetIds->count() . ' ' . \Illuminate\Support\Str::plural('person', $targetIds->count()) . '.';
        if ($firstRun->isNotEmpty()) {
            $message .= ' ' . $firstRun->count() . ' assigned today, since today matches.';
        }

        return response()->json(['success' => true, 'message' => $message, 'schedule_id' => $schedule->id]);
    }

    /** Stop a recurring schedule for everyone — no more future occurrences. Already-created tasks are untouched. */
    public function stopSchedule($id)
    {
        $schedule = TaskSchedule::findOrFail($id);
        if (!TaskAccess::isUnrestricted(auth()->user()) && $schedule->assigned_by_id !== auth()->id()) {
            abort(403, 'You cannot manage a recurring task you did not create.');
        }

        $schedule->update(['is_active' => false]);

        return response()->json(['success' => true, 'message' => 'Recurring task stopped. Nothing more will be created from it.']);
    }

    /** Set (or clear) the end date on a recurring schedule so it stops on its own. */
    public function setScheduleEndDate(Request $request, $id)
    {
        $schedule = TaskSchedule::findOrFail($id);
        if (!TaskAccess::isUnrestricted(auth()->user()) && $schedule->assigned_by_id !== auth()->id()) {
            abort(403, 'You cannot manage a recurring task you did not create.');
        }

        $data = $request->validate(['ends_on' => 'nullable|date|after_or_equal:today']);
        $schedule->update(['ends_on' => $data['ends_on'] ?? null, 'updated_by_id' => auth()->id()]);

        return response()->json([
            'success' => true,
            'message' => empty($data['ends_on'])
                ? 'End date cleared — this recurring task now runs until stopped.'
                : 'End date set to ' . \Illuminate\Support\Carbon::parse($data['ends_on'])->format('d M Y') . '. It will stop on its own after that.',
        ]);
    }

    /** Drop one person from an active schedule's future occurrences, without touching anyone else on it. */
    public function removeFromSchedule(Request $request, $id)
    {
        $schedule = TaskSchedule::findOrFail($id);
        if (!TaskAccess::isUnrestricted(auth()->user()) && $schedule->assigned_by_id !== auth()->id()) {
            abort(403, 'You cannot manage a recurring task you did not create.');
        }

        $userId = (int) $request->input('user_id');
        $schedule->update([
            'target_user_ids' => array_values(array_diff($schedule->target_user_ids ?? [], [$userId])),
        ]);

        return response()->json(['success' => true, 'message' => 'Removed from the recurring assignment.']);
    }

    /**
     * Every day this schedule has actually fired, newest first, with how
     * many of that day's tasks got done - the traceability the flat
     * "Repeats" row never showed (a schedule can look identical whether it
     * has fired 40 times or never once).
     */
    public function scheduleHistory($id)
    {
        $schedule = TaskSchedule::findOrFail($id);
        if (!TaskAccess::isUnrestricted(auth()->user()) && $schedule->assigned_by_id !== auth()->id()) {
            abort(403, 'You cannot view a recurring task you did not create.');
        }

        $rows = Task::where('task_schedule_id', $schedule->id)
            ->select('batch_id', DB::raw('DATE(for_date) as fire_date'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status in ('completed','cancelled','resolved','closed') then 1 else 0 end) as done"))
            ->groupBy('batch_id', DB::raw('DATE(for_date)'))
            ->orderByDesc('fire_date')
            ->get();

        return response()->json([
            'success' => true,
            'html' => view('tenant.task-management._schedule_history', ['rows' => $rows])->render(),
        ]);
    }

    /** Everyone in one bulk-assignment batch and their own status. */
    public function showBatch(Request $request, string $batchId)
    {
        $user = auth()->user();

        $query = Task::with(['user.department', 'assignedBy', 'ccUsers', 'taskUpdates' => fn ($q) => $q->with('createdBy:id,first_name,last_name')->latest()->limit(1)])
            ->where('batch_id', $batchId);
        $query = $this->scopeVisible($query, $user);

        $tasks = $query->orderBy('id')->get();

        if ($tasks->isEmpty()) {
            abort(404);
        }

        // The Task Management list collapses every batch into one summary
        // row with an inline expand toggle (rather than the old behaviour of
        // navigating away to this same query's full-page view) - it asks for
        // this same data as JSON so the rows can be injected in place.
        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'html' => view('tenant.task-management._batch_children', ['tasks' => $tasks])->render(),
            ]);
        }

        return view('tenant.task-management.batch', ['batchId' => $batchId, 'tasks' => $tasks]);
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);

        if (!TaskAccess::canManage(auth()->user(), $task)) {
            abort(403, 'You cannot manage a task outside your team.');
        }

        $task->delete();

        return response()->json(['success' => true, 'message' => 'Task deleted successfully.']);
    }

    /** Delete every task in $request->ids this user is actually allowed to manage; silently skip the rest. */
    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $user = auth()->user();
        $tasks = Task::whereIn('id', $validated['ids'])->get();

        $deleted = 0;
        $skipped = 0;
        foreach ($tasks as $task) {
            if (TaskAccess::canManage($user, $task)) {
                $task->delete();
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $message = $deleted . ' task(s) deleted.';
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (outside your team).";
        }

        return response()->json(['success' => $deleted > 0, 'message' => $message, 'deleted' => $deleted]);
    }

    /** Edit an existing task's own details, and optionally who it's assigned to. */
    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = auth()->user();

        if (!TaskAccess::canManage($user, $task)) {
            abort(403, 'You cannot manage a task outside your team.');
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'nullable|date',
            'priority'    => 'nullable|in:' . implode(',', Task::PRIORITIES),
            'user_id'     => 'nullable|integer|exists:users,id',
            'cc_user_ids'   => 'nullable|array',
            'cc_user_ids.*' => 'integer|exists:users,id',
            // An edit made on a grouped row used to change only the one
            // person's copy the row happened to show. Now it can apply to
            // everyone in the assignment (reassigning stays one person).
            'apply_to_batch' => 'nullable|boolean',
        ]);

        $newDue = !empty($validated['due_date']) ? \Carbon\Carbon::parse($validated['due_date']) : null;
        $dueChanged = optional($newDue)->format('Y-m-d H:i') !== optional($task->due_date)->format('Y-m-d H:i');
        if ($dueChanged && ($message = self::pastDueMessage($validated['due_date'] ?? null))) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $targets = collect([$task]);
        if (!empty($validated['apply_to_batch']) && $task->batch_id) {
            $targets = Task::where('batch_id', $task->batch_id)->get()->filter(fn ($t) => TaskAccess::canManage($user, $t))->values();
        }

        $reassignTo = (!empty($validated['user_id']) && $targets->count() === 1 && (int) $validated['user_id'] !== (int) $task->user_id)
            ? User::find((int) $validated['user_id'])
            : null;

        // Collected inside the transaction, dispatched after it commits - one
        // entry per target whose own details changed (not reassigned; that
        // path already notifies the new assignee via TaskAssigned).
        $notifyList = [];
        DB::transaction(function () use ($targets, $validated, $request, $user, $newDue, $reassignTo, &$notifyList) {
            foreach ($targets as $t) {
                $notes = [];
                $tDueChanged = optional($newDue)->format('Y-m-d H:i') !== optional($t->due_date)->format('Y-m-d H:i');
                $oldDue = $t->dueLabel() ?? 'no due date';
                $updates = [
                    'title'         => $validated['title'],
                    'description'   => $validated['description'] ?? null,
                    'due_date'      => $newDue,
                    'priority'      => $validated['priority'] ?? $t->priority,
                    'updated_by_id' => $user->id,
                ];
                // A new deadline (or a new person) re-arms the overdue reminder -
                // it used to stay silenced by the stamp from the old deadline.
                if ($tDueChanged) {
                    $updates['delay_notified_at'] = null;
                }
                if ($reassignTo) {
                    $notes[] = 'Reassigned from ' . (optional($t->user)->getFullName() ?? 'nobody') . ' to ' . $reassignTo->getFullName() . '.';
                    $updates['user_id'] = $reassignTo->id;
                    $updates['delay_notified_at'] = null;
                }
                $t->update($updates);
                if ($tDueChanged) {
                    $notes[] = 'Due date changed from ' . $oldDue . ' to ' . ($t->dueLabel() ?? 'no due date') . '.';
                }

                // Not scoped by TaskAccess, same reasoning as store() - CC is
                // watching, not being assigned work. Excludes the (possibly just
                // reassigned) owner - being CC'd on your own task is meaningless.
                if ($request->has('cc_user_ids')) {
                    $ccIds = collect($validated['cc_user_ids'] ?? [])->unique()->reject(fn ($id) => (int) $id === (int) $t->user_id);
                    $t->ccUsers()->sync($ccIds->mapWithKeys(fn ($id) => [$id => ['created_by_id' => $user->id]])->all());
                }

                // Shown in the task's updates, so the assignee and the CC'd
                // people can see what changed and who changed it.
                if ($notes) {
                    $t->taskUpdates()->create([
                        'comment'       => implode(' ', $notes),
                        'update_type'   => 'comment',
                        'is_admin'      => true,
                        'created_by_id' => $user->id,
                        'updated_by_id' => $user->id,
                    ]);
                    if (!$reassignTo && (int) $t->user_id !== (int) $user->id) {
                        $notifyList[] = ['taskId' => $t->id, 'summary' => implode(' ', $notes)];
                    }
                }
            }
        });

        // The new person is told, exactly as if it had been assigned to them.
        if ($reassignTo) {
            $taskId = $task->id;
            app()->terminating(function () use ($taskId) {
                $fresh = Task::with(['user', 'assignedBy'])->find($taskId);
                try {
                    $fresh?->user?->notify(new TaskAssigned($fresh));
                } catch (\Throwable $e) {
                    Log::warning("Task-reassigned notify failed for task {$taskId}: " . $e->getMessage());
                }
            });
        }

        // Everyone whose own task's title/due date/priority changed (not
        // reassigned) is told what changed.
        if ($notifyList) {
            $actorId = $user->id;
            app()->terminating(function () use ($notifyList, $actorId) {
                $actor = User::find($actorId);
                foreach ($notifyList as $entry) {
                    try {
                        $fresh = Task::with('user')->find($entry['taskId']);
                        $fresh?->user?->notify(new \App\Notifications\Task\TaskDetailsUpdated($fresh, $entry['summary'], $actor));
                    } catch (\Throwable $e) {
                        Log::warning("Task-details-updated notify failed for task {$entry['taskId']}: " . $e->getMessage());
                    }
                }
            });
        }

        $message = $targets->count() > 1 ? "Updated for all {$targets->count()} people." : 'Task updated successfully.';

        return response()->json(['success' => true, 'message' => $message]);
    }

    /** What the View popup shows for one person's task - built here so the list row and an expanded group row agree. */
    public static function viewPayload(Task $t): array
    {
        $timing = $t->completion_timing;
        $latest = $t->relationLoaded('taskUpdates') ? $t->taskUpdates->first() : null;

        return [
            'title'              => $t->title,
            'description'        => $t->description,
            'priority'           => ucfirst($t->priority),
            'due'                => $t->dueLabel('d/m/Y') ?? '—',
            'expected'           => optional($t->completion_expected_date)->format('d/m/Y'),
            'assignee'           => optional($t->user)->getFullName() ?? '—',
            'assignedBy'         => optional($t->assignedBy)->getFullName() ?? '—',
            'status'             => $t->status === 'objection' ? 'Objection raised' : (Task::PROGRESS_STATUSES[$t->status] ?? ucfirst(str_replace('_', ' ', $t->status))),
            'objection'          => $t->objection_notes,
            'attachment'         => $t->attachment_path ? route('tasks.attachment', $t->id) : null,
            'updateAttachment'   => optional($latest)->file_url ? route('task-updates.attachment', $latest->id) : null,
            'assignedOn'         => $t->created_at?->format('d/m/Y h:i A') ?? '—',
            'actualCompletion'   => $t->completed_at ? $t->completed_at->format('d/m/Y h:i A') : '—',
            'timingLabel'        => ['early' => 'Completed early', 'on_time' => 'Completed on time', 'late' => 'Completed late'][$timing] ?? null,
            'timingClass'        => ['early' => 'badge-hitech-success', 'on_time' => 'badge-brand-soft', 'late' => 'badge-hitech-danger'][$timing] ?? null,
            'ccNames'            => $t->relationLoaded('ccUsers') ? $t->ccUsers->map(fn ($u) => $u->getFullName())->implode(', ') : '',
            'threadUrl'          => route('my-tasks.thread', $t->id),
        ];
    }

    /**
     * Admin/HR/manager sends a task back to whoever it is assigned to, with a
     * reason - the same shape as Travel Claim's objection flow. Status moves
     * to 'objection' so it is unmistakable from a plain rejection; the
     * assignee resubmits via MyTasksController::resubmit(), which is the only
     * path back out of this status.
     */
    public function objection(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $user = auth()->user();

        if (!TaskAccess::canManage($user, $task)) {
            abort(403, 'You cannot manage a task outside your team.');
        }

        $validated = $request->validate([
            'reason'     => 'required|string|max:2000',
            'attachment' => TaskAttachment::RULES,
        ]);

        // An objection sends finished work back. The page only offers it on a
        // completed task, but nothing checked that here, and the assignee's
        // only way out of 'objection' is Fix & Resubmit.
        if (!in_array($task->status, ['completed', 'resolved'], true)) {
            return response()->json(['success' => false, 'message' => 'An objection can only be raised on a completed task.'], 422);
        }

        $task->update([
            'status'          => 'objection',
            'objection_notes' => $validated['reason'],
            'objected_by_id'  => $user->id,
            'objected_at'     => now(),
            'updated_by_id'   => $user->id,
        ]);

        $task->taskUpdates()->create([
            'comment'       => $validated['reason'],
            'file_url'      => $request->hasFile('attachment')
                ? TaskAttachment::store($request->file('attachment'), 'task-attachments')
                : null,
            'update_type'   => 'objection',
            'is_admin'      => true,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        // Same pattern store() uses - a slow/unreachable mail host must not
        // make the admin wait for their objection to save. app()->terminating()
        // rather than dispatch(...)->afterResponse() -- see store() above for
        // why: the latter's closure serialization breaks on prod's obfuscated
        // source.
        app()->terminating(function () use ($task) {
            try {
                $fresh = $task->fresh(['user', 'objectedBy']);
                $fresh->user?->notify(new TaskObjection($fresh));
            } catch (\Throwable $e) {
                Log::warning("Task-objection notify failed for task {$task->id}: " . $e->getMessage());
            }
        });

        return response()->json(['success' => true, 'message' => 'Objection raised - sent back to ' . (optional($task->user)->getFullName() ?? 'the assignee') . '.']);
    }
}
