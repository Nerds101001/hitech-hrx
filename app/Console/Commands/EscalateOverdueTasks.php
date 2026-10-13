<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\Task\EscalationDeskDigest;
use App\Notifications\Task\TaskEscalated;
use App\Services\TaskEscalationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * A task still open config('tasks.escalate_after_days') days past its
 * deadline is escalated, once, to the assignee's manager - the step beyond
 * the one-time overdue notice the assignee already got. Recipient and status
 * logic live in TaskEscalationService, shared with the Task Management
 * "Escalations" tab so the screen never disagrees with who actually gets
 * notified.
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
        // Collected for the ONE consolidated email at the end - every
        // escalation this run, regardless of which manager it went to.
        $deskRows = [];
        foreach ($tasks as $task) {
            $recipient = TaskEscalationService::recipientFor($task);
            $daysOverdue = TaskEscalationService::daysOverdue($task);
            if ($recipient) {
                try {
                    $recipient->notify(new TaskEscalated($task, $daysOverdue));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning("Escalation notify failed for task {$task->id}: " . $e->getMessage());
                }
                $deskRows[] = [
                    'taskId'       => $task->id,
                    'taskTitle'    => $task->title,
                    'assigneeName' => optional($task->user)->getFullName() ?? 'Unknown',
                    'managerName'  => $recipient->getFullName(),
                    'daysOverdue'  => $daysOverdue,
                    'dueLabel'     => $task->dueLabel(),
                ];
            } else {
                $noManager++;
            }
            // Stamped either way - no manager today should not mean it is
            // re-examined and re-logged on every future run.
            $task->update(['escalated_at' => now()]);
        }

        // One email for the whole run, worst-first, instead of a separate
        // email per manager per task - the per-manager in-app/push above
        // already told each of them about their own report.
        if ($deskRows) {
            usort($deskRows, fn ($a, $b) => $b['daysOverdue'] <=> $a['daysOverdue']);
            $deskEmails = config('tasks.escalation_desk_emails', []);
            foreach ($deskEmails as $email) {
                try {
                    Notification::route('mail', $email)->notify(new EscalationDeskDigest($deskRows));
                } catch (\Throwable $e) {
                    Log::warning("Escalation desk digest failed for {$email}: " . $e->getMessage());
                }
            }
        }

        $this->info("{$sent} escalated; {$noManager} had no manager to escalate to; {$tasks->count()} eligible; "
            . ($deskRows ? 'desk digest sent to ' . count(config('tasks.escalation_desk_emails', [])) . ' address(es).' : 'no desk digest (nothing escalated).'));
    }
}
