<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Notifications\Task\TaskEscalated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * A task still open config('tasks.escalate_after_days') days past its
 * deadline is escalated, once, to the assignee's manager - the step beyond
 * the one-time overdue notice the assignee already got.
 *
 * Storm guard: only tasks whose deadline fell on or after
 * config('tasks.escalate_from') are ever escalated. That stops the day this
 * went live from dumping the whole existing overdue backlog on managers;
 * only tasks that cross the line from the go-live date onward escalate.
 */
class EscalateOverdueTasks extends Command
{
    protected $signature = 'tasks:escalate-overdue';

    protected $description = 'Escalate a long-overdue open task to the assignee\'s manager (once).';

    public function handle(): void
    {
        $days = max(1, (int) config('tasks.escalate_after_days', 2));
        $from = config('tasks.escalate_from');
        $cutoff = now()->subDays($days);   // deadline must be at least this old

        $tasks = Task::query()
            ->whereNull('escalated_at')
            ->whereNotIn('status', Task::CLOSED_STATUSES)
            ->whereNotNull('due_date')
            ->whereRaw(Task::DEADLINE_SQL . ' < ?', [$cutoff])
            ->when($from, fn ($q) => $q->whereRaw(Task::DEADLINE_SQL . ' >= ?', [$from]))
            ->with('user')
            ->get();

        $sent = 0;
        $noManager = 0;
        foreach ($tasks as $task) {
            $recipient = $this->recipientFor($task);
            if ($recipient) {
                try {
                    $daysOverdue = (int) $task->due_deadline->startOfDay()->diffInDays(now()->startOfDay());
                    $recipient->notify(new TaskEscalated($task, max(1, $daysOverdue)));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning("Escalation notify failed for task {$task->id}: " . $e->getMessage());
                }
            } else {
                $noManager++;
            }
            // Stamped either way - no manager today should not mean it is
            // re-examined and re-logged on every future run.
            $task->update(['escalated_at' => now()]);
        }

        $this->info("{$sent} escalated; {$noManager} had no manager to escalate to; {$tasks->count()} eligible.");
    }

    /**
     * The assignee's manager (via reporting_to_id, honouring delegation of a
     * vacant seat), falling back to whoever assigned the task. Never the
     * assignee themselves, and only an active account.
     */
    private function recipientFor(Task $task): ?User
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
                && ($c->status instanceof \App\Enums\UserAccountStatus ? $c->status === \App\Enums\UserAccountStatus::ACTIVE : $c->status === 'active')) {
                return $c;
            }
        }

        return null;
    }
}
