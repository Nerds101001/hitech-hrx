<?php

namespace Tests\Feature;

use App\Models\NinetyDayRound;
use App\Models\User;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Target customers may only be added for 48 hours after enrolment, and a round holding fewer
 * than the minimum does not count. Both rules lock real salespeople out of their own targets,
 * so the edges matter more than the happy path.
 */
class NinetyDayCustomerWindowTest extends TestCase
{
    use DatabaseTransactions;

    private function round(?Carbon $createdAt = null): NinetyDayRound
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Goals',
            'email'      => 'qagoals' . uniqid() . '@example.test',
            'code'       => 'QAG' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);

        $r = NinetyDayRound::create([
            'user_id' => $u->id, 'round_no' => 1, 'status' => 'active',
            'starts_on' => Carbon::today()->toDateString(),
            'ends_on'   => Carbon::today()->addDays(90)->toDateString(),
        ]);

        // created_at is what the window counts from, so the tests must be able to set it.
        if ($createdAt) {
            $r->forceFill(['created_at' => $createdAt])->save();
        }

        return $r->fresh();
    }

    public function test_the_window_is_counted_from_enrolment_not_from_the_round_start(): void
    {
        // Grace date in the past, so this measures the enrolment anchor alone.
        config(['ninety_day.window_rule_from' => Carbon::now()->subYear()->toDateTimeString()]);

        // Back-dated round (snapped to its Monday) enrolled just now: the window must run from
        // the enrolment, or someone enrolled mid-week loses hours they were never told about.
        $r = $this->round(Carbon::now());
        $r->forceFill(['starts_on' => Carbon::today()->subDays(5)->toDateString()])->save();

        $closes = Goals::customerWindowClosesAt($r->fresh());

        $this->assertEqualsWithDelta(
            Carbon::now()->addHours(48)->timestamp, $closes->timestamp, 120,
            'the window must close 48 hours after enrolment, regardless of the round start date'
        );
        $this->assertTrue(Goals::customerWindowOpen($r->fresh()));
    }

    public function test_the_window_closes_after_the_configured_hours(): void
    {
        // Push the grace date into the past so it does not hold these windows open - that
        // behaviour has its own test below.
        config(['ninety_day.window_rule_from' => Carbon::now()->subYear()->toDateTimeString()]);

        $r = $this->round(Carbon::now()->subHours(49));

        $this->assertFalse(Goals::customerWindowOpen($r), '49 hours after enrolment the window is shut');
        $this->assertTrue(
            Goals::customerWindowOpen($this->round(Carbon::now()->subHours(47))),
            '47 hours after enrolment it is still open'
        );
    }

    /**
     * Introducing the rule must not retroactively shut the window on people enrolled before it
     * existed - the first program's three participants were enrolled with a task not due for
     * another three days, and would have been locked out having added nothing.
     */
    public function test_rounds_predating_the_rule_are_measured_from_when_the_rule_started(): void
    {
        config(['ninety_day.window_rule_from' => Carbon::now()->subHour()->toDateTimeString()]);

        $old = $this->round(Carbon::now()->subDays(30));

        $this->assertTrue(Goals::customerWindowOpen($old), 'an old round gets its 48 hours from the rule start, not from its own creation');
        $this->assertEqualsWithDelta(
            Carbon::now()->addHours(47)->timestamp,
            Goals::customerWindowClosesAt($old)->timestamp, 120
        );
    }

    public function test_the_minimum_is_judged_on_the_rounds_running_total(): void
    {
        config(['ninety_day.min_customers' => 3]);
        $r = $this->round(Carbon::now());

        $this->assertFalse(Goals::meetsMinimum($r), 'an empty round is below the minimum');
        $this->assertSame(0, Goals::customerCount($r));

        $state = Goals::windowState($r);
        $this->assertSame(3, $state['short_by']);
        $this->assertFalse($state['meets_min']);
        $this->assertTrue($state['open']);
    }

    /** A shut window with the minimum unmet is the state the banner must warn hardest about. */
    public function test_a_closed_window_reports_itself_as_closed_and_short(): void
    {
        config(['ninety_day.min_customers' => 10, 'ninety_day.window_rule_from' => Carbon::now()->subYear()->toDateTimeString()]);
        $r = $this->round(Carbon::now()->subDays(5));

        $state = Goals::windowState($r);
        $this->assertFalse($state['open']);
        $this->assertSame(0, $state['hours_left']);
        $this->assertSame(10, $state['short_by']);
        $this->assertFalse($state['meets_min']);
    }
}
