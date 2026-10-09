<?php

namespace App\Console\Commands;

use App\Services\NinetyDayGoalService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Monday routine for the 90-Day Goals tracker: closes rounds that have ended,
 * starts a round for anyone in Sales without one, opens this week's check-ins
 * and puts the "submit your status" task on each person's list.
 *
 * Safe to run twice: every step checks before it creates.
 */
class NinetyDayGoalsWeeklyCommand extends Command
{
    protected $signature = 'goals:weekly {--date= : run as if today were this date (YYYY-MM-DD)}';

    protected $description = 'Weekly 90-Day Goals run: close ended rounds, start new ones, open check-ins, add the Monday task';

    public function handle(): int
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $r = NinetyDayGoalService::runWeekly($today);

        $this->info("90-Day Goals for {$today->toDateString()}: closed {$r['closed']} round(s), started {$r['started']}, opened {$r['checkins']} check-in(s), added {$r['tasks']} task(s), emailed {$r['emails']} assignee(s).");

        return self::SUCCESS;
    }
}
