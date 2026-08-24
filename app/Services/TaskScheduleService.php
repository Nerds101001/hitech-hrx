<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;
use App\Notifications\Task\TaskAssigned;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a TaskSchedule into today's actual Task rows — one per target
 * person, due today, linked back to the schedule that made them. Shared by
 * the daily tasks:generate-recurring command and by TaskManagementController
 * so a schedule created on a day it already matches fires immediately
 * instead of the assigner waiting until tomorrow's cron run to see anything.
 */
class TaskScheduleService
{
    /**
     * Fire this schedule for $day if it matches and has not already run
     * today. Returns the created tasks, or an empty collection if nothing
     * happened (inactive, already run, no target users left, or the day
     * does not match).
     */
    public static function fireIfDue(TaskSchedule $schedule, ?Carbon $day = null): \Illuminate\Support\Collection
    {
        $day = $day ?: Carbon::today();

        if (!$schedule->is_active || !$schedule->matchesToday($day)) {
            return collect();
        }

        if ($schedule->last_run_on && $schedule->last_run_on->isSameDay($day)) {
            return collect();
        }

        $targetIds = User::whereIn('id', $schedule->target_user_ids ?? [])
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->pluck('id');

        $batchId = (string) Str::uuid();
        $created = collect();

        DB::transaction(function () use ($schedule, $targetIds, $batchId, $day, &$created) {
            foreach ($targetIds as $uid) {
                $created->push(Task::create([
                    'batch_id'         => $batchId,
                    'task_schedule_id' => $schedule->id,
                    'title'            => $schedule->title,
                    'description'      => $schedule->description,
                    'type'             => 'open',
                    'assigned_by_id'   => $schedule->assigned_by_id,
                    'user_id'          => $uid,
                    'for_date'         => $day,
                    'due_date'         => $day,
                    'status'           => 'new',
                    'priority'         => $schedule->priority,
                    'created_by_id'    => $schedule->assigned_by_id,
                    'updated_by_id'    => $schedule->assigned_by_id,
                ]));
            }
            $schedule->update(['last_run_on' => $day]);
        });

        foreach ($created as $task) {
            try {
                $task->user?->notify(new TaskAssigned($task));
            } catch (\Throwable $e) {
                Log::warning("Recurring task-assigned notify failed for task {$task->id}: " . $e->getMessage());
            }
        }

        return $created;
    }
}
