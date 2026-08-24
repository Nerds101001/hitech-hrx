<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Notifications\Task\TaskAssigned;
use App\Support\TaskAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * The original single-target task/KRA endpoints, still used by the "Add
 * Task" modal on an individual employee's profile page.
 *
 * Bulk assignment (multiple employees or whole departments at once) lives in
 * TaskManagementController — that one always creates via TaskAccess-scoped
 * targets. This controller predates that scoping and originally had none at
 * all: any signed-in admin/hr/accounts/manager/tasks.manage user could edit
 * or delete ANY task in the system by guessing its id, including one that
 * belonged to a completely different manager's team. Every method below now
 * runs through the same TaskAccess check the new portal uses.
 */
class TaskController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'userId' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date|after_or_equal:today',
            'type' => 'required|in:open,site_based,client_based',
        ]);

        $allowedIds = TaskAccess::allowedUserIds(auth()->user());
        if ($allowedIds !== null && !in_array((int) $validated['userId'], $allowedIds, true)) {
            return response()->json(['success' => false, 'message' => 'You can only assign tasks to your own team.'], 403);
        }

        try {
            $task = Task::create([
                'user_id' => $validated['userId'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'type' => $validated['type'],
                'assigned_by_id' => auth()->id(),
                'status' => 'new',
                'for_date' => now(), // Required field in migration
                'created_by_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id ?? null,
            ]);

            // Deferred until after the response — see the same fix in
            // TaskManagementController::store() for why: this mailer's real
            // SMTP host is slow to unreachable from a dev box, and sending it
            // inline used to freeze this request on a task that had already
            // been created.
            $taskId = $task->id;
            dispatch(function () use ($taskId) {
                $t = Task::with('user')->find($taskId);
                try {
                    $t?->user?->notify(new TaskAssigned($t));
                } catch (\Throwable $e) {
                    Log::warning("Task-assigned notify failed for task {$taskId}: " . $e->getMessage());
                }
            })->afterResponse();

            return response()->json(['success' => true, 'message' => 'Task assigned successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to assign task: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        if (!TaskAccess::canManage(auth()->user(), $task)) {
            return response()->json(['success' => false, 'message' => 'You cannot manage a task outside your team.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date|after_or_equal:today',
            'type' => 'required|in:open,site_based,client_based',
        ]);

        try {
            $task->title = $validated['title'];
            $task->description = $validated['description'] ?? null;
            $task->due_date = $validated['due_date'] ?? null;
            $task->type = $validated['type'];
            $task->updated_by_id = auth()->id();
            $task->save();

            return response()->json(['success' => true, 'message' => 'Task updated successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update task: ' . $e->getMessage()], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        if (!TaskAccess::canManage(auth()->user(), $task)) {
            return response()->json(['success' => false, 'message' => 'You cannot manage a task outside your team.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:new,in_progress,completed,cancelled,hold,rejected,reassigned,reopened,resolved,closed',
        ]);

        try {
            $task->status = $validated['status'];
            if (in_array($validated['status'], Task::CLOSED_STATUSES, true)) {
                $task->completed_at = $task->completed_at ?: now();
            } else {
                $task->completed_at = null;
            }
            $task->save();

            return response()->json(['success' => true, 'message' => 'Task status updated to ' . $validated['status']]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update task status.'], 500);
        }
    }

    public function destroy($id)
    {
        $task = Task::findOrFail($id);
        if (!TaskAccess::canManage(auth()->user(), $task)) {
            return response()->json(['success' => false, 'message' => 'You cannot manage a task outside your team.'], 403);
        }

        try {
            $task->delete();

            return response()->json(['success' => true, 'message' => 'Task deleted successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete task.'], 500);
        }
    }
}
