<?php

namespace App\Console\Commands;

use App\Notifications\NinetyDay\AddCustomersReminder;
use App\Services\NinetyDayGoalService as Goals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Emails the "finish adding your 90-Day customers" reminder to anyone whose
 * add window closes within config('ninety_day.reminder_lead_hours') and who is
 * still under the minimum - once per deadline (stamped on the round). Runs
 * hourly so it catches the ~12-hour mark whatever time the window closes.
 */
class NinetyDayRemindAddCustomers extends Command
{
    protected $signature = 'ninety-day:remind-add-customers {--dry-run : List who would be reminded, send nothing}';

    protected $description = 'Remind 90-Day participants under the minimum that their add-customers window is closing soon.';

    public function handle(): int
    {
        $min = (int) config('ninety_day.min_customers', 10);
        $due = Goals::roundsNeedingAddReminder();
        $sent = 0;

        foreach ($due as $round) {
            $count = Goals::customerCount($round);
            $closes = Goals::customerWindowClosesAt($round);
            if ($this->option('dry-run')) {
                $this->line(sprintf('  would remind #%d %s (%d/%d, closes %s)', $round->user_id, optional($round->user)->getFullName(), $count, $min, $closes->format('d M H:i')));
                $sent++;
                continue;
            }
            try {
                $round->user->notify(new AddCustomersReminder($round, $count, $min, $closes));
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("90-Day add-customers reminder failed for round {$round->id}: " . $e->getMessage());
            }
            // Stamped regardless, so a broken address does not retry every hour.
            $round->forceFill(['completion_reminder_sent_at' => now()])->save();
        }

        $this->info(($this->option('dry-run') ? 'Would remind ' : 'Reminded ') . "{$sent} of {$due->count()} participant(s).");

        return self::SUCCESS;
    }
}
