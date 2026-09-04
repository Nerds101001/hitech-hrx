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
        ]);
    }

    /**
     * Everything assigned to this person via Task Management, bucketed for
     * the page. Task::forUser() is the only place that scopes by user_id —
     * there is no path here that can return someone else's task.
     */
    public static function assignedTaskBuckets($user): array
    {
        $empty = ['all' => collect(), 'upcoming' => collect(), 'pending' => collect(), 'completed' => collect(), 'overdue' => collect()];
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

        return [
            'all'       => $tasks,
            'overdue'   => $tasks->filter(fn ($t) => $t->is_overdue)->values(),
            'completed' => $tasks->filter(fn ($t) => $t->is_completed)->values(),
            'pending'   => $tasks->filter(fn ($t) => !$t->is_completed && !$t->is_overdue && $t->due_date && $t->due_date->isToday())->values(),
            'upcoming'  => $tasks->filter(fn ($t) => !$t->is_completed && !$t->is_overdue && (!$t->due_date || $t->due_date->isFuture()))->values(),
        ];
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
