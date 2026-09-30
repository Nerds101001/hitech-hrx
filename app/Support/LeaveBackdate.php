<?php

namespace App\Support;

use App\Models\LeaveUnlock;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * How far back an employee may apply for leave. One place for the rule so the
 * web form, its date picker and the mobile app cannot drift apart - the app
 * used to have no limit at all while the web form allowed 30 days.
 *
 * Admin/HR can open a Leave Unlock Window (LeaveUnlock) for older dates, for
 * everyone or for named people - like the attendance unlock windows.
 */
class LeaveBackdate
{
    /** Leave may start at most this many days before today (one week). */
    public const MAX_DAYS = 7;

    /** The earliest start date the normal rule allows today, as Y-m-d. */
    public static function earliest(?Carbon $today = null): string
    {
        return ($today ?? Carbon::today())->copy()->subDays(self::MAX_DAYS)->toDateString();
    }

    /**
     * The earliest date $userId can pick: the normal limit, or the start of an
     * open unlock window for them if that is earlier. For the date picker; the
     * server still checks each day with error().
     */
    public static function earliestFor(?int $userId, ?Carbon $today = null): string
    {
        $earliest = self::earliest($today);
        foreach (self::windows() as $w) {
            if ($w->appliesTo($userId) && $w->from_date->toDateString() < $earliest) {
                $earliest = $w->from_date->toDateString();
            }
        }

        return $earliest;
    }

    /**
     * An error message when the leave reaches further back than allowed,
     * otherwise null. Every day of it older than the one-week limit must be
     * covered by an open unlock window for this person.
     */
    public static function error($fromDate, $toDate = null, ?int $userId = null, ?Carbon $today = null): ?string
    {
        if (!$fromDate) {
            return null;
        }
        $earliest = Carbon::parse(self::earliest($today));
        $from = Carbon::parse($fromDate)->startOfDay();
        if ($from->gte($earliest)) {
            return null;
        }

        // Only the days before the limit need a window; a leave running into
        // the last week or beyond is fine from there on.
        $to = $toDate ? Carbon::parse($toDate)->startOfDay() : $from->copy();
        $lastOld = $to->lt($earliest) ? $to : $earliest->copy()->subDay();
        $windows = self::windows()->filter(fn ($w) => $w->appliesTo($userId));
        for ($d = $from->copy(), $n = 0; $d->lte($lastOld) && $n < 400; $d->addDay(), $n++) {
            $day = $d->toDateString();
            if (!$windows->contains(fn ($w) => $w->covers($day, $userId))) {
                return 'You cannot apply for leave more than ' . self::MAX_DAYS . ' days (1 week) in the past'
                    . ($windows->isEmpty() ? '.' : ' - HR has unlocked other dates for you, but not ' . $d->format('d M Y') . '.')
                    . ' Ask HR to open a leave unlock window for those dates.';
            }
        }

        return null;
    }

    /** Open windows; none if the table is not there yet. */
    private static function windows()
    {
        return Schema::hasTable('leave_unlocks') ? LeaveUnlock::where('is_active', true)->get() : collect();
    }
}
