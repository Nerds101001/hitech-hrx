<?php

namespace App\Services;

use App\Enums\UserAccountStatus;
use App\Models\Task;
use App\Models\User;

/**
 * Who a task escalates to, and whether it has crossed the threshold - the
 * single source of truth both EscalateOverdueTasks (the actual email run)
 * and the Task Management "Escalations" tab read from, so what a manager
 * sees on screen can never disagree with who actually gets notified.
 */
class TaskEscalationService
{
    /**
     * The assignee's manager (via reporting_to_id, honouring delegation of a
     * vacant seat), falling back to whoever assigned the task. Never the
     * assignee themselves, and only an active account.
     */
    public static function recipientFor(Task $task): ?User
    {
        $candidates = [];
        if ($task->user && $task->user->reporting_to_id) {
            $manager = User::find($task->user->reporting_to_id);
            if ($manager) {
                $candidates[] = $manager->effectiveActor();
            }
        }
        if ($task->assigned_by_id) {
            $candidates[] = User::find($task->assigned_by_id);
        }

        foreach ($candidates as $c) {
            if ($c && (int) $c->id !== (int) $task->user_id
                && ($c->status instanceof UserAccountStatus ? $c->status === UserAccountStatus::ACTIVE : $c->status === 'active')) {
                return $c;
            }
        }

        return null;
    }

    /** Calendar days a task has been overdue, floor-clamped to at least 1 once it IS overdue. */
    public static function daysOverdue(Task $task): int
    {
        return max(1, (int) $task->due_deadline->startOfDay()->diffInDays(now()->startOfDay()));
    }

    /**
     * 'escalated' (already sent), 'will_escalate' (overdue long enough that
     * the next run will actually send it), 'pending' (overdue but not yet at
     * the threshold), or 'exempt' (past the threshold but its deadline fell
     * before config('tasks.escalate_from') - the storm guard that stops
     * escalation going live from dumping the whole pre-existing backlog on
     * managers; EscalateOverdueTasks will never pick these up, so they must
     * never be shown as "will escalate" here either).
     */
    public static function statusFor(Task $task): string
    {
        if ($task->escalated_at) {
            return 'escalated';
        }
        $threshold = max(1, (int) config('tasks.escalate_after_days', 2));
        if (self::daysOverdue($task) < $threshold) {
            return 'pending';
        }
        $from = config('tasks.escalate_from');
        if ($from && $task->due_deadline && $task->due_deadline->lt(\Carbon\Carbon::parse($from))) {
            return 'exempt';
        }

        return 'will_escalate';
    }
}
