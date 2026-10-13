<?php

namespace App\Console\Commands;

use App\Enums\UserAccountStatus;
use App\Models\Task;
use App\Models\TaskCc;
use App\Models\User;
use App\Notifications\Task\TaskDailyDigest;
use App\Services\TaskDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * One email a day, per person, covering every open task touching them -
 * replaces what would otherwise have been a separate email for every reply,
 * status change or edit (see TaskReplied/TaskStatusUpdated/TaskDetailsUpdated,
 * which still fire in-app + push instantly, just not mail).
 */
class SendTaskDailyDigest extends Command
{
    protected $signature = 'tasks:daily-digest';
    protected $description = 'Send each person a single daily email covering their open tasks, replies and CC backlog';

    public function handle(): int
    {
        // Only people an open task actually touches right now - assignee,
        // assigner, or CC - not every active employee in the company.
        $candidateIds = Task::whereNotIn('status', Task::CLOSED_STATUSES)->pluck('user_id')
            ->merge(Task::whereNotIn('status', Task::CLOSED_STATUSES)->pluck('assigned_by_id'))
            ->merge(TaskCc::whereHas('task', fn ($q) => $q->whereNotIn('status', Task::CLOSED_STATUSES))->pluck('user_id'))
            ->filter()
            ->unique();

        $users = User::whereIn('id', $candidateIds)
            ->where('status', UserAccountStatus::ACTIVE)
            ->whereNotNull('email')
            ->get();

        $sent = 0;
        $skipped = 0;
        foreach ($users as $user) {
            try {
                $data = TaskDigestService::buildFor($user);
                if ($data === null) {
                    $skipped++;
                    continue;
                }
                $user->notify(new TaskDailyDigest($data));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Task daily digest failed for user {$user->id}: " . $e->getMessage());
            }
        }

        $this->info("Task daily digest: sent {$sent}, skipped {$skipped} (nothing to report), out of {$users->count()} candidates.");

        return self::SUCCESS;
    }
}
