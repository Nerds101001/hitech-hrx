<?php

namespace App\Support;

use App\Models\User;

/**
 * Picks which manager dashboard a user sees.
 *
 * Managers do not all want the same numbers. A Sales manager is measured on
 * targets and achievement; a Customer Care / New Biz manager is measured on
 * forecast, pending orders and the book. Rather than one dashboard that tries
 * to serve both, the manager view branches on the variant returned here.
 *
 * Adding a department is a one-line change to self::MAP.
 */
class ManagerDashboard
{
    /**
     * Normalised department name => dashboard variant.
     *
     * Keys are lower-cased with a trailing "department" stripped, so both
     * "Sales" and "Sales Department" land on the same entry.
     */
    private const MAP = [
        'sales'                  => 'sales',
        'customer care'          => 'ccare',
        'new biz'                => 'ccare',
        'customer care + new biz' => 'ccare',
        'ccare & new biz'        => 'ccare',
    ];

    /** Fallback for departments with no dedicated dashboard yet. */
    public const DEFAULT = 'ccare';

    /**
     * @return string One of: 'sales', 'ccare'.
     */
    public static function variant(?User $user): string
    {
        if (!$user) {
            return self::DEFAULT;
        }

        return self::MAP[self::normalise(self::departmentName($user))] ?? self::DEFAULT;
    }

    /** The department name on the user, whichever shape the record takes. */
    public static function departmentName(?User $user): string
    {
        if (!$user) {
            return '';
        }

        try {
            return (string) ($user->department->name ?? '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The right-rail widgets: AI insights, today's events and the calendar.
     *
     * Same shapes the admin dashboard's rail renders, but every count here is
     * the manager's own team. Built once and shared by both controllers that
     * render the manager view, so the two cannot drift apart.
     *
     * @param  array $c  teamSize, present, onLeave, absent, pendingLeave,
     *                   pendingExpense, pendingDocument, pendingLoan,
     *                   todayBirthdays, todayAnniversaries, sm
     */
    public static function rail(array $c): array
    {
        $int = fn($k) => (int) ($c[$k] ?? 0);
        $col = fn($k) => collect($c[$k] ?? []);

        $teamSize = $int('teamSize');
        $present  = $int('present');
        $attPct   = $teamSize > 0 ? (int) round($present / $teamSize * 100) : 0;
        $approvals = $int('pendingLeave') + $int('pendingExpense')
                   + $int('pendingDocument') + $int('pendingLoan');
        $sm = (array) ($c['sm'] ?? []);

        $insights = [];
        if ($teamSize > 0) {
            $insights[] = ['icon' => 'bx-user-check',
                'tone'  => $attPct >= 90 ? 'success' : ($attPct >= 75 ? 'warning' : 'danger'),
                'title' => $attPct . '% of your team present',
                'sub'   => $present . ' of ' . $teamSize . ' checked in today'];
        }
        if ($int('onLeave') > 0) {
            $insights[] = ['icon' => 'bx-calendar-event', 'tone' => 'warning',
                'title' => $int('onLeave') . ' on leave today',
                'sub'   => 'Plan cover for their accounts'];
        }
        if ($approvals > 0) {
            $insights[] = ['icon' => 'bx-check-shield', 'tone' => 'info',
                'title' => $approvals . ' approvals waiting on you',
                'sub'   => 'Leave, expense, document & loan requests'];
        }
        if (($sm['tierTargets']['Bingo'] ?? 0) > 0) {
            $pct = (float) ($sm['tierPct']['Bingo'] ?? 0);
            $insights[] = ['icon' => 'bx-trending-up',
                'tone'  => $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'),
                'title' => 'Team at ' . $pct . '% of Bingo target',
                'sub'   => 'For ' . now()->format('F')];
        }
        if (!empty($sm['repsWithoutTarget'])) {
            $insights[] = ['icon' => 'bx-target-lock', 'tone' => 'warning',
                'title' => $sm['repsWithoutTarget'] . ' rep(s) without a target',
                'sub'   => 'No target set for ' . now()->format('F')];
        }
        $behind = collect($sm['scoreBehind'] ?? [])->filter(fn($r) => ($r['pct'] ?? 0) < 60)->count();
        if ($behind > 0) {
            $insights[] = ['icon' => 'bx-error-circle', 'tone' => 'danger',
                'title' => $behind . ' rep(s) below 60% of target',
                'sub'   => 'Coach and review pipeline'];
        }
        if (empty($insights)) {
            $insights[] = ['icon' => 'bx-check-circle', 'tone' => 'success',
                'title' => 'Nothing needs your attention', 'sub' => 'Your team is all clear today'];
        }

        try {
            $railUser = $c['user'] ?? null;
            $holidaysThisMonth = \App\Models\Holiday::countThisMonthForUser($railUser);
            $upcomingHolidays = \App\Models\Holiday::upcomingForUser($railUser);
        } catch (\Throwable $e) {
            $holidaysThisMonth = 0;
            $upcomingHolidays = collect();
        }

        return [
            'aiInsights' => $insights,
            'eventsToday' => [
                ['icon' => 'bx-cake', 'label' => 'Birthdays Today', 'count' => $col('todayBirthdays')->count(), 'tone' => 'primary', 'key' => 'bday'],
                ['icon' => 'bx-gift', 'label' => 'Work Anniversaries', 'count' => $col('todayAnniversaries')->count(), 'tone' => 'info', 'key' => 'anni'],
                ['icon' => 'bx-calendar-star', 'label' => 'Holidays This Month', 'count' => $holidaysThisMonth, 'tone' => 'success', 'key' => ''],
                ['icon' => 'bx-calendar-event', 'label' => 'Leaves Pending', 'count' => $int('pendingLeave'), 'tone' => 'danger', 'key' => ''],
                ['icon' => 'bx-group', 'label' => 'Team Size', 'count' => $teamSize, 'tone' => 'primary', 'key' => ''],
            ],
            'upcomingHolidays' => $upcomingHolidays,
        ];
    }

    /** Lower-case, collapse whitespace, drop a trailing "department". */
    private static function normalise(string $name): string
    {
        $n = strtolower(trim($name));
        $n = preg_replace('/\s+/', ' ', $n);
        $n = preg_replace('/\s*department$/', '', $n);

        return trim($n);
    }
}
