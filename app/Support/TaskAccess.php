<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;

/**
 * TaskAccess — single source of truth for "whose tasks may this user assign,
 * see and manage?" on the Task Management portal.
 *
 * Rules:
 *   - admin / hr / tasks.manage → unrestricted (assign to anyone, see every task)
 *   - manager / accounts        → their own direct reports only (reporting_to_id,
 *                                  via managerIdentityIds() so a delegate covering
 *                                  a vacant manager seat inherits the same reach)
 *   - anyone else                → nothing on this portal; their own tasks still
 *                                  come through MyTasksController, which is a
 *                                  separate, narrower read (self only)
 *
 * Kept apart from SalesScope: that one governs sales BOOKS (whose customers/
 * pipeline a rep can see); this one governs task ASSIGNMENT, a different
 * relationship even where the role names line up.
 */
class TaskAccess
{
    /** True if this user manages tasks org-wide — no team restriction applies. */
    public static function isUnrestricted(?User $user): bool
    {
        return $user && ($user->hasRole(['admin', 'Admin', 'hr', 'HR']) || $user->can('tasks.manage'));
    }

    /**
     * User ids this person may assign a task to / view tasks for.
     * null == unrestricted (admin/HR/tasks.manage). Empty array == no one.
     */
    public static function allowedUserIds(?User $user): ?array
    {
        if (!$user) {
            return [];
        }

        if (self::isUnrestricted($user)) {
            return null;
        }

        if ($user->hasRole(['manager', 'Manager', 'accounts', 'Accounts'])) {
            return User::whereIn('reporting_to_id', $user->managerIdentityIds())
                ->notTerminated()->pluck('id')->all();
        }

        return [];
    }

    /** Can $user manage (edit/delete/reassign) this specific task? */
    public static function canManage(?User $user, Task $task): bool
    {
        if (!$user) {
            return false;
        }

        if (self::isUnrestricted($user)) {
            return true;
        }

        $ids = self::allowedUserIds($user) ?? [];

        // Either they are the one who assigned it, or it belongs to one of
        // their own reports — covers a manager cleaning up a task they set
        // as well as one inherited from a predecessor.
        return in_array($task->assigned_by_id, $user->managerIdentityIds(), true)
            || in_array($task->user_id, $ids, true);
    }

    /** Departments this person may target when assigning in bulk. */
    public static function allowedDepartmentIds(?User $user): ?array
    {
        if (self::isUnrestricted($user)) {
            return null;
        }

        $ids = self::allowedUserIds($user) ?? [];

        return User::whereIn('id', $ids)->pluck('department_id')->filter()->unique()->values()->all();
    }
}
