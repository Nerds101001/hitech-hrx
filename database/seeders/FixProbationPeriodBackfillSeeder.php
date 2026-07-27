<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Backfills probation_period_months / probation_end_date for ACTIVE
 * employees with date_of_joining >= 2026-01-01, per the standing rule:
 *   - DOJ 2026-01-01..2026-05-31 -> 6-month probation
 *   - DOJ 2026-06-01 onward      -> 11-month probation
 * Only touches users with probation_confirmed_at still NULL (never
 * overwrites a finalized decision). Terminated employees and anyone
 * with date_of_joining before 2026-01-01 are left untouched. Idempotent.
 */
class FixProbationPeriodBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $this->backfillBucket('2026-01-01', '2026-05-31', 6, 'Jan-May 2026 (6mo rule)');
        $this->backfillBucket('2026-06-01', null, 11, 'Jun 2026+ (11mo rule)');
    }

    private function backfillBucket(string $fromDoj, ?string $toDoj, int $months, string $label): void
    {
        $query = User::query()
            ->where('status', 'active')
            ->whereNull('probation_confirmed_at')
            ->whereNotNull('date_of_joining')
            ->where('date_of_joining', '>=', $fromDoj);

        if ($toDoj) {
            $query->where('date_of_joining', '<=', $toDoj);
        }

        $users = $query->get(['id', 'name', 'date_of_joining', 'probation_period_months', 'probation_end_date', 'is_probation_extended']);

        $updated = 0;
        $flaggedExtended = [];

        foreach ($users as $user) {
            if ($user->is_probation_extended) {
                $flaggedExtended[] = "{$user->id} ({$user->name})";
            }

            $newEndDate = Carbon::parse($user->date_of_joining)->addMonths($months)->toDateString();

            $user->forceFill([
                'probation_period_months' => $months,
                'probation_end_date'      => $newEndDate,
            ])->save();

            $updated++;
        }

        $this->command->info("[{$label}] Updated {$updated} user(s) to {$months}-month probation.");

        if ($flaggedExtended) {
            $this->command->warn("[{$label}] Had is_probation_extended=true (prior manual extension) but were overwritten per the new rule anyway -- review: " . implode(', ', $flaggedExtended));
        }
    }
}
