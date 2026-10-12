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

        // Past its optional end date: deactivate it so it stops looping, and
        // create nothing more.
        if ($schedule->hasEnded($day)) {
            if ($schedule->is_active) {
                $schedule->update(['is_active' => false]);
                Log::info("Recurring task schedule {$schedule->id} reached its end date ({$schedule->ends_on->toDateString()}); deactivated.");
            }
            return collect();
        }

        // Never on a Sunday or company holiday; an occurrence that fell on
        // one fires on the first working day after it (TaskSchedule::isDueOn).
        if (!$schedule->is_active || !$schedule->isDueOn($day)) {
            return collect();
        }

        if ($schedule->last_run_on && $schedule->last_run_on->isSameDay($day)) {
            return collect();
        }

        $targetIds = User::whereIn('id', $schedule->target_user_ids ?? [])
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->pluck('id');

        // Everyone on it has left. This used to still count as a run -
        // last_run_on moved on every morning while nothing was created, so
        // the schedule looked healthy. Not stamped now; the Recurring tab
        // shows it as having nobody active.
        if ($targetIds->isEmpty()) {
            Log::warning("Recurring task schedule {$schedule->id} has no active people left; nothing created.");
            return collect();
        }

        // Same active-only filter as targets, and excludes anyone who's also
        // a target - being CC'd on your own task is meaningless.
        $ccIds = User::whereIn('id', $schedule->cc_user_ids ?? [])
            ->where('status', \App\Enums\UserAccountStatus::ACTIVE)
            ->pluck('id')
            ->diff($targetIds);

        $batchId = (string) Str::uuid();
        $created = collect();

        DB::transaction(function () use ($schedule, $targetIds, $ccIds, $batchId, $day, &$created) {
            foreach ($targetIds as $uid) {
                $task = Task::create([
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
                ]);

                foreach ($ccIds as $ccId) {
                    $task->taskCcs()->create([
                        'user_id'       => $ccId,
                        'created_by_id' => $schedule->assigned_by_id,
                    ]);
                }

                $created->push($task);
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
