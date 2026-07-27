<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Small, display-only data-consistency fix: a handful of users (13
 * system-wide) have probation_period_months set but probation_end_date was
 * never computed -- they predate or bypassed the approveOnboarding() flow
 * that normally calculates it. This just fills that gap so the Probation
 * Dashboard doesn't show a period with no end date. Does not touch
 * probation_confirmed_at, balances, or anything payroll-related -- purely
 * date_of_joining + probation_period_months. Skips rows with no
 * date_of_joining (can't compute) or a 0-month period (not meaningful).
 */
class FixMissingProbationEndDatesSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereNotNull('probation_period_months')
            ->where('probation_period_months', '>', 0)
            ->whereNull('probation_end_date')
            ->whereNotNull('date_of_joining')
            ->get(['id', 'name', 'date_of_joining', 'probation_period_months']);

        $updated = 0;
        foreach ($users as $user) {
            $endDate = Carbon::parse($user->date_of_joining)->addMonths($user->probation_period_months)->toDateString();
            $user->forceFill(['probation_end_date' => $endDate])->save();
            $updated++;
        }

        $this->command->info("Filled probation_end_date for {$updated} user(s).");
    }
}
