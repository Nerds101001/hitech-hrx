<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\Task\TaskDueSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * A heads-up the day before a task is due (for a day-only deadline) or while
 * a timed deadline is within config('tasks.reminder_lead_hours') and still
 * ahead. Sent once per task - stamped like the overdue notice, so it never
 * nags. Recurring tasks are created ON their due day, so they never trigger
 * a "due tomorrow" nudge; only tasks given a future deadline do.
 */
class NotifyDueSoonTasks extends Command
{
    protected $signature = 'tasks:notify-due-soon';

    protected $description = 'Remind an assignee that their open task is due soon (once, before it is due).';

    public function handle(): void
    {
        $lead = (int) config('tasks.reminder_lead_hours', 24);
        $tomorrow = now()->addDay()->toDateString();

        $due = Task::whereNull('due_soon_notified_at')
            ->whereNotNull('due_date')
            ->whereNotIn('status', Task::CLOSED_STATUSES)
            ->where(function ($q) use ($tomorrow, $lead) {
                // day-only deadline due tomorrow...
                $q->where(fn ($d) => $d->whereRaw("TIME(due_date) = '00:00:00'")->whereDate('due_date', $tomorrow))
                    // ...or a timed deadline still ahead, within the lead window
                    ->orWhere(fn ($d) => $d->whereRaw("TIME(due_date) <> '00:00:00'")
                        ->where('due_date', '>', now())
                        ->where('due_date', '<=', now()->addHours($lead)));
            })
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($due as $task) {
            if ($task->user) {
                try {
                    $task->user->notify(new TaskDueSoon($task));
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning("Due-soon notify failed for task {$task->id}: " . $e->getMessage());
                }
            }
            // Stamped regardless, so a broken address does not retry forever.
            $task->update(['due_soon_notified_at' => now()]);
        }

        $this->info("{$sent} of {$due->count()} due-soon reminder(s) sent.");
    }
}
