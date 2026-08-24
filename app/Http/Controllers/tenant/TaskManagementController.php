<?php

namespace App\Http\Controllers\tenant;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Notifications\Task\TaskAssigned;
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

        $query = Task::with(['user.department', 'assignedBy'])
            ->orderByDesc('created_at');

        if ($allowedIds !== null) {
            $query->whereIn('user_id', $allowedIds);
        }

        if ($request->filled('department_id')) {
            $query->whereHas('user', fn ($q) => $q->where('department_id', $request->department_id));
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'overdue'   => $query->overdue(),
                'completed' => $query->whereIn('status', Task::CLOSED_STATUSES),
                default     => $query->where('status', $request->status)
                    ->whereNotIn('status', Task::CLOSED_STATUSES),
            };
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
            'total'     => (clone $query)->count(),
            'overdue'   => (clone $query)->overdue()->count(),
            'completed' => (clone $query)->whereIn('status', Task::CLOSED_STATUSES)->count(),
            'pending'   => (clone $query)->whereNotIn('status', Task::CLOSED_STATUSES)->count(),
        ];

        return view('tenant.task-management.index', compact('tasks', 'departments', 'employees', 'stats', 'batchCounts'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'due_date'       => 'nullable|date|after_or_equal:today',
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
                    'created_by_id'  => $user->id,
                    'updated_by_id'  => $user->id,
                ]);
            }
        });

        $notified = 0;
        foreach ($created as $task) {
            try {
                $task->user?->notify(new TaskAssigned($task));
                $notified++;
            } catch (\Throwable $e) {
                // One broken address must not roll back tasks that were
                // already committed for everyone else in the batch.
                Log::warning("Task-assigned notify failed for task {$task->id}: " . $e->getMessage());
            }
        }

        $message = count($created) . ' task(s) assigned.';
        if ($dropped > 0) {
            $message .= " {$dropped} selected person(s) were outside your team and skipped.";
        }

        return response()->json(['success' => true, 'message' => $message, 'batch_id' => $batchId, 'assigned' => count($created), 'notified' => $notified]);
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
}
