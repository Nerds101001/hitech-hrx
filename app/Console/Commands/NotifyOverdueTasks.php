<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\Task\TaskOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyOverdueTasks extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Email/notify staff whose assigned task has passed its due date and is still open.';

    public function handle(): void
    {
        $overdue = Task::overdue()
            ->whereNull('delay_notified_at')
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($overdue as $task) {
            if (!$task->user) {
                continue;
            }

            try {
                $task->user->notify(new TaskOverdue($task));
                $sent++;
            } catch (\Throwable $e) {
                // One bad address must not stop the rest of the run.
                Log::warning("Overdue-task notify failed for task {$task->id}: " . $e->getMessage());
            }

            // Stamped regardless of whether the send succeeded, so a
            // permanently-broken address does not retry (and re-log) forever.
            $task->update(['delay_notified_at' => now()]);
        }

        $this->info("{$sent} of {$overdue->count()} overdue task notification(s) sent.");
    }
}
