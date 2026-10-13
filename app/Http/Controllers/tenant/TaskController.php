<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Notifications\Task\TaskAssigned;
use App\Notifications\Task\TaskDetailsUpdated;
use App\Notifications\Task\TaskStatusUpdated;
use App\Support\TaskAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
            //
            // app()->terminating() rather than dispatch(...)->afterResponse():
            // the latter still routes through the queue Dispatcher, which
            // serializes the closure via reflection on its *source file* --
            // and on prod this file is obfuscated, so that file doesn't
            // exist on disk as far as file_get_contents() is concerned. Every
            // single task assigned through this endpoint on prod hit that
            // failure and never sent its notification, silently (confirmed
            // from the real error emails: user 178 at /task-management, five
            // times in one day). terminating() just holds the closure in
            // memory and calls it directly -- no serialization at all.
            $taskId = $task->id;
            app()->terminating(function () use ($taskId) {
                $t = Task::with('user')->find($taskId);
                try {
                    $t?->user?->notify(new TaskAssigned($t));
                } catch (\Throwable $e) {
                    Log::warning("Task-assigned notify failed for task {$taskId}: " . $e->getMessage());
                }
            });

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
            $oldDue = $task->dueLabel() ?? 'no due date';
            $titleChanged = $task->title !== $validated['title'];
            $dueChanged = optional($task->due_date)->format('Y-m-d') !== $validated['due_date'];

            $task->title = $validated['title'];
            $task->description = $validated['description'] ?? null;
            $task->due_date = $validated['due_date'] ?? null;
            $task->type = $validated['type'];
            $task->updated_by_id = auth()->id();
            $task->save();

            $notes = [];
            if ($titleChanged) {
                $notes[] = 'Title changed to "' . $validated['title'] . '".';
            }
            if ($dueChanged) {
                $notes[] = 'Due date changed from ' . $oldDue . ' to ' . ($task->dueLabel() ?? 'no due date') . '.';
            }
            // Logged even when nothing listed above changed (description/type
            // can still differ) - same taskUpdates trail every other edit path
            // on this page leaves, which this endpoint never kept before.
            $task->taskUpdates()->create([
                'comment'       => $notes ? implode(' ', $notes) : 'Task details updated.',
                'update_type'   => 'comment',
                'is_admin'      => auth()->id() !== $task->user_id,
                'created_by_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
            ]);

            if ((int) $task->user_id !== auth()->id()) {
                $summary = $notes ? implode(' ', $notes) : 'description or type changed.';
                $taskId = $task->id;
                $actorId = auth()->id();
                app()->terminating(function () use ($taskId, $summary, $actorId) {
                    try {
                        $fresh = Task::with('user')->find($taskId);
                        $actor = \App\Models\User::find($actorId);
                        $fresh?->user?->notify(new TaskDetailsUpdated($fresh, $summary, $actor));
                    } catch (\Throwable $e) {
                        Log::warning("Task-details-updated notify failed for task {$taskId}: " . $e->getMessage());
                    }
                });
            }

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

        if (in_array($validated['status'], Task::CLOSED_STATUSES, true) && ($reason = \App\Services\NinetyDayGoalService::taskBlockReason($task))) {
            return response()->json(['success' => false, 'message' => $reason], 422);
        }

        try {
            $task->status = $validated['status'];
            if (in_array($validated['status'], Task::CLOSED_STATUSES, true)) {
                $task->completed_at = $task->completed_at ?: now();
            } else {
                $task->completed_at = null;
            }
            $task->save();

            $statusLabel = ucfirst(str_replace('_', ' ', $validated['status']));
            $task->taskUpdates()->create([
                'comment'       => 'Status changed to ' . $statusLabel . '.',
                'update_type'   => in_array($validated['status'], Task::CLOSED_STATUSES, true) ? 'complete' : 'comment',
                'is_admin'      => auth()->id() !== $task->user_id,
                'created_by_id' => auth()->id(),
                'updated_by_id' => auth()->id(),
            ]);

            if ((int) $task->user_id !== auth()->id()) {
                $taskId = $task->id;
                $actorId = auth()->id();
                app()->terminating(function () use ($taskId, $statusLabel, $actorId) {
                    try {
                        $fresh = Task::with('user')->find($taskId);
                        $actor = \App\Models\User::find($actorId);
                        $fresh?->user?->notify(new TaskStatusUpdated($fresh, $statusLabel, $actor));
                    } catch (\Throwable $e) {
                        Log::warning("Admin task status-change notify failed for task {$taskId}: " . $e->getMessage());
                    }
                });
            }

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

    /**
     * The reference image attached at creation time. Not route-gated to
     * admin/hr/tasks.manage like the rest of this controller - the assignee
     * needs to see their own task's attachment on My Tasks too, so the check
     * happens here instead: either it's your own task, or you're someone who
     * could manage it on the Task Management portal.
     */
    public function attachment($id)
    {
        $task = Task::findOrFail($id);

        // Anyone who can see the task's updates: CC'd people were shown the
        // link in the thread but got a 403 opening it.
        if (!$task->canBeViewedBy(auth()->user())) {
            abort(403, 'You cannot view this attachment.');
        }

        if (!$task->attachment_path) {
            abort(404);
        }

        return $this->streamAttachment($task->attachment_path);
    }

    /** Same idea, for the photo attached when a task was completed/objected to. */
    public function updateAttachment($id)
    {
        $update = TaskUpdate::with('task')->findOrFail($id);
        $task = $update->task;

        if (!$task || !$task->canBeViewedBy(auth()->user())) {
            abort(403, 'You cannot view this attachment.');
        }

        if (!$update->file_url) {
            abort(404);
        }

        return $this->streamAttachment($update->file_url);
    }

    private function streamAttachment(string $path)
    {
        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        $mimeType = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return response(Storage::disk('public')->get($path))
            ->header('Content-Type', $mimeType)
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=604800')
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    }
}
