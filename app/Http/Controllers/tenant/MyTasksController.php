<?php

namespace App\Http\Controllers\tenant;

use App\Http\Controllers\Controller;
use App\Models\EmployeeSetupTask;
use App\Models\ExitClearance;
use App\Models\Task;
use App\Support\TaskAttachment;
use Illuminate\Http\Request;
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
        $tasks = Task::forUser($user->id)
            ->with(['assignedBy', 'taskUpdates' => fn ($q) => $q->latest()->limit(1)])
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
            ->with(['assignedBy', 'user', 'taskUpdates' => fn ($q) => $q->latest()->limit(1)])
            ->get()
            ->concat(
                Task::where('assigned_by_id', $user->id)->where('user_id', '!=', $user->id)
                    ->with(['assignedBy', 'user', 'taskUpdates' => fn ($q) => $q->latest()->limit(1)])
                    ->get()
            )
            // Undated tasks pushed to the end, matching every other bucket's
            // ordering here - Collection::sortBy alone would put a null
            // due_date first, the opposite of what orderByRaw('due_date IS
            // NULL') does at the SQL level for a single query.
            ->sortBy(fn ($t) => [$t->due_date === null ? 1 : 0, $t->due_date])
            ->values();

        return [
            'all'       => $tasks,
            'overdue'   => $tasks->filter(fn ($t) => $t->is_overdue)->values(),
            'completed' => $tasks->filter(fn ($t) => $t->is_completed)->values(),
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

        $task->status = 'completed';
        $task->completed_at = now();
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
        if (!$task->isVisibleTo(auth()->id())) {
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

        return response()->json(['success' => true, 'update' => [
            'id'         => $update->id,
            'comment'    => $update->comment,
            'created_by' => auth()->user()->getFullName(),
            'created_at' => $update->created_at->format('d M Y, h:i A'),
        ]]);
    }

    /**
     * The full update thread for one task, for the assignee/assigner/CC'd
     * detail panel — same isVisibleTo() gate as addReply().
     */
    public function thread(Task $task)
    {
        if (!$task->isVisibleTo(auth()->id())) {
            abort(403, 'You do not have access to this task.');
        }

        $task->load(['assignedBy', 'user', 'ccUsers', 'taskUpdates' => fn ($q) => $q->with('createdBy')->oldest()]);

        return response()->json([
            'task' => [
                'id'                        => $task->id,
                'title'                     => $task->title,
                'description'               => $task->description,
                'status'                    => $task->status,
                'status_label'              => Task::PROGRESS_STATUSES[$task->status] ?? ucfirst($task->status),
                'assigned_by'               => optional($task->assignedBy)->getFullName(),
                'assignee'                  => optional($task->user)->getFullName(),
                'due_date'                  => optional($task->due_date)->format('d M Y'),
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
}
