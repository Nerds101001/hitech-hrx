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
 * A finished 90-day round for the demo Sales account (sales@demo.com), so the round-closed
 * state, the achievement dashboard and the comment history all have something to show on
 * localhost. Needs DemoAccountsSeeder and DemoSalesPipelineSeeder to have run first, and is
 * local-only: this is fabricated data, never meant to run against a real tenant.
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
        // The weekly job starts every Sales person an empty current round on its own; if that already
        // happened here and nothing has been added to it yet, it is safe to clear and replace with the
        // demo history below (round 1, closed). A round that already has goals is left alone.
        $existing = NinetyDayRound::where('user_id', $user->id)->get();
        if ($existing->contains(fn ($r) => NinetyDayGoal::where('round_id', $r->id)->exists())) {
            $this->command->info('sales@demo.com already has a 90-Day Goals round with customers in it — skipping so it is not overwritten.');
            return;
        }
        NinetyDayRound::where('user_id', $user->id)->delete();

        $pipeline = SalesPipeline::where('salesperson_id', $user->id)->orderBy('id')->take(6)->get();
        if ($pipeline->count() < 6) {
            $this->command->error('sales@demo.com has fewer than 6 pipeline customers. Run DemoSalesPipelineSeeder first.');
            return;
        }

        $today = Carbon::today();
        $startsOn = Goals::mondayOf($today->copy()->subDays(Goals::ROUND_SPAN_DAYS));
        $endsOn = $startsOn->copy()->addDays(Goals::ROUND_SPAN_DAYS);

        $round = NinetyDayRound::create([
            'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'round_no' => 1,
            'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(),
            'status' => 'closed', 'closed_at' => $endsOn->copy()->addDay(),
        ]);

        // Each customer plays out a different, plausible 13-week story.
        $stories = [
            // status per week 0..12 (null = nothing sent that Monday); a trailing short array repeats its last entry.
            ['brand' => null, 'potential' => 8.0, 'goal' => 'Regular order', 'sales' => 6.0,
                'weeks' => array_fill(0, 8, 'On Track') + [8 => 'Trial Order', 9 => 'Trial Order', 10 => 'Regular Order', 11 => 'Regular Order', 12 => 'Regular Order']],
            ['brand' => null, 'potential' => 5.0, 'goal' => 'First order', 'sales' => 2.5,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'Off Track', 3 => 'Off Track', 4 => 'On Track', 5 => 'On Track',
                    6 => 'Trial Order', 7 => 'Trial Order', 8 => 'Trial Order', 9 => 'Regular Order', 10 => 'Regular Order', 11 => 'Regular Order', 12 => 'Regular Order']],
            ['brand' => null, 'potential' => 3.0, 'goal' => 'Re-engage', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => 'No Report', 2 => 'No Report', 3 => 'No Report', 4 => null, 5 => 'No Report',
                    6 => 'Off Track', 7 => 'Off Track', 8 => null, 9 => 'Dropped', 10 => 'Dropped', 11 => 'Dropped', 12 => 'Dropped']],
            ['brand' => null, 'potential' => 6.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'On Track', 4 => 'Transferred', 5 => null,
                    6 => null, 7 => null, 8 => null, 9 => null, 10 => null, 11 => null, 12 => null]],
            ['brand' => null, 'potential' => 4.0, 'goal' => 'Order', 'sales' => 1.2,
                'weeks' => [0 => 'On Track', 1 => 'On Track', 2 => 'On Track', 3 => 'Off Track', 4 => 'On Track', 5 => 'On Track',
                    6 => 'On Track', 7 => 'Trial Order', 8 => 'Trial Order', 9 => 'Trial Order', 10 => 'Off Track', 11 => 'Off Track', 12 => 'Off Track'],
                'late' => [3, 10]],
            ['brand' => null, 'potential' => 2.0, 'goal' => 'Order', 'sales' => 0.0,
                'weeks' => [0 => 'On Track', 1 => null, 2 => 'On Track', 3 => 'On Track', 4 => null, 5 => 'On Track',
                    6 => 'On Track', 7 => null, 8 => 'On Track', 9 => 'On Track', 10 => null, 11 => 'On Track', 12 => 'On Track']],
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
        $goalCount = 0;
        $checkinCount = 0;

        foreach ($stories as $i => $story) {
            $sp = $pipeline[$i];
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

            // Real sales against the customer for the months the round covers, in lakh like the rest of this
            // feature — overwriting ALL three months for every customer, not just the ones with a story sale,
            // because DemoSalesPipelineSeeder already left rupee-scale numbers here that would otherwise get
            // summed as lakh and blow up the achievement total.
            $perMonth = round($story['sales'] / 3, 2);
            foreach (['06', '07', '08'] as $m) {
                DB::table('sales_pipeline_months')->updateOrInsert(
                    ['sales_pipeline_id' => $sp->id, 'month_year' => "2026-{$m}"],
                    ['forecast_amount' => $story['potential'], 'sale_amount' => $perMonth, 'pending_amount' => max(0, $story['potential'] - $perMonth),
                     'remarks' => 'Demo data', 'tenant_id' => $tenantId, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        // A couple of comments added after the fact, as an admin would, so the Comments tab has both kinds.
        $firstGoal = NinetyDayGoal::where('round_id', $round->id)->orderBy('id')->first();
        NinetyDayGoalNote::create(['tenant_id' => $tenantId, 'checkin_id' => NinetyDayGoalCheckin::where('goal_id', $firstGoal->id)->where('week_no', 10)->value('id'),
            'goal_id' => $firstGoal->id, 'week_no' => 10, 'user_id' => $user->id, 'body' => 'Great turnaround on this one, use it as an example in the next review.']);

        $this->command->info("✅ Closed 90-Day round for sales@demo.com: {$goalCount} customers, {$checkinCount} weekly check-ins, round {$startsOn->format('d M')} to {$endsOn->format('d M Y')}.");
    }
}
