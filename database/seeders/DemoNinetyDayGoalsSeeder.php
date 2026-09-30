<?php

namespace Database\Seeders;

use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayGoalNote;
use App\Models\NinetyDayRound;
use App\Models\SalesPipeline;
use App\Models\User;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Two finished 90-day rounds, back to back, for the demo Sales account (sales@demo.com) —
 * as if this were already the person's third round of the year — so the round-closed state,
 * the achievement dashboard and the comment history all have real history to show on
 * localhost, not just one round. Needs DemoAccountsSeeder and DemoSalesPipelineSeeder to have
 * run first, and is local-only: this is fabricated data, never meant to run against a real
 * tenant. Visiting the 90-Day Goals page afterwards starts a third, empty, active round the
 * normal way — that is the "just started the next one" state to check.
 *
 *   php artisan db:seed --class=DemoNinetyDayGoalsSeeder
 */
class DemoNinetyDayGoalsSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->isLocal()) {
            $this->command->error('DemoNinetyDayGoalsSeeder is local-only — refusing to run in ' . app()->environment() . '.');
            return;
        }

        $user = User::where('email', 'sales@demo.com')->first();
        if (!$user) {
            $this->command->error('sales@demo.com not found. Run DemoAccountsSeeder first.');
            return;
        }
        // Everything this account has under 90-Day Goals is demo data this seeder made (or that the
        // weekly job auto-started for it), never a real person's history, so it is always safe to
        // clear and rebuild rather than only filling gaps.
        NinetyDayGoal::where('user_id', $user->id)->each(function ($g) {
            $g->checkins()->each(fn ($c) => $c->notes()->delete());
            $g->checkins()->delete();
            $g->delete();
        });
        NinetyDayRound::where('user_id', $user->id)->delete();

        $pipeline = SalesPipeline::where('salesperson_id', $user->id)->orderBy('id')->get();
        if ($pipeline->count() < 12) {
            $this->command->error('sales@demo.com has fewer than 12 pipeline customers (needs 6 per round × 2 rounds). Run DemoSalesPipelineSeeder first.');
            return;
        }

        $today = Carbon::today();
        $round2Starts = Goals::mondayOf($today->copy()->subDays(Goals::ROUND_SPAN_DAYS));   // the most recently finished round
        $round1Starts = $round2Starts->copy()->subDays(Goals::ROUND_SPAN_DAYS + 1);          // the one before that, back to back, no gap

        $rounds = [
            [
                'round_no' => 1, 'starts_on' => $round1Starts,
                'pipeline' => $pipeline->slice(6, 6)->values(), 'months' => ['03', '04', '05', '06'],
                'stories' => $this->storiesA(),
            ],
            [
                'round_no' => 2, 'starts_on' => $round2Starts,
                'pipeline' => $pipeline->slice(0, 6)->values(), 'months' => ['06', '07', '08', '09'],
                'stories' => $this->storiesB(),
            ],
        ];

        $comments = [
            'On Track' => ['Spoke to purchase, still interested.', 'Sample with their QC team.', 'Following up next week.', 'No change since last week, watching it.'],
            'Off Track' => ['Went quiet after the last call, chasing.', 'Budget approval stuck on their end.', 'Competitor quoted lower, re-pitching value.'],
            'Trial Order' => ['First trial PO placed, small quantity.', 'Trial batch shipped, awaiting feedback.'],
            'Regular Order' => ['Second PO in, moving to monthly billing.', 'Confirmed as a repeat account now.'],
            'No Report' => ['Could not reach anyone this week.', 'Office closed, will try again.'],
            'Dropped' => ['Went with a competitor, closing this one out.'],
            'Transferred' => ['Handed over to the export team, out of my book now.'],
        ];
        $lateComments = ['Slipped past Monday, catching up now.', 'Was on leave, filing this a day late.'];
        $tenantId = $user->tenant_id;
        $summary = [];

        foreach ($rounds as $def) {
            $startsOn = $def['starts_on'];
            $endsOn = $startsOn->copy()->addDays(Goals::ROUND_SPAN_DAYS);

            $round = NinetyDayRound::create([
                'tenant_id' => $tenantId, 'user_id' => $user->id, 'round_no' => $def['round_no'],
                'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(),
                'status' => 'closed', 'closed_at' => $endsOn->copy()->addDay(),
            ]);

            $goalCount = 0;
            $checkinCount = 0;

            foreach ($def['stories'] as $i => $story) {
                $sp = $def['pipeline'][$i];
                $brand = Goals::brandOf($sp->product) ?: 'Other';

                $goal = NinetyDayGoal::create([
                    'user_id' => $user->id, 'round_id' => $round->id, 'sales_pipeline_id' => $sp->id, 'tenant_id' => $tenantId,
                    'client_name' => $sp->party_name, 'product' => $sp->product, 'brand' => $brand,
                    'goal' => $story['goal'], 'goal_type' => $story['goal'], 'monthly_potential' => $story['potential'],
                    'is_active' => true, 'closed_at' => $round->closed_at,
                ]);
                $goalCount++;

                foreach ($story['weeks'] as $week => $status) {
                    $due = $startsOn->copy()->addDays(7 * $week);
                    $late = in_array($week, $story['late'] ?? [], true);
                    $submittedAt = $late ? $due->copy()->addDays(2)->setTime(11, 30) : $due->copy()->setTime(random_int(9, 17), random_int(0, 59));

                    $comment = null;
                    if ($status !== null) {
                        $pool = $late ? $lateComments : ($comments[$status] ?? []);
                        $comment = $pool[array_rand($pool)];
                    }

                    NinetyDayGoalCheckin::create([
                        'tenant_id' => $tenantId, 'goal_id' => $goal->id, 'round_id' => $round->id, 'week_no' => $week,
                        'due_on' => $due->toDateString(), 'status' => $status, 'comment' => $comment,
                        'submitted_at' => $status !== null ? $submittedAt : null,
                        'submitted_by_id' => $status !== null ? $user->id : null, 'is_late' => $status !== null && $late,
                    ]);
                    if ($status !== null) {
                        $goal->forceFill(["w{$week}" => $status])->save();
                    }
                    $checkinCount++;
                }

                // Real sales against the customer for every month this round's dashboard tally looks at, in
                // lakh like the rest of this feature — overwriting all of them, not just the ones with a
                // story sale, because DemoSalesPipelineSeeder already left rupee-scale numbers here that
                // would otherwise get summed as lakh and blow up the achievement total. Each round uses its
                // own, disjoint set of customers, so two rounds never sum the same pipeline row's month twice.
                $perMonth = round($story['sales'] / count($def['months']), 2);
                foreach ($def['months'] as $m) {
                    DB::table('sales_pipeline_months')->updateOrInsert(
                        ['sales_pipeline_id' => $sp->id, 'month_year' => "2026-{$m}"],
                        ['forecast_amount' => $story['potential'], 'sale_amount' => $perMonth, 'pending_amount' => max(0, $story['potential'] - $perMonth),
                         'remarks' => 'Demo data', 'tenant_id' => $tenantId, 'updated_at' => now(), 'created_at' => now()]
                    );
                }
            }

            // A comment added after the fact, as an admin would, so the Comments tab has both kinds in every round.
            $firstGoal = NinetyDayGoal::where('round_id', $round->id)->orderBy('id')->first();
            $tenthWeek = NinetyDayGoalCheckin::where('goal_id', $firstGoal->id)->where('week_no', 10)->first();
            if ($tenthWeek) {
                NinetyDayGoalNote::create(['tenant_id' => $tenantId, 'checkin_id' => $tenthWeek->id, 'goal_id' => $firstGoal->id,
                    'week_no' => 10, 'user_id' => $user->id, 'body' => 'Round ' . $def['round_no'] . ' review: keep going the same way here.']);
            }

            $summary[] = "round {$def['round_no']} ({$startsOn->format('d M')} to {$endsOn->format('d M Y')}): {$goalCount} customers, {$checkinCount} check-ins";
        }

        $this->command->info('✅ 90-Day Goals demo history for sales@demo.com — ' . implode('; ', $summary)
            . '. Opening the page now starts round 3, fresh and empty, the normal way.');
    }

    /** Round 1's six customers: an earlier, rougher quarter. */
    private function storiesA(): array
    {
        return [
            ['potential' => 5.0, 'goal' => 'First order', 'sales' => 3.5,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'Trial Order', 4 => 'Trial Order', 5 => 'Regular Order',
                    6 => 'Regular Order', 7 => 'Regular Order', 8 => 'Regular Order', 9 => 'Regular Order', 10 => 'Regular Order', 11 => 'Regular Order', 12 => 'Regular Order']],
            ['potential' => 3.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => 'Off Track', 2 => 'Off Track', 3 => 'No Report', 4 => 'No Report', 5 => 'No Report',
                    6 => null, 7 => 'No Report', 8 => 'Dropped', 9 => 'Dropped', 10 => 'Dropped', 11 => 'Dropped', 12 => 'Dropped'],
                'late' => [1]],
            ['potential' => 7.0, 'goal' => 'Regular order', 'sales' => 4.0,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'On Track', 4 => 'On Track', 5 => 'Trial Order',
                    6 => 'Trial Order', 7 => 'Regular Order', 8 => 'Regular Order', 9 => 'On Track', 10 => 'Regular Order', 11 => 'Regular Order', 12 => 'Regular Order']],
            ['potential' => 2.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => null, 2 => null, 3 => 'No Report', 4 => null, 5 => null,
                    6 => 'No Report', 7 => null, 8 => null, 9 => 'No Report', 10 => null, 11 => null, 12 => 'Dropped']],
            ['potential' => 4.0, 'goal' => 'Order', 'sales' => 1.0,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'Off Track', 3 => 'On Track', 4 => 'On Track', 5 => 'On Track',
                    6 => 'Off Track', 7 => 'On Track', 8 => 'Trial Order', 9 => 'Trial Order', 10 => 'Off Track', 11 => 'Off Track', 12 => 'Off Track'],
                'late' => [6, 9]],
            ['potential' => 3.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'Transferred', 4 => null, 5 => null,
                    6 => null, 7 => null, 8 => null, 9 => null, 10 => null, 11 => null, 12 => null]],
        ];
    }

    /** Round 2's six customers: the round that just finished. Same shape as before this seeder grew a second round. */
    private function storiesB(): array
    {
        return [
            ['potential' => 8.0, 'goal' => 'Regular order', 'sales' => 6.0,
                'weeks' => array_fill(0, 8, 'On Track') + [8 => 'Trial Order', 9 => 'Trial Order', 10 => 'Regular Order', 11 => 'Regular Order', 12 => 'Regular Order']],
            ['potential' => 5.0, 'goal' => 'First order', 'sales' => 2.5,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'Off Track', 3 => 'Off Track', 4 => 'On Track', 5 => 'On Track',
                    6 => 'Trial Order', 7 => 'Trial Order', 8 => 'Trial Order', 9 => 'Regular Order', 10 => 'Regular Order', 11 => 'Regular Order', 12 => 'Regular Order']],
            ['potential' => 3.0, 'goal' => 'Re-engage', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => 'No Report', 2 => 'No Report', 3 => 'No Report', 4 => null, 5 => 'No Report',
                    6 => 'Off Track', 7 => 'Off Track', 8 => null, 9 => 'Dropped', 10 => 'Dropped', 11 => 'Dropped', 12 => 'Dropped']],
            ['potential' => 6.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'On Track', 4 => 'Transferred', 5 => null,
                    6 => null, 7 => null, 8 => null, 9 => null, 10 => null, 11 => null, 12 => null]],
            ['potential' => 4.0, 'goal' => 'Order', 'sales' => 1.2,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'Off Track', 4 => 'On Track', 5 => 'On Track',
                    6 => 'On Track', 7 => 'Trial Order', 8 => 'Trial Order', 9 => 'Trial Order', 10 => 'Off Track', 11 => 'Off Track', 12 => 'Off Track'],
                'late' => [3, 10]],
            ['potential' => 2.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => null, 2 => 'On Track', 3 => 'On Track', 4 => null, 5 => 'On Track',
                    6 => 'On Track', 7 => null, 8 => 'On Track', 9 => 'On Track', 10 => null, 11 => 'On Track', 12 => 'On Track']],
        ];
    }
}
