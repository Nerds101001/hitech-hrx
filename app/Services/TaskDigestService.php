<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

/**
 * Builds the single daily task digest for one person — everything that would
 * otherwise have been a separate reply/status/edit email, rolled into one.
 * Three roles, each scored against the open (not CLOSED_STATUSES) tasks that
 * role touches:
 *
 *  - Assigned to you: your own backlog (due today / overdue / total).
 *  - Assigned by you: tasks you gave to others. "Whose turn it is" is read
 *    off the thread's last update — if the assignee spoke last, the ball is
 *    in YOUR court as the assigner; if you (or nobody) spoke last, it's
 *    still with them. A task with no update yet has nobody's "turn", so it
 *    only counts in due/overdue, not in the pending-reply lists.
 *  - CC'd on: same "last update" read, from the assignee's side — a CC
 *    watches the assignee's own update, not the assigner's.
 */
class TaskDigestService
{
    /** How many rows to list per section before collapsing to "+N more". */
    private const LIST_LIMIT = 10;

    /** Null when there is nothing at all to report — the command skips sending. */
    public static function buildFor(User $user, ?Carbon $today = null): ?array
    {
        $today = $today ?: Carbon::today();

        $assignedToYou = self::assignedToYou($user, $today);
        $assignedByYou = self::assignedByYou($user, $today);
        $ccTasks       = self::ccTasks($user, $today);

        $hasAnything = $assignedToYou['totalPending'] > 0
            || $assignedByYou['totalPendingReply'] > 0
            || $assignedByYou['repliesTodayCount'] > 0
            || $ccTasks['totalPending'] > 0;

        return $hasAnything ? compact('assignedToYou', 'assignedByYou', 'ccTasks') : null;
    }

    /** Your own backlog — tasks where you are the assignee. */
    private static function assignedToYou(User $user, Carbon $today): array
    {
        $base = Task::where('user_id', $user->id)->whereNotIn('status', Task::CLOSED_STATUSES);

        return [
            'dueToday'     => (clone $base)->dueToday()->count(),
            'overdue'      => (clone $base)->overdue()->count(),
            'totalPending' => (clone $base)->count(),
        ];
    }

    /** Tasks you handed to someone else. */
    private static function assignedByYou(User $user, Carbon $today): array
    {
        $tasks = Task::where('assigned_by_id', $user->id)
            ->where('user_id', '!=', $user->id)
            ->whereNotIn('status', Task::CLOSED_STATUSES)
            ->with(['user:id,first_name,last_name', 'taskUpdates' => fn ($q) => $q->latest()->limit(1)])
            ->get();

        $pendingList = [];
        $repliesToday = [];
        foreach ($tasks as $task) {
            $last = $task->taskUpdates->first();
            $assigneeSpokeLast = $last && (int) $last->created_by_id === (int) $task->user_id;

            if ($assigneeSpokeLast) {
                $pendingList[] = [
                    'title'       => $task->title,
                    'assignee'    => optional($task->user)->getFullName() ?? '—',
                    'daysPending' => (int) $last->created_at->diffInDays(now()),
                    'dueLabel'    => $task->dueLabel(),
                    'needsToday'  => $task->due_date && $task->due_date->isToday() || $last->created_at->isToday(),
                ];
            }
            if ($last && $last->created_at->isToday() && (int) $last->created_by_id !== (int) $user->id) {
                $repliesToday[] = [
                    'title'   => $task->title,
                    'by'      => optional($last->createdBy)->getFullName() ?? 'Someone',
                    'comment' => (string) ($last->comment ?: '(no comment — status/attachment only)'),
                    'time'    => $last->created_at->format('h:i A'),
                ];
            }
        }

        return [
            'needingReplyToday' => collect($pendingList)->where('needsToday', true)->count(),
            'totalPendingReply' => count($pendingList),
            'pendingReplyList'  => array_slice($pendingList, 0, self::LIST_LIMIT),
            'pendingReplyMore'  => max(0, count($pendingList) - self::LIST_LIMIT),
            'repliesTodayCount' => count($repliesToday),
            'repliesToday'      => array_slice($repliesToday, 0, self::LIST_LIMIT),
            'repliesTodayMore'  => max(0, count($repliesToday) - self::LIST_LIMIT),
        ];
    }

    /** Tasks you're CC'd on (not the assignee, not the assigner). */
    private static function ccTasks(User $user, Carbon $today): array
    {
        $tasks = Task::whereNotIn('status', Task::CLOSED_STATUSES)
            ->whereHas('taskCcs', fn ($q) => $q->where('user_id', $user->id))
            ->with(['user:id,first_name,last_name', 'taskUpdates' => fn ($q) => $q->latest()->limit(1)])
            ->get();

        $pendingList = [];
        foreach ($tasks as $task) {
            $last = $task->taskUpdates->first();
            $daysPending = (int) ($last ? $last->created_at : $task->created_at)->diffInDays(now());
            $pendingList[] = [
                'title'       => $task->title,
                'assignee'    => optional($task->user)->getFullName() ?? '—',
                'daysPending' => $daysPending,
                'dueLabel'    => $task->dueLabel(),
                // A CC needs to look when the assignee has gone quiet long
                // enough that their own update is now the stale one.
                'needsUpdate' => !$last || $daysPending >= 1,
            ];
        }

        return [
            'needingUpdate' => collect($pendingList)->where('needsUpdate', true)->count(),
            'totalPending'  => count($pendingList),
            'pendingList'   => array_slice($pendingList, 0, self::LIST_LIMIT),
            'pendingMore'   => max(0, count($pendingList) - self::LIST_LIMIT),
        ];
    }
}
