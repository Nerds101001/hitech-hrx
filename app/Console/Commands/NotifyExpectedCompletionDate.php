<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\Task\TaskExpectedDateReached;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyExpectedCompletionDate extends Command
{
    protected $signature = 'tasks:notify-expected-date';

    protected $description = "Remind an assignee the day their own completion_expected_date arrives — the date they said they'd finish by, separate from the assigner's due_date.";

    public function handle(): void
    {
        $due = Task::whereDate('completion_expected_date', now()->toDateString())
            ->whereNull('expected_date_notified_at')
            ->whereNotIn('status', Task::CLOSED_STATUSES)
            ->with('user')
            ->get();

        $sent = 0;
        foreach ($due as $task) {
            if (!$task->user) {
                continue;
            }

            try {
                $task->user->notify(new TaskExpectedDateReached($task));
                $sent++;
            } catch (\Throwable $e) {
                // One bad address must not stop the rest of the run.
                Log::warning("Expected-date notify failed for task {$task->id}: " . $e->getMessage());
            }

            // Stamped regardless of whether the send succeeded, so a
            // permanently-broken address does not retry (and re-log) forever.
            $task->update(['expected_date_notified_at' => now()]);
        }

        $this->info("{$sent} of {$due->count()} expected-completion-date reminder(s) sent.");
    }
}
