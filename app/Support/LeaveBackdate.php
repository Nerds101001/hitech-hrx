<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * How far back an employee may apply for leave. One place for the rule so the
 * web form, its date picker and the mobile app cannot drift apart - the app
 * used to have no limit at all while the web form allowed 30 days.
 */
class LeaveBackdate
{
    /** Leave may start at most this many days before today (one week). */
    public const MAX_DAYS = 7;

    /** The earliest start date allowed today, as Y-m-d. */
    public static function earliest(?Carbon $today = null): string
    {
        return ($today ?? Carbon::today())->copy()->subDays(self::MAX_DAYS)->toDateString();
    }

    /** An error message when $fromDate is too far back, otherwise null. */
    public static function error($fromDate, ?Carbon $today = null): ?string
    {
        if (!$fromDate) {
            return null;
        }
        if (Carbon::parse($fromDate)->startOfDay()->lt(Carbon::parse(self::earliest($today)))) {
            return 'You cannot apply for leave more than ' . self::MAX_DAYS . ' days (1 week) in the past.';
        }

        return null;
    }
}
