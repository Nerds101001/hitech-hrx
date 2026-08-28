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
use App\Services\TaskScheduleService;
use App\Support\TaskAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Task Management - where HR/admin/manager assign work to one person, a
 * hand-picked group, or whole departments at once.
 *
 * One Task row is created PER PERSON even for a department-wide assignment,
 * grouped by batch_id so this page can still show it as a single entry. That
 * is deliberate: it is what lets each person's own status, remarks and
 * overdue notification exist independently of everyone else's - the same
 * assignment finished by five people and still open with two others.
 *
 * Visibility is governed entirely by TaskAccess: admin/hr/tasks.manage see
 * and can target anyone; a manager or accounts only their own reports. This
 * controller never trusts a user_id/department_id from the request without
 * checking it against that scope first.
 */
class TaskManagementController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $allowedIds = TaskAccess::allowedUserIds($user);

        // Highest priority first, then nearest deadline — what actually
        // needs attention next — with undated tasks pushed to the end rather
        // than sorting first as NULL otherwise would, and newest-assigned as
        // the final tiebreaker. Same ordering My Tasks uses.
        $query = Task::with(['user.department', 'assignedBy'])
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at');

        if ($allowedIds !== null) {
            $query->whereIn('user_id', $allowedIds);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'overdue'    => $query->overdue(),
                'due_today'  => $query->whereDate('due_date', now()->toDateString())
                    ->whereNotIn('status', Task::CLOSED_STATUSES),
                'completed'  => $query->whereIn('status', Task::CLOSED_STATUSES),
                default      => $query->where('status', $request->status)
                    ->whereNotIn('status', Task::CLOSED_STATUSES),
            };
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

        $tasks = $query->paginate(20)->withQueryString();

        // One row per batch for the summary list, counted from the same
        // scoped query so a manager never sees a batch's true size if it
        // reaches beyond their own team.
        // reorder() first: the base query's ORDER BY created_at is not one of
        // the GROUP BY columns below, which MySQL's only_full_group_by mode
        // (the default here) rejects outright rather than just ignoring.
        $batchCounts = (clone $query)->reorder()->whereNotNull('batch_id')
            ->select('batch_id', DB::raw('count(*) as total'), DB::raw("sum(case when status in ('completed','cancelled','resolved','closed') then 1 else 0 end) as done"))
            ->groupBy('batch_id')
            ->pluck('total', 'batch_id');

        $departments = $allowedIds === null
            ? Department::cachedActive()
            : Department::whereIn('id', TaskAccess::allowedDepartmentIds($user) ?? [])->orderBy('name')->get();

        $employees = ($allowedIds === null ? User::where('status', UserAccountStatus::ACTIVE) : User::whereIn('id', $allowedIds ?? []))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'department_id']);

        $stats = [
            'total'      => (clone $query)->count(),
            'overdue'    => (clone $query)->overdue()->count(),
            'due_today'  => (clone $query)->whereDate('due_date', now()->toDateString())
                ->whereNotIn('status', Task::CLOSED_STATUSES)->count(),
            'completed'  => (clone $query)->whereIn('status', Task::CLOSED_STATUSES)->count(),
            'pending'    => (clone $query)->whereNotIn('status', Task::CLOSED_STATUSES)->count(),
        ];

        $scheduleQuery = TaskSchedule::where('is_active', true)->with('assignedBy');
        if ($allowedIds !== null) {
            $scheduleQuery->where('assigned_by_id', $user->id);
        }
        $schedules = $scheduleQuery->orderByDesc('created_at')->get();
        // Resolved once here rather than per-row in the view (N+1) — the id
        // list is small (schedule targets), so one query per page is fine.
        $allTargetIds = $schedules->flatMap(fn ($s) => $s->target_user_ids ?? [])->unique();
        $targetNames = User::whereIn('id', $allTargetIds)->get(['id', 'first_name', 'last_name'])->keyBy('id');

        return view('tenant.task-management.index', compact('tasks', 'departments', 'employees', 'stats', 'batchCounts', 'schedules', 'targetNames'));
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
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:users,id',
            'department_ids'   => 'nullable|array',
            'department_ids.*' => 'integer|exists:departments,id',
        ]);

        if (empty($validated['employee_ids']) && empty($validated['department_ids'])) {
            return response()->json(['success' => false, 'message' => 'Pick at least one employee or department.'], 422);
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
        $targetIds = $targetIds->unique()->values();

        // A manager/accounts assigner is confined to their own reports -
        // even if they hand-picked a user_id or a whole department outside
        // that, silently drop anyone not in scope rather than 403 the whole
        // batch, since a department pick legitimately mixes people they can
        // and cannot reach.
        $allowedIds = TaskAccess::allowedUserIds($user);
        $dropped = 0;
        if ($allowedIds !== null) {
            $before = $targetIds->count();
            $targetIds = $targetIds->intersect($allowedIds)->values();
            $dropped = $before - $targetIds->count();
        }

        if ($targetIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'None of the selected people are in your team.'], 422);
        }

        if (!empty($validated['interval'])) {
            return $this->storeSchedule($validated, $targetIds, $dropped, $user);
        }

        $batchId = (string) Str::uuid();
        $created = [];

        DB::transaction(function () use ($targetIds, $validated, $user, $batchId, &$created) {
            foreach ($targetIds as $uid) {
                $created[] = Task::create([
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
                    'created_by_id'  => $user->id,
                    'updated_by_id'  => $user->id,
                ]);
            }
        });

        // Sent AFTER the response goes out, not before. This mailer's real
        // SMTP host is slow (sometimes unreachable) from a dev box, and every
        // notify() here used to block the whole request on it — the tasks
        // were already committed, so "Assigning..." sat frozen on the button
        // for as long as the mail attempt hung, even though nothing was
        // actually still in progress.
        $createdIds = array_map(fn ($t) => $t->id, $created);
        dispatch(function () use ($createdIds) {
            foreach (Task::whereIn('id', $createdIds)->with('user')->get() as $task) {
                try {
                    $task->user?->notify(new TaskAssigned($task));
                } catch (\Throwable $e) {
                    // One broken address must not stop the rest of the batch
                    // from being notified.
                    Log::warning("Task-assigned notify failed for task {$task->id}: " . $e->getMessage());
                }
            }
        })->afterResponse();

        $message = count($created) . ' task(s) assigned.';
        if ($dropped > 0) {
            $message .= " {$dropped} selected person(s) were outside your team and skipped.";
        }

        return response()->json(['success' => true, 'message' => $message, 'batch_id' => $batchId, 'assigned' => count($created)]);
    }

    /**
     * The recurring path: one TaskSchedule row, target_user_ids snapshotted
     * once (already TaskAccess-scoped by the caller) rather than re-resolved
     * on every future firing — see the migration for why. Fires today's
     * occurrence right away if the chosen day matches, instead of making the
     * assigner wait for tomorrow's cron run to see anything happen.
     */
    private function storeSchedule(array $validated, \Illuminate\Support\Collection $targetIds, int $dropped, $user)
    {
        $schedule = TaskSchedule::create([
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'priority'        => $validated['priority'] ?? 'medium',
            'frequency'       => $validated['interval'],
            'weekday'         => $validated['interval'] === 'weekly' ? $validated['weekday'] : null,
            'day_of_month'    => $validated['interval'] === 'monthly' ? $validated['day_of_month'] : null,
            'target_user_ids' => $targetIds->values()->all(),
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
        if ($dropped > 0) {
            $message .= " {$dropped} selected person(s) were outside your team and skipped.";
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

    /** Everyone in one bulk-assignment batch and their own status. */
    public function showBatch(Request $request, string $batchId)
    {
        $user = auth()->user();
        $allowedIds = TaskAccess::allowedUserIds($user);

        $query = Task::with(['user.department', 'assignedBy', 'taskUpdates' => fn ($q) => $q->latest()->limit(1)])
            ->where('batch_id', $batchId);

        if ($allowedIds !== null) {
            $query->whereIn('user_id', $allowedIds);
        }

        $tasks = $query->orderBy('id')->get();

        if ($tasks->isEmpty()) {
            abort(404);
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

    /** Edit an existing task's own details — never who it belongs to (use reassignment for that, not this). */
    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);

        if (!TaskAccess::canManage(auth()->user(), $task)) {
            abort(403, 'You cannot manage a task outside your team.');
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date'    => 'nullable|date',
            'priority'    => 'nullable|in:' . implode(',', Task::PRIORITIES),
        ]);

        $task->update([
            'title'         => $validated['title'],
            'description'   => $validated['description'] ?? null,
            'due_date'      => $validated['due_date'] ?? null,
            'priority'      => $validated['priority'] ?? $task->priority,
            'updated_by_id' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'message' => 'Task updated successfully.']);
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
            'reason' => 'required|string|max:2000',
        ]);

        $task->update([
            'status'          => 'objection',
            'objection_notes' => $validated['reason'],
            'objected_by_id'  => $user->id,
            'objected_at'     => now(),
            'updated_by_id'   => $user->id,
        ]);

        $task->taskUpdates()->create([
            'comment'       => $validated['reason'],
            'update_type'   => 'objection',
            'is_admin'      => true,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        // Same afterResponse() pattern store() uses - a slow/unreachable mail
        // host must not make the admin wait for their objection to save.
        dispatch(function () use ($task) {
            try {
                $fresh = $task->fresh(['user', 'objectedBy']);
                $fresh->user?->notify(new TaskObjection($fresh));
            } catch (\Throwable $e) {
                Log::warning("Task-objection notify failed for task {$task->id}: " . $e->getMessage());
            }
        })->afterResponse();

        return response()->json(['success' => true, 'message' => 'Objection raised - sent back to ' . (optional($task->user)->getFullName() ?? 'the assignee') . '.']);
    }
}
