<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSetupTask;
use App\Models\ExitClearance;
use App\Models\Task;
use App\Notifications\Task\TaskReplied;
use App\Notifications\Task\TaskStatusUpdated;
use App\Support\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "My Tasks" — one place where any user finds the work that is actually
 * waiting on them, whatever their role. Each source decides for itself
 * whether the signed-in user owns that item, so a user only ever sees their
 * own queue.
 */
class MyTasksController extends Controller
{
    public function index()
    {
        return view('tenant.my-tasks.index', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'groups'      => self::forUser(auth()->user()),
            'taskBuckets' => self::assignedTaskBuckets(auth()->user()),
            'upcomingSchedules' => self::upcomingSchedulePreviews(auth()->user()),
        ]);
    }

    /**
     * Everything assigned to this person via Task Management, bucketed for
     * the page. Task::forUser() is the only place that scopes by user_id —
     * there is no path here that can return someone else's task.
     */
    public static function assignedTaskBuckets($user): array
    {
        $empty = ['all' => collect(), 'upcoming' => collect(), 'pending' => collect(), 'completed' => collect(), 'overdue' => collect(), 'watching' => collect()];
        if (!$user) {
            return $empty;
        }

        // Highest priority first, then nearest due date, undated tasks
        // pushed to the end rather than sorting first as NULL otherwise
        // would — same ordering Task Management uses, so the two pages never
        // disagree about what "most urgent" means.
        // Latest update (with who wrote it) and the CC list come along: both
        // are shown on the rows, not only inside the Updates popup.
        $latestUpdate = fn ($q) => $q->with('createdBy:id,first_name,last_name')->latest()->limit(1);
        $tasks = Task::forUser($user->id)
            ->with(['assignedBy', 'ccUsers', 'taskUpdates' => $latestUpdate])
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get();

        // Someone else's work this person is tracking, not their own to
        // complete: either CC'd on it, or they're the one who assigned it
        // to someone else. Kept out of every other bucket above: mixing a
        // watched row into "pending"/"overdue" would make it look like this
        // person's own task is late, when it's actually someone else's.
        $watching = Task::ccTo($user->id)
            ->with(['assignedBy', 'user', 'ccUsers', 'taskUpdates' => $latestUpdate])
            ->get()
            ->concat(
                Task::where('assigned_by_id', $user->id)->where('user_id', '!=', $user->id)
                    ->with(['assignedBy', 'user', 'ccUsers', 'taskUpdates' => $latestUpdate])
                    ->get()
            )
            // CC'd on a task I also assigned - once, not twice.
            ->unique('id')
            // Undated tasks pushed to the end, matching every other bucket's
            // ordering here - Collection::sortBy alone would put a null
            // due_date first, the opposite of what orderByRaw('due_date IS
            // NULL') does at the SQL level for a single query.
            ->sortBy(fn ($t) => [$t->due_date === null ? 1 : 0, $t->due_date])
            ->values();

        // Last submitted first here - once a task is done, what matters is
        // what you just finished, not its old due date/priority.
        $completed = $tasks->filter(fn ($t) => $t->is_completed)->sortByDesc('completed_at')->values();

        return [
            // Still-open work leads (priority, then nearest due date) with
            // completed tasks trailing behind it, most recently finished
            // first - otherwise a task finished weeks ago but due date 09 Sep
            // outranks one due today just because it shares the same
            // priority, which is what made the "All" tab look unsorted.
            'all'       => $tasks->filter(fn ($t) => !$t->is_completed)->values()->concat($completed)->values(),
            'overdue'   => $tasks->filter(fn ($t) => $t->is_overdue)->values(),
            'completed' => $completed,
            'pending'   => $tasks->filter(fn ($t) => !$t->is_completed && !$t->is_overdue && $t->due_date && $t->due_date->isToday())->values(),
            'upcoming'  => $tasks->filter(fn ($t) => !$t->is_completed && !$t->is_overdue && (!$t->due_date || $t->due_date->isFuture()))->values(),
            'watching'  => $watching,
        ];
    }

    /**
     * Active recurring schedules assigned to this person that haven't fired
     * yet - a TaskSchedule only ever creates its Task row on the day it's
     * actually due (see TaskScheduleService::fireIfDue), never in advance,
     * so without this a schedule due next Tuesday is invisible until
     * Tuesday itself. Deliberately separate from assignedTaskBuckets(): a
     * preview like this has no real Task row behind it (no id, no
     * complete/resubmit action), so it must never be mixed into a bucket
     * whose rows drive those buttons.
     *
     * Filtered in PHP rather than whereJsonContains(): existing schedules
     * have target_user_ids stored as strings (e.g. ["266","9","246"]) while
     * $user->id is an int, and JSON_CONTAINS is type-strict — it silently
     * matches nothing for every schedule when the types don't line up.
     * in_array()'s default loose comparison handles both without caring
     * which one a given schedule happens to have.
     */
    public static function upcomingSchedulePreviews($user): \Illuminate\Support\Collection
    {
        if (!$user) {
            return collect();
        }

        return \App\Models\TaskSchedule::where('is_active', true)
            ->get()
            ->filter(fn ($s) => in_array($user->id, $s->target_user_ids ?? []))
            ->map(fn ($s) => ($next = $s->nextOccurrence()) ? (object) [
                'title' => $s->title,
                'due_date' => $next,
                'recurrence_label' => $s->recurrenceLabel(),
            ] : null)
            ->filter()
            ->sortBy('due_date')
            ->values();
    }

    /**
     * Mark one of MY OWN tasks complete, with an optional remark. The only
     * ownership check that matters is right here: user_id must be this
     * person, or the lookup 404s before anything is touched.
     */
    public function complete(Request $request, Task $task)
    {
        if ($task->user_id !== auth()->id()) {
            abort(403, 'This is not your task.');
        }

        $validated = $request->validate([
            'remarks'    => 'nullable|string|max:2000',
            'attachment' => TaskAttachment::RULES,
        ]);

        if ($task->status === 'objection') {
            return response()->json(['success' => false, 'message' => 'This task was sent back - use Fix & Resubmit.'], 422);
        }
        if ($task->is_completed) {
            return response()->json(['success' => false, 'message' => 'This task is already completed.'], 422);
        }
        // Same guard as updateStatus(): this endpoint (used by the dashboard
        // card and the photo upload) used to skip it, so a 90-Day Goals task
        // could be ticked off without the data it asks for.
        if ($reason = \App\Services\NinetyDayGoalService::taskBlockReason($task)) {
            return response()->json(['success' => false, 'message' => $reason], 422);
        }

        $task->status = 'completed';
        $task->completed_at = now();
        $task->completion_expected_date = null;
        $task->save();

        $task->taskUpdates()->create([
            'comment'        => $validated['remarks'] ?? null,
            'file_url'       => $request->hasFile('attachment')
                ? TaskAttachment::store($request->file('attachment'), 'task-attachments')
                : null,
            'update_type'    => 'complete',
            'is_admin'       => false,
            'created_by_id'  => auth()->id(),
            'updated_by_id'  => auth()->id(),
        ]);

        self::notifyStatusChange($task, 'Completed', $validated['remarks'] ?? null);

        return response()->json(['success' => true, 'message' => 'Task marked complete.']);
    }

    /**
     * The only way out of 'objection' - the assignee has fixed whatever the
     * objection called out and is resubmitting it. Goes straight back to
     * completed, not in_progress: an objection is only ever raised on a task
     * that was already completed (see TaskManagementController::objection()'s
     * status gate), so the fix itself IS the resubmission - a second, separate
     * Mark Complete step would just be re-doing the same click for no reason.
     * If the objection turns out not to actually be fixed, raising it again
     * goes through TaskManagementController::objection() from there same as
     * any other completed task.
     */
    public function resubmit(Request $request, Task $task)
    {
        if ($task->user_id !== auth()->id()) {
            abort(403, 'This is not your task.');
        }

        if ($task->status !== 'objection') {
            abort(422, 'This task has no objection to resubmit against.');
        }

        $validated = $request->validate([
            'remarks'    => 'nullable|string|max:2000',
            'attachment' => TaskAttachment::RULES,
        ]);

        $task->status = 'completed';
        $task->completed_at = now();
        $task->save();

        $task->taskUpdates()->create([
            'comment'        => $validated['remarks'] ?? null,
            'file_url'       => $request->hasFile('attachment')
                ? TaskAttachment::store($request->file('attachment'), 'task-attachments')
                : null,
            'update_type'    => 'resubmit',
            'is_admin'       => false,
            'created_by_id'  => auth()->id(),
            'updated_by_id'  => auth()->id(),
        ]);

        self::notifyStatusChange($task, 'Resubmitted', $validated['remarks'] ?? null);

        return response()->json(['success' => true, 'message' => 'Resubmitted.']);
    }

    /**
     * Move MY OWN task between Pending / Partially Done / Completed, and
     * optionally set (or clear) when I actually expect to finish it. Only
     * the assignee can do this — the assigner and anyone CC'd can watch and
     * reply (see addReply()), but not change status on someone else's
     * behalf.
     */
    public function updateStatus(Request $request, Task $task)
    {
        if ($task->user_id !== auth()->id()) {
            abort(403, 'This is not your task.');
        }

        $validated = $request->validate([
            'status'                    => 'required|in:' . implode(',', array_keys(Task::PROGRESS_STATUSES)),
            'completion_expected_date'  => 'nullable|date|after_or_equal:today',
            'remarks'                   => 'nullable|string|max:2000',
        ]);

        if ($validated['status'] === 'completed' && ($reason = \App\Services\NinetyDayGoalService::taskBlockReason($task))) {
            return response()->json(['success' => false, 'message' => $reason], 422);
        }

        $task->status = $validated['status'];
        $task->completed_at = $validated['status'] === 'completed' ? now() : null;
        // Clearing the reminder guard on any status change, not just when a
        // new date is set: an expected date that already fired its
        // reminder and then got pushed out (or cleared) must be free to
        // notify again against whatever now stands, not stay silenced by a
        // stamp from the old date.
        $task->completion_expected_date = $validated['status'] === 'completed'
            ? null
            : ($validated['completion_expected_date'] ?? $task->completion_expected_date);
        $task->expected_date_notified_at = null;
        $task->save();

        $task->taskUpdates()->create([
            'comment'       => $validated['remarks'] ?? null,
            'update_type'   => $validated['status'] === 'completed' ? 'complete' : 'comment',
            'is_admin'      => false,
            'created_by_id' => auth()->id(),
            'updated_by_id' => auth()->id(),
        ]);

        self::notifyStatusChange($task, Task::PROGRESS_STATUSES[$validated['status']], $validated['remarks'] ?? null);

        return response()->json([
            'success' => true,
            'message' => Task::PROGRESS_STATUSES[$validated['status']] . '.',
        ]);
    }

    /**
     * A comment that doesn't touch status — how the assigner or a CC'd
     * co-owner follows up, and how the assignee can answer without that
     * being read as "I'm done." isVisibleTo() is the one gate: assignee,
     * assigner, or CC'd, nobody else.
     */
    public function addReply(Request $request, Task $task)
    {
        if (!$task->canBeViewedBy(auth()->user())) {
            abort(403, 'You do not have access to this task.');
        }

        $validated = $request->validate([
            'comment'    => 'required|string|max:2000',
            'attachment' => TaskAttachment::RULES,
        ]);

        $update = $task->taskUpdates()->create([
            'comment'       => $validated['comment'],
            'file_url'      => $request->hasFile('attachment')
                ? TaskAttachment::store($request->file('attachment'), 'task-attachments')
                : null,
            'update_type'   => 'comment',
            'is_admin'      => auth()->id() !== $task->user_id,
            'created_by_id' => auth()->id(),
            'updated_by_id' => auth()->id(),
        ]);

        self::notifyReply($task, $update);

        return response()->json(['success' => true, 'update' => [
            'id'         => $update->id,
            'comment'    => $update->comment,
            'created_by' => auth()->user()->getFullName(),
            'created_at' => $update->created_at->format('d M Y, h:i A'),
        ]]);
    }

    /**
     * The full update thread for one task - same canBeViewedBy() gate as
     * addReply(): the assignee, assigner, CC'd people, and whoever manages
     * the task on Task Management (admin/HR, the assignee's manager).
     */
    public function thread(Task $task)
    {
        if (!$task->canBeViewedBy(auth()->user())) {
            abort(403, 'You do not have access to this task.');
        }

        $task->load(['assignedBy', 'user', 'ccUsers', 'taskUpdates' => fn ($q) => $q->with('createdBy')->oldest()]);

        return response()->json([
            'task' => [
                'id'                        => $task->id,
                'title'                     => $task->title,
                'description'               => $task->description,
                'status'                    => $task->status,
                'status_label'              => $task->status === 'objection' ? 'Objection raised' : (Task::PROGRESS_STATUSES[$task->status] ?? ucfirst($task->status)),
                'priority'                  => ucfirst((string) $task->priority),
                'assigned_by'               => optional($task->assignedBy)->getFullName(),
                'assignee'                  => optional($task->user)->getFullName(),
                'due_date'                  => $task->dueLabel(),
                'completion_expected_date'  => optional($task->completion_expected_date)->format('d M Y'),
                'is_overdue'                => $task->is_overdue,
                'cc_users'                  => $task->ccUsers->map(fn ($u) => $u->getFullName())->values(),
            ],
            'updates' => $task->taskUpdates->map(fn ($u) => [
                'id'         => $u->id,
                'comment'    => $u->comment,
                'type'       => $u->update_type,
                'file_url'   => $u->file_url ? route('task-updates.attachment', $u->id) : null,
                'created_by' => optional($u->createdBy)->getFullName() ?? '—',
                'created_at' => $u->created_at->format('d M Y, h:i A'),
                // Drives which side of the WhatsApp-style thread a bubble
                // renders on — the viewer's own messages right, everyone
                // else's left, same convention as a chat app.
                'is_own'     => (int) $u->created_by_id === (int) auth()->id(),
            ])->values(),
        ]);
    }

    /**
     * Everything outstanding for a user, grouped by kind.
     *
     * Static so the sidebar badge and the dashboard card can ask the same
     * question without duplicating the rules.
     */
    public static function forUser($user): array
    {
        if (!$user) {
            return [];
        }

        $groups = [];

        // ---- Attendance setup (accounts) --------------------------------
        if ($user->hasRole(['accounts', 'admin', 'Admin', 'hr', 'HR'])
            && Schema::hasTable('employee_setup_tasks')) {
            try {
                $setup = EmployeeSetupTask::pending()
                    ->with(['user.department', 'user.designation'])
                    ->orderBy('created_at')->get();

                if ($setup->isNotEmpty()) {
                    $groups[] = [
                        'key'    => 'setup',
                        'title'  => 'Attendance Setup',
                        'sub'    => 'Record employee ID and biometric enrolment so payroll can start',
                        'icon'   => 'bx-id-card',
                        'tone'   => 'primary',
                        'action' => url('/employee-setup'),
                        'items'  => $setup->map(function ($t) {
                            $meta = trim(implode(' · ', array_filter([
                                optional(optional($t->user)->designation)->name,
                                optional(optional($t->user)->department)->name,
                            ])));

                            return [
                                'title' => optional($t->user)->getFullName() ?? 'Employee',
                                'meta'  => $meta,
                                'tags'  => array_values(array_filter([
                                    $t->needs_emp_id ? 'Employee ID' : null,
                                    $t->needs_biometric ? 'Biometric' : 'Biometric exempt',
                                ])),
                                'url'   => url('/employee-setup'),
                                // Completed in a dialog on this page — sending
                                // the user off to the queue page just to come
                                // back was the long way round.
                                'modal' => [
                                    'id'        => $t->id,
                                    'name'      => optional($t->user)->getFullName() ?? 'Employee',
                                    'meta'      => $meta,
                                    'emp'       => optional($t->user)->code,
                                    'needs_emp' => $t->needs_emp_id ? 1 : 0,
                                    'needs_bio' => $t->needs_biometric ? 1 : 0,
                                ],
                            ];
                        })->all(),
                    ];
                }
            } catch (\Throwable $e) { /* module not ready */ }
        }

        // ---- Exit clearances this user can actually sign ------------------
        if (Schema::hasTable('exit_clearances')) {
            try {
                $mine = ExitClearance::pending()->with('user')->get()
                    ->filter(fn($c) => $c->isSignableBy($user));

                if ($mine->isNotEmpty()) {
                    $groups[] = [
                        'key'    => 'noc',
                        'title'  => 'Exit Clearance (NOC)',
                        'sub'    => 'Confirm there are no dues or pending items from your side',
                        'icon'   => 'bx-user-x',
                        'tone'   => 'danger',
                        'action' => url('/exit-clearance'),
                        'items'  => $mine->map(fn($c) => [
                            'title' => optional($c->user)->getFullName() ?? 'Employee',
                            'meta'  => $c->label . ' clearance · ' . ucfirst($c->exit_type),
                            'tags'  => [$c->label],
                            'url'   => url('/exit-clearance'),
                        ])->values()->all(),
                    ];
                }
            } catch (\Throwable $e) { /* module not ready */ }
        }

        return $groups;
    }

    /** Total outstanding items, for the sidebar badge. */
    public static function countFor($user): int
    {
        return collect(self::forUser($user))->sum(fn($g) => count($g['items']));
    }

    /**
     * Tell the person who assigned this task that its status just moved.
     * Every caller here is the assignee acting on their own task, so the
     * counterpart is always the assigner - unless they assigned it to
     * themselves, in which case there is no one else to tell.
     * app()->terminating() rather than dispatch(...)->afterResponse(): a
     * slow mail host must not make the status update wait, and the latter's
     * closure serialization breaks on prod's obfuscated source (same reason
     * TaskManagementController::objection() uses this pattern).
     */
    private static function notifyStatusChange(Task $task, string $statusLabel, ?string $remarks): void
    {
        $taskId = $task->id;
        $actorId = auth()->id();
        app()->terminating(function () use ($taskId, $statusLabel, $remarks, $actorId) {
            try {
                $fresh = Task::with(['assignedBy'])->find($taskId);
                $actor = \App\Models\User::find($actorId);
                if ($fresh && $fresh->assignedBy && (int) $fresh->assigned_by_id !== $actorId) {
                    $fresh->assignedBy->notify(new TaskStatusUpdated($fresh, $statusLabel, $actor, $remarks));
                }
            } catch (\Throwable $e) {
                Log::warning("Task status-change notify failed for task {$taskId}: " . $e->getMessage());
            }
        });
    }

    /**
     * Tell the other side of the thread a reply landed - the assignee's
     * reply goes to the assigner, and anyone else's (assigner or a CC'd
     * co-owner) goes to the assignee, since they're the one most likely
     * waiting on an answer. Never notifies the person who just replied.
     */
    private static function notifyReply(Task $task, \App\Models\TaskUpdate $update): void
    {
        $taskId = $task->id;
        $updateId = $update->id;
        $actorId = auth()->id();
        app()->terminating(function () use ($taskId, $updateId, $actorId) {
            try {
                $fresh = Task::with(['user', 'assignedBy'])->find($taskId);
                $freshUpdate = \App\Models\TaskUpdate::with('createdBy')->find($updateId);
                if (!$fresh || !$freshUpdate) {
                    return;
                }
                $recipients = collect([$fresh->user, $fresh->assignedBy])
                    ->filter()
                    ->unique('id')
                    ->reject(fn ($u) => (int) $u->id === (int) $actorId);
                foreach ($recipients as $recipient) {
                    $recipient->notify(new TaskReplied($fresh, $freshUpdate));
                }
            } catch (\Throwable $e) {
                Log::warning("Task reply notify failed for task {$taskId}: " . $e->getMessage());
            }
        });
    }
}
