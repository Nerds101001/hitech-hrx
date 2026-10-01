<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\NinetyDayGoalController;
use App\Models\Department;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayProgram;
use App\Models\NinetyDayRound;
use App\Models\Task;
use App\Models\User;
use App\Notifications\NinetyDayGoal\ProgramInvite;
use App\Services\KpiAutoSourceService;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The 90-Day Goals tracker. Three things carry the weight: the Monday rules (a
 * status per goal per week, late ones need a comment), the 13-week round that
 * closes by itself and relaunches, and who is allowed to see or change what.
 */
class NinetyDayGoalsTest extends TestCase
{
    use DatabaseTransactions;

    private Department $sales;
    private Department $other;
    private User $rep;
    private User $rep2;
    private User $outsider;
    private const MONDAY = '2026-10-05';   // a Monday

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        View::share('errors', new \Illuminate\Support\ViewErrorBag());
        $this->sales = Department::create(['name' => 'ZZ Sales ' . uniqid(), 'code' => 'ZZS' . random_int(1000, 9999)]);
        $this->other = Department::create(['name' => 'ZZ Accounts ' . uniqid(), 'code' => 'ZZA' . random_int(1000, 9999)]);
        $this->rep = $this->person($this->sales);
        $this->rep2 = $this->person($this->sales);
        $this->outsider = $this->person($this->other);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(?Department $dept, ?string $role = null, array $extra = []): User
    {
        static $n = 0;
        $n++;
        $u = User::create(array_merge(['first_name' => 'QA', 'last_name' => "Goal {$n}", 'email' => 'qagoal' . uniqid() . '@example.test',
            'code' => 'QAGL' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
            'department_id' => optional($dept)->id], $extra));
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u->fresh();
    }

    private function at(string $day, string $time = '09:00:00'): void
    {
        Carbon::setTestNow(Carbon::parse("$day $time"));
    }

    private function pipeline(User $owner, string $name, string $product = 'Rust-X VCI bag', bool $report = false): int
    {
        return DB::table('sales_pipelines')->insertGetId(['party_name' => $name, 'salesperson_id' => $owner->id, 'product' => $product,
            'total_business_potential' => 12, 'is_locked' => $report ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function goal(User $u, NinetyDayRound $round, string $name = 'Acme'): NinetyDayGoal
    {
        return NinetyDayGoal::create(['user_id' => $u->id, 'round_id' => $round->id, 'client_name' => $name, 'product' => 'P', 'brand' => 'KIF',
            'goal' => 'Order', 'goal_type' => 'Order', 'monthly_potential' => 2.5, 'is_active' => true]);
    }

    private function body($res): array
    {
        return json_decode($res->getContent(), true);
    }

    private function ask(User $u, string $method, string $uri, array $data = []): Request
    {
        Auth::setUser($u);
        $req = Request::create($uri, $method, $data);
        $req->setUserResolver(fn () => $u);
        return $req;
    }

    // ── the weekly run and the round ─────────────────────────────────────

    public function test_the_monday_run_starts_a_13_week_round_for_sales_people_only(): void
    {
        $this->at(self::MONDAY);

        Goals::runWeekly();

        $r = NinetyDayRound::where('user_id', $this->rep->id)->firstOrFail();
        $this->assertSame(self::MONDAY, $r->starts_on->toDateString());
        $this->assertSame(Carbon::parse(self::MONDAY)->addDays(90)->toDateString(), $r->ends_on->toDateString());
        $this->assertTrue($r->ends_on->isSunday(), 'a round is 13 whole weeks, so the next Monday starts the next one');
        $this->assertSame(1, $r->round_no);
        $this->assertSame(0, NinetyDayRound::where('user_id', $this->outsider->id)->count(), 'not in Sales, no round');
    }

    public function test_the_run_is_safe_to_repeat(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $this->goal($this->rep, $round);

        Goals::runWeekly();
        Goals::runWeekly();

        $this->assertSame(1, NinetyDayRound::where('user_id', $this->rep->id)->count());
        $this->assertSame(1, NinetyDayGoalCheckin::whereIn('goal_id', NinetyDayGoal::where('user_id', $this->rep->id)->pluck('id'))->count());
        $this->assertSame(1, Task::where('user_id', $this->rep->id)->where('title', 'like', '90-Day Goals: submit W0%')->count(), 'one task, not two');
    }

    public function test_someone_with_no_customers_gets_an_add_your_customers_task_and_nothing_else(): void
    {
        $this->at(self::MONDAY);

        Goals::runWeekly();

        $tasks = Task::where('user_id', $this->rep->id)->get();
        $this->assertCount(1, $tasks);
        $this->assertStringContainsString('add your target customers', $tasks->first()->title);
    }

    public function test_each_monday_adds_one_task_and_the_check_in_rows(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::runWeekly();
        $this->assertSame(1, $g->checkins()->count(), 'W0');

        $this->at('2026-10-12');   // the next Monday
        $r = Goals::runWeekly();

        $this->assertSame([0, 1], $g->checkins()->pluck('week_no')->all(), 'W0 still pending, W1 opened');
        $this->assertSame(2, Task::where('user_id', $this->rep->id)->where('title', 'like', '90-Day Goals: submit%')->count());
        $this->assertGreaterThanOrEqual(1, $r['tasks']);
    }

    public function test_a_goal_added_midweek_is_not_late_on_day_one(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);

        $this->at('2026-10-07', '11:00:00');   // Wednesday of week 0
        $g = $this->goal($this->rep, $round);
        Goals::ensureCheckins($round);

        $this->assertSame(0, $g->checkins()->count(), 'W0 was Monday; it is not owed by someone who joined on Wednesday');
        $this->at('2026-10-12');
        Goals::ensureCheckins($round);
        $this->assertSame([1], $g->checkins()->pluck('week_no')->all(), 'their first status is next Monday');
    }

    public function test_the_round_closes_after_90_days_and_a_new_one_starts_on_the_next_monday(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        $g->checkins()->create(['round_id' => $round->id, 'due_on' => self::MONDAY, 'status' => 'On Track', 'week_no' => 0]);

        $this->at($round->ends_on->copy()->addDay()->toDateString());   // Monday after the last day
        Goals::runWeekly();

        $this->assertSame('closed', $round->fresh()->status);
        $this->assertNotNull($g->fresh()->closed_at);
        $new = Goals::activeRound($this->rep);
        $this->assertNotNull($new);
        $this->assertSame(2, $new->round_no);
        $this->assertSame($round->ends_on->copy()->addDay()->toDateString(), $new->starts_on->toDateString());
        $this->assertSame(0, NinetyDayGoal::where('round_id', $new->id)->count(), 'a fresh, empty round');
        $this->assertSame('On Track', $g->checkins()->first()->status, 'the closed round keeps its history');
    }

    // ── dynamic week counts (an admin-created program can run a different length) ────────

    public function test_a_self_started_round_is_still_exactly_13_weeks(): void
    {
        $round = Goals::roundFor($this->rep, Carbon::parse(self::MONDAY));
        $this->assertSame(13, Goals::weeksOf($round));
        $this->assertCount(13, Goals::weekDates($round));
    }

    public function test_a_shorter_program_has_fewer_weeks_and_a_longer_one_has_more(): void
    {
        $admin = $this->person(null, 'admin');
        $short = Goals::createProgram($admin, 'Six Weeks', Carbon::parse('2027-01-04'), Carbon::parse('2027-02-15'), [$this->rep->id]);
        $long = Goals::createProgram($admin, 'Half a Year', Carbon::parse('2027-01-04'), Carbon::parse('2027-07-05'), [$this->rep2->id]);

        // 42 days = exactly 6 weeks.
        $this->assertSame(6, Goals::weeksOf($short['enrolled'][0]['round']));
        // 182 days = 26 weeks (rounded up).
        $this->assertSame(26, Goals::weeksOf($long['enrolled'][0]['round']));
    }

    public function test_a_status_cannot_be_submitted_past_a_shorter_programs_real_last_week(): void
    {
        $this->at('2027-01-04');
        $admin = $this->person(null, 'admin');
        $result = Goals::createProgram($admin, 'Four Weeks', Carbon::parse('2027-01-04'), Carbon::parse('2027-02-01'), [$this->rep->id]);
        $round = $result['enrolled'][0]['round'];   // 28 days = 4 weeks (W0-W3)
        $g = $this->goal($this->rep, $round);

        $this->at('2027-01-25');   // week 3, the round's last real week
        [$saved, $errors] = Goals::submit($this->rep, 3, [$g->id => ['status' => 'On Track']]);
        $this->assertSame(1, $saved);
        $this->assertSame([], $errors);

        [$saved, $errors] = Goals::submit($this->rep, 4, [$g->id => ['status' => 'On Track']]);
        $this->assertSame(0, $saved);
        $this->assertSame(['That week does not exist.'], $errors);
    }

    public function test_mixed_round_lengths_in_one_dashboard_dont_truncate_the_longer_ones_strip(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        // rep is on the usual 13-week round; rep2 is on a 20-week program.
        $this->goal($this->rep, Goals::roundFor($this->rep));
        $long = Goals::createProgram($admin, 'Twenty Weeks', Carbon::parse(self::MONDAY), Carbon::parse(self::MONDAY)->addDays(140), [$this->rep2->id]);
        $this->goal($this->rep2, $long['enrolled'][0]['round']);

        $d = Goals::dashboard([$this->rep->id, $this->rep2->id]);
        $this->assertSame(20, $d['weeksSpan']);
        $this->assertCount(20, $d['strip']);
    }

    // ── auto-detected order status and upsell confirmation ─────────────────

    public function test_the_first_months_real_sale_auto_fills_the_status_to_regular_order(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $pid = $this->pipeline($this->rep, 'AutoCo');
        $g = $this->goal($this->rep, $round, 'AutoCo');
        $g->update(['sales_pipeline_id' => $pid]);
        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $pid, 'month_year' => Carbon::parse(self::MONDAY)->format('Y-m'), 'sale_amount' => 4.0, 'created_at' => now(), 'updated_at' => now()]);

        Goals::ensureCheckins($round);

        $ck = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();
        $this->assertSame('Regular Order', $ck->status);
        $this->assertTrue($ck->auto_filled);
        $this->assertFalse($ck->needs_upsell_confirm);
        $this->assertNotNull($ck->submitted_at);
    }

    public function test_no_sale_that_month_leaves_the_status_pending_not_auto_filled(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $pid = $this->pipeline($this->rep, 'QuietCo');
        $g = $this->goal($this->rep, $round, 'QuietCo');
        $g->update(['sales_pipeline_id' => $pid]);

        Goals::ensureCheckins($round);

        $ck = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();
        $this->assertNull($ck->status);
        $this->assertFalse($ck->auto_filled);
        $this->assertFalse($ck->needs_upsell_confirm);
    }

    public function test_a_second_months_sale_is_flagged_for_confirmation_not_silently_marked_upsell(): void
    {
        $this->at(self::MONDAY);   // the goal must exist from the round's own start, or firstWeek() skips week 0
        $round = Goals::roundFor($this->rep, Carbon::parse(self::MONDAY));
        $pid = $this->pipeline($this->rep, 'RepeatCo');
        $g = $this->goal($this->rep, $round, 'RepeatCo');
        $g->update(['sales_pipeline_id' => $pid]);
        $this->at('2026-11-02');   // a Monday, week 4 of a round starting self::MONDAY
        DB::table('sales_pipeline_months')->insert([
            ['sales_pipeline_id' => $pid, 'month_year' => '2026-10', 'sale_amount' => 3.0, 'created_at' => now(), 'updated_at' => now()],
            ['sales_pipeline_id' => $pid, 'month_year' => '2026-11', 'sale_amount' => 5.0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Goals::ensureCheckins($round);

        $week0 = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();
        $this->assertSame('Regular Order', $week0->status);
        $this->assertTrue($week0->auto_filled);

        $week4 = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 4)->first();
        $this->assertNull($week4->status);
        $this->assertTrue($week4->needs_upsell_confirm);
        $this->assertFalse($week4->auto_filled);
    }

    public function test_confirming_an_upsell_sets_the_status_and_clears_the_flag(): void
    {
        $week4 = $this->pendingUpsellCheckin();

        Goals::confirmUpsell($week4, $this->rep, true);
        $week4->refresh();

        $this->assertSame('Regular Order', $week4->status);
        $this->assertTrue($week4->upsell_confirmed);
        $this->assertFalse($week4->needs_upsell_confirm);
        $this->assertTrue($week4->auto_filled);
        $this->assertStringContainsString('repeat/upsell', $week4->comment);
    }

    public function test_declining_an_upsell_just_clears_the_flag_without_setting_a_status(): void
    {
        $week4 = $this->pendingUpsellCheckin();

        Goals::confirmUpsell($week4, $this->rep, false);
        $week4->refresh();

        $this->assertNull($week4->status);
        $this->assertFalse($week4->upsell_confirmed);
        $this->assertFalse($week4->needs_upsell_confirm);
    }

    public function test_the_confirm_upsell_endpoint_requires_being_allowed_on_that_goal(): void
    {
        $week4 = $this->pendingUpsellCheckin();
        $c = app(NinetyDayGoalController::class);

        $blocked = $c->confirmUpsell($this->ask($this->rep2, 'POST', '/x', ['checkin_id' => $week4->id, 'is_upsell' => true]));
        $this->assertSame(403, $blocked->getStatusCode());
        $this->assertNull($week4->fresh()->status, "a colleague's confirmation must not have taken effect");

        $ok = $c->confirmUpsell($this->ask($this->rep, 'POST', '/x', ['checkin_id' => $week4->id, 'is_upsell' => true]));
        $this->assertTrue($this->body($ok)['success']);
        $this->assertSame('Regular Order', $week4->fresh()->status);
    }

    public function test_confirm_upsell_endpoint_refuses_when_nothing_is_pending(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::ensureCheckins($round);
        $ck = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();

        $res = app(NinetyDayGoalController::class)->confirmUpsell($this->ask($this->rep, 'POST', '/x', ['checkin_id' => $ck->id, 'is_upsell' => true]));
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_mine_page_lists_pending_upsell_confirmations(): void
    {
        $week4 = $this->pendingUpsellCheckin();

        Auth::setUser($this->rep);
        $html = app(NinetyDayGoalController::class)->roundDetail($this->ask($this->rep, 'GET', '/x'), $week4->round_id)->render();

        $this->assertStringContainsString('RepeatCo', $html);
        $this->assertStringContainsString("it's an upsell", $html, 'confirmed on the program page now');
    }

    /** A goal whose week 4 checkin is already flagged needs_upsell_confirm (week 0 auto-filled first). */
    private function pendingUpsellCheckin(): NinetyDayGoalCheckin
    {
        $this->at(self::MONDAY);   // the goal must exist from the round's own start, or firstWeek() skips week 0
        $round = Goals::roundFor($this->rep, Carbon::parse(self::MONDAY));
        $pid = $this->pipeline($this->rep, 'RepeatCo');
        $g = $this->goal($this->rep, $round, 'RepeatCo');
        $g->update(['sales_pipeline_id' => $pid]);
        $this->at('2026-11-02');
        DB::table('sales_pipeline_months')->insert([
            ['sales_pipeline_id' => $pid, 'month_year' => '2026-10', 'sale_amount' => 3.0, 'created_at' => now(), 'updated_at' => now()],
            ['sales_pipeline_id' => $pid, 'month_year' => '2026-11', 'sale_amount' => 5.0, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Goals::ensureCheckins($round);

        return NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 4)->first();
    }

    // ── the weekly status ────────────────────────────────────────────────

    public function test_the_status_choices_are_the_sheets_seven(): void
    {
        $this->assertSame(['On Track', 'Off Track', 'Trial Order', 'Regular Order', 'No Report', 'Dropped', 'Transferred'], Goals::STATUSES);
        $this->assertSame(Goals::STATUSES, array_keys(Goals::STATUS_COLORS));
    }

    public function test_an_order_status_moves_a_pipeline_customer_to_the_sales_report_and_waits_for_a_real_sale(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $pid = $this->pipeline($this->rep, 'Order Co', 'P', false);   // still in the Sales Pipeline
        $g = $this->goal($this->rep, $round, 'Order Co');
        $g->update(['sales_pipeline_id' => $pid]);
        Goals::ensureCheckins($round);

        [$saved, $errors, $notes] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Trial Order', 'comment' => '']]);

        $this->assertSame([1, []], [$saved, $errors]);
        $p = DB::table('sales_pipelines')->find($pid);
        $this->assertSame(1, (int) $p->is_locked, 'on the Sales Report now');
        $this->assertSame('Order', $p->status_stage);
        $this->assertStringContainsString('moved from your Sales Pipeline to the Sales Report', implode(' ', $notes));
        $this->assertStringContainsString('counts once the sale appears on the Sales Report', implode(' ', $notes));

        $cards = Goals::dashboard([$this->rep->id])['cards'];
        $this->assertSame(0, $cards['converted'], 'an order with no sale on the Sales Report is not converted yet');
        $this->assertSame(1, $cards['ordersAwaiting']);
        $this->assertFalse(Goals::orderCheck($g->fresh())['confirmed']);

        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $pid, 'month_year' => '2026-10', 'sale_amount' => 1.75, 'created_at' => now(), 'updated_at' => now()]);

        $cards = Goals::dashboard([$this->rep->id])['cards'];
        $this->assertSame(1, $cards['converted'], 'confirmed by the Sales Report sale');
        $this->assertSame(0, $cards['ordersAwaiting']);
        $check = Goals::orderCheck($g->fresh());
        $this->assertTrue($check['confirmed']);
        $this->assertEquals(1.75, $check['amount'], 'the amount comes from the Sales Report');
    }

    public function test_re_saving_a_week_keeps_a_follow_up_date_already_on_record(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::ensureCheckins($round);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track', 'comment' => '', 'next_follow_up_date' => '2026-10-09']]);

        Goals::submit($this->rep, 0, [$g->id => ['status' => 'Off Track', 'comment' => 'Buyer away', 'next_follow_up_date' => null]]);

        $c = $g->checkins()->where('week_no', 0)->first();
        $this->assertSame('Off Track', $c->status);
        $this->assertSame('2026-10-09', $c->next_follow_up_date->format('Y-m-d'));
    }

    public function test_a_status_on_the_monday_needs_no_comment_and_is_not_late(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::ensureCheckins($round);

        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track', 'comment' => '']]);

        $this->assertSame([1, []], [$saved, $errors]);
        $c = $g->checkins()->first();
        $this->assertSame('On Track', $c->status);
        $this->assertFalse($c->is_late);
        $this->assertNotNull($c->submitted_at);
        $this->assertSame('On Track', $g->fresh()->w0, 'the older week column is kept in step');
    }

    public function test_a_monday_that_has_passed_needs_a_status_and_a_comment(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::ensureCheckins($round);

        $this->at('2026-10-07');   // Wednesday: W0 (Monday) is late
        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Off Track', 'comment' => '']]);
        $this->assertSame(0, $saved);
        $this->assertStringContainsString('comment', $errors[0]);
        $this->assertNull($g->checkins()->first()->status, 'nothing is saved without the comment');

        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Off Track', 'comment' => 'Buyer on leave until Friday']]);
        $this->assertSame([1, []], [$saved, $errors]);
        $c = $g->checkins()->first();
        $this->assertTrue($c->is_late);
        $this->assertSame('Buyer on leave until Friday', $c->comment);
    }

    public function test_a_future_week_and_an_unknown_status_are_refused(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::ensureCheckins($round);

        [$saved, $errors] = Goals::submit($this->rep, 3, [$g->id => ['status' => 'On Track', 'comment' => '']]);
        $this->assertSame(0, $saved);
        $this->assertStringContainsString('not due until', $errors[0]);

        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Going great', 'comment' => '']]);
        $this->assertSame(0, $saved);
        $this->assertStringContainsString('not a status you can pick', $errors[0]);
    }

    public function test_nobody_can_submit_for_someone_elses_goal(): void
    {
        $this->at(self::MONDAY);
        $mine = Goals::roundFor($this->rep);
        $theirs = $this->goal($this->rep2, Goals::roundFor($this->rep2));
        Goals::ensureCheckins($mine);

        [$saved, $errors] = Goals::submit($this->rep, 0, [$theirs->id => ['status' => 'On Track', 'comment' => '']]);

        $this->assertSame(0, $saved);
        $this->assertNotEmpty($errors);
    }

    public function test_the_monday_task_completes_itself_when_every_customer_has_a_status(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $a = $this->goal($this->rep, $round, 'A');
        $b = $this->goal($this->rep, $round, 'B');
        Goals::runWeekly();
        $task = Task::where('user_id', $this->rep->id)->where('title', 'like', '90-Day Goals: submit W0%')->firstOrFail();
        $this->assertSame('new', $task->status);

        Goals::submit($this->rep, 0, [$a->id => ['status' => 'On Track', 'comment' => '']]);
        $this->assertSame('new', $task->fresh()->status, 'one customer is still waiting');

        Goals::submit($this->rep, 0, [$b->id => ['status' => 'Trial Order', 'comment' => '']]);
        $this->assertSame('completed', $task->fresh()->status);
    }

    // ── choosing customers ───────────────────────────────────────────────

    public function test_customers_come_only_from_the_persons_own_pipeline_and_sales_report(): void
    {
        $mine = $this->pipeline($this->rep, 'My Prospect');
        $report = $this->pipeline($this->rep, 'My Booked Customer', 'Dr. Bio oil', true);
        $this->pipeline($this->rep2, 'Somebody Elses');

        $list = Goals::pipelineCustomers($this->rep);

        $this->assertEqualsCanonicalizing([$mine, $report], $list->pluck('id')->all());
        $this->assertSame('Sales Report', $list->firstWhere('id', $report)['source']);
        $this->assertSame('Dr. Bio', $list->firstWhere('id', $report)['brand'], 'brand worked out from the product');
    }

    public function test_adding_customers_saves_the_sheets_columns_and_refuses_anyone_elses_customer(): void
    {
        $this->at(self::MONDAY);
        $mine = $this->pipeline($this->rep, 'My Prospect');
        $foreign = $this->pipeline($this->rep2, 'Not Mine');

        $res = app(NinetyDayGoalController::class)->store($this->ask($this->rep, 'POST', '/x', ['customers' => [
            ['sales_pipeline_id' => $mine, 'product' => 'VCI bags', 'brand' => 'Rust-X', 'goal' => 'Regular monthly order', 'monthly_potential' => 3.5],
            ['sales_pipeline_id' => $foreign, 'product' => 'x', 'brand' => 'KIF', 'goal' => 'Order', 'monthly_potential' => 9],
        ]]));

        $body = $this->body($res);
        $this->assertSame(1, $body['added']);
        $this->assertNotEmpty($body['skipped']);
        $g = NinetyDayGoal::where('user_id', $this->rep->id)->firstOrFail();
        $this->assertSame(['My Prospect', 'VCI bags', 'Rust-X', 'Regular monthly order', 3.5], [$g->client_name, $g->product, $g->brand, $g->goal, $g->monthly_potential]);
        $this->assertSame($mine, (int) $g->sales_pipeline_id);
        $this->assertSame(0, NinetyDayGoal::where('sales_pipeline_id', $foreign)->count());
    }

    public function test_the_same_customer_is_not_added_twice_in_one_round(): void
    {
        $this->at(self::MONDAY);
        $mine = $this->pipeline($this->rep, 'My Prospect');
        $row = ['sales_pipeline_id' => $mine, 'product' => 'p', 'brand' => 'KIF', 'goal' => 'Order', 'monthly_potential' => 1];

        app(NinetyDayGoalController::class)->store($this->ask($this->rep, 'POST', '/x', ['customers' => [$row]]));
        $second = $this->body(app(NinetyDayGoalController::class)->store($this->ask($this->rep, 'POST', '/x', ['customers' => [$row]])));

        $this->assertSame(0, $second['added']);
        $this->assertSame(1, NinetyDayGoal::where('user_id', $this->rep->id)->count());
        $picker = $this->body(app(NinetyDayGoalController::class)->pipelineCustomers($this->ask($this->rep, 'GET', '/x')));
        $this->assertSame([], $picker['customers'], 'and it no longer shows in the picker');
    }

    public function test_someone_outside_sales_cannot_add_goals(): void
    {
        $this->at(self::MONDAY);
        $id = $this->pipeline($this->outsider, 'Whatever');

        $res = app(NinetyDayGoalController::class)->store($this->ask($this->outsider, 'POST', '/x', ['customers' => [
            ['sales_pipeline_id' => $id, 'product' => 'p', 'brand' => 'KIF', 'goal' => 'Order', 'monthly_potential' => 1]]]));

        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame(0, NinetyDayGoal::where('user_id', $this->outsider->id)->count());
    }

    // ── who sees what ────────────────────────────────────────────────────

    public function test_a_salesperson_sees_their_own_page_and_an_outsider_is_turned_away(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $this->goal($this->rep, $round, 'Mine Ltd');
        $this->goal($this->rep2, Goals::roundFor($this->rep2), 'Theirs Ltd');

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class,
            app(NinetyDayGoalController::class)->index($this->ask($this->rep, 'GET', '/performance/ninety-day-goals')), 'the old page sends them to My Programs');
        Auth::setUser($this->rep);
        $html = app(NinetyDayGoalController::class)->roundDetail($this->ask($this->rep, 'GET', '/x'), $round->id)->render();

        $this->assertStringContainsString('Mine Ltd', $html);
        $this->assertStringNotContainsString('Theirs Ltd', $html);
        $this->assertStringContainsString('Add Customer', $html);
        $this->assertStringNotContainsString('Proactive', $html, 'that column is not part of this tracker');

        $away = app(NinetyDayGoalController::class)->index($this->ask($this->outsider, 'GET', '/performance/ninety-day-goals'));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $away);
    }

    public function test_admin_sees_everyones_goals_with_the_week_by_week_statuses(): void
    {
        $this->at(self::MONDAY);
        $a = $this->goal($this->rep, Goals::roundFor($this->rep), 'Alpha Ltd');
        $b = $this->goal($this->rep2, Goals::roundFor($this->rep2), 'Beta Ltd');
        Goals::ensureCheckins(Goals::activeRound($this->rep));
        Goals::ensureCheckins(Goals::activeRound($this->rep2));
        Goals::submit($this->rep, 0, [$a->id => ['status' => 'Regular Order', 'comment' => 'Second PO in']]);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        // Filtered by customer: the table pages through everyone's real data in this database.
        $alpha = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/x', ['customer' => 'Alpha Ltd']))->render();
        $this->assertStringContainsString('Alpha Ltd', $alpha);
        $this->assertStringContainsString('Regular Order', $alpha);
        $this->assertStringContainsString('Beta Ltd', app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/x', ['customer' => 'Beta Ltd']))->render(), 'the whole team on the Program Dashboard');

        $own = app(NinetyDayGoalController::class)->roundDetail($this->ask($admin, 'GET', '/x'), Goals::activeRound($this->rep)->id)->render();
        $this->assertStringContainsString('Second PO in', $own, "and the status comment on that person's program page");
    }

    public function test_a_manager_sees_only_their_team(): void
    {
        $this->at(self::MONDAY);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();
        $this->goal($this->rep, Goals::roundFor($this->rep), 'Team Ltd');
        $this->goal($this->rep2, Goals::roundFor($this->rep2), 'Stranger Ltd');

        Auth::setUser($manager);
        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($manager, 'GET', '/x'))->render();

        $this->assertStringContainsString('Team Ltd', $html);
        $this->assertStringNotContainsString('Stranger Ltd', $html);
    }

    // ── the scorecard reads the new data ─────────────────────────────────

    public function test_the_sales_scorecard_source_uses_each_goals_latest_status(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $a = $this->goal($this->rep, $round, 'A');
        $b = $this->goal($this->rep, $round, 'B');
        $a->checkins()->create(['round_id' => $round->id, 'week_no' => 0, 'due_on' => self::MONDAY, 'status' => 'Trial Order']);
        $a->checkins()->create(['round_id' => $round->id, 'week_no' => 1, 'due_on' => '2026-10-12', 'status' => 'Regular Order']);   // latest counts
        $b->checkins()->create(['round_id' => $round->id, 'week_no' => 0, 'due_on' => self::MONDAY, 'status' => 'Dropped']);

        $pct = KpiAutoSourceService::ninetyDayGoals($this->rep->id, Carbon::parse('2026-10-01'), Carbon::parse('2027-03-31'));

        $this->assertSame(50.0, $pct);
        $this->assertNull(KpiAutoSourceService::ninetyDayGoals($this->rep2->id, Carbon::parse('2026-10-01'), Carbon::parse('2027-03-31')));
    }
    // ── reachable by the people it is for ────────────────────────────────

    public function test_every_goals_route_is_login_only_so_a_plain_salesperson_can_reach_it(): void
    {
        foreach (['index', 'pipeline-customers', 'store', 'checkin', 'update', 'destroy'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $roleGates = array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'));
            $this->assertSame([], array_values($roleGates), "{$name} must not sit behind an admin/HR/manager gate, or the Sales team gets a 403");
        }
    }

    public function test_the_sidebar_link_is_allowed_for_a_plain_salesperson(): void
    {
        \App\Support\Nav::flush();

        $this->assertTrue(\App\Support\Nav::allows('/performance/ninety-day-goals', $this->rep));
    }
    // ── comments and the dashboard ───────────────────────────────────────

    private function soldGoal(User $u, string $name, float $potential, string $brand, float $soldInOctober): NinetyDayGoal
    {
        $pid = $this->pipeline($u, $name, 'P', true);
        $g = $this->goal($u, Goals::roundFor($u), $name);
        $g->update(['sales_pipeline_id' => $pid, 'monthly_potential' => $potential, 'brand' => $brand]);
        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $pid, 'month_year' => '2026-10', 'sale_amount' => $soldInOctober, 'created_at' => now(), 'updated_at' => now()]);

        return $g->fresh();
    }

    public function test_achievement_is_what_was_sold_in_the_round_against_the_overall_target(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'Half Ltd', 6.0, 'KIF', 3.0);       // target 6, sold 3
        $this->soldGoal($this->rep, 'Over Ltd', 3.0, 'Rust-X', 6.0);    // target 3, sold 6

        $d = Goals::dashboard([$this->rep->id, $this->rep2->id]);

        $this->assertSame(2, $d['cards']['goals']);
        $this->assertEquals(9.0, $d['cards']['potential']);
        $this->assertEquals(9.0, $d['cards']['target'], 'the typed target is not multiplied by months');
        $this->assertEquals(9.0, $d['cards']['achieved']);
        $this->assertEquals(100.0, $d['cards']['pct']);
        $this->assertSame('Over Ltd', $d['topAchieved']->first()->goal->client_name);
    }

    public function test_sales_from_before_the_round_do_not_count(): void
    {
        $this->at(self::MONDAY);
        $g = $this->soldGoal($this->rep, 'Old Ltd', 1.0, 'KIF', 0.0);
        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $g->sales_pipeline_id, 'month_year' => '2026-08', 'sale_amount' => 50, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertEquals(0.0, Goals::dashboard([$this->rep->id])['cards']['achieved']);
    }

    public function test_the_round_page_shows_the_customer_minimum_the_window_countdown_and_what_can_be_edited(): void
    {
        config(['ninety_day.min_customers' => 10, 'ninety_day.window_rule_from' => null]);
        $round = Goals::roundFor($this->rep);
        $this->goal($this->rep, $round, 'One Ltd');
        $round->update(['customer_window_until' => now()->addHours(5)]);
        Auth::setUser($this->rep);

        $html = app(NinetyDayGoalController::class)->roundDetail($this->ask($this->rep, 'GET', '/x'), $round->id)->render();

        $this->assertStringContainsString('Customers (minimum 10)', $html);
        $this->assertStringContainsString('Incomplete', $html);
        $this->assertStringContainsString('Add 9 more customers', $html);
        $this->assertStringContainsString('id="ndgWinCountdown"', $html);
        $this->assertStringContainsString('1 of 1 targets editable', $html);
        $this->assertStringContainsString('data-bs-target="#ndgRdAddModal"', $html);
        $this->assertMatchesRegularExpression('#class="ndg-alert-red mb-3 ndg-alert-click" id="ndgMinAlert" role=button tabindex=0 data-bs-toggle=modal data-bs-target=\#ndgRdAddModal#', $html, 'the red alert opens the Add Customer popup');
        $this->assertStringContainsString('Add customers now', $html);
        $this->assertStringNotContainsString('Next Follow-up Date', $html, 'the weekly table has no follow-up date column any more');

        $dash = app(NinetyDayGoalController::class)->myDashboard($this->ask($this->rep, 'GET', '/x'))->render();
        $this->assertStringContainsString('/performance/ninety-day-goals/round/' . $round->id . '?add=1', $dash, 'the My Dashboard alert links straight into the popup');
        $this->assertStringContainsString('border-color:#e11d48', $dash);

        $round->update(['customer_window_until' => now()->subHour()]);
        $closed = app(NinetyDayGoalController::class)->roundDetail($this->ask($this->rep, 'GET', '/x'), $round->id)->render();

        $this->assertStringNotContainsString('id="ndgWinCountdown"', $closed);
        $this->assertStringContainsString('Ask admin/HR to reopen it', $closed);
        $this->assertStringContainsString('Adding closed', $closed);
        $this->assertStringContainsString('id="ndgMinAlert"', $closed, 'still red: the round is short');
        $this->assertStringNotContainsString('id="ndgMinAlert" role=button', $closed, 'but not clickable - adding is closed');
        $this->assertStringNotContainsString('data-bs-target="#ndgRdAddModal"', $closed, 'no Add Customer button once the window has closed');
    }

    public function test_a_round_starting_on_28_september_counts_october_to_december_not_september(): void
    {
        $this->at('2026-10-01');
        $g = $this->soldGoal($this->rep, 'A-Trac', 4.0, 'Rust-X', 0.0);
        $g->round->update(['starts_on' => '2026-09-28', 'ends_on' => '2026-12-31']);
        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $g->sales_pipeline_id, 'month_year' => '2026-09', 'sale_amount' => 4.08, 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(['2026-10', '2026-11', '2026-12'], Goals::roundMonths($g->round->fresh()));
        $cards = Goals::dashboard([$this->rep->id])['cards'];
        $this->assertEquals(4.0, $cards['target'], 'the 4 L typed is the overall target');
        $this->assertEquals(0.0, $cards['achieved'], "September's sale was booked before the round began");
    }

    public function test_each_month_belongs_to_exactly_one_of_two_back_to_back_rounds(): void
    {
        $a = new NinetyDayRound(['starts_on' => '2026-09-28', 'ends_on' => '2026-12-27']);
        $b = new NinetyDayRound(['starts_on' => '2026-12-28', 'ends_on' => '2027-03-28']);

        $this->assertSame(['2026-10', '2026-11', '2026-12'], Goals::roundMonths($a));
        $this->assertSame(['2027-01', '2027-02', '2027-03'], Goals::roundMonths($b));
    }

    public function test_top_customers_and_brands_are_ranked_biggest_first(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'Small Ltd', 1.0, 'KIF', 0);
        $this->soldGoal($this->rep, 'Big Ltd', 9.0, 'Rust-X', 0);
        $this->soldGoal($this->rep2, 'Mid Ltd', 4.0, 'Rust-X', 0);

        $d = Goals::dashboard([$this->rep->id, $this->rep2->id]);

        $this->assertSame(['Big Ltd', 'Mid Ltd', 'Small Ltd'], $d['topCustomers']->map(fn ($r) => $r->goal->client_name)->all());
        $this->assertSame('Rust-X', $d['topBrands']->first()['brand']);
        $this->assertEquals(13.0, $d['topBrands']->first()['potential']);
    }

    public function test_people_are_ranked_by_share_of_target_sold(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'A Ltd', 30.0, 'KIF', 3.0);     // 3 of 30 = 10%
        $this->soldGoal($this->rep2, 'B Ltd', 3.0, 'KIF', 3.0);     // 3 of 3 = 100%

        $users = Goals::dashboard([$this->rep->id, $this->rep2->id])['users'];

        $this->assertSame($this->rep2->id, $users->first()['id']);
        $this->assertEquals(100.0, $users->first()['pct']);
    }

    public function test_the_dashboard_only_counts_the_people_in_scope(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'Mine Ltd', 2.0, 'KIF', 1.0);
        $this->soldGoal($this->rep2, 'Theirs Ltd', 5.0, 'KIF', 1.0);

        $d = Goals::dashboard([$this->rep->id]);

        $this->assertSame(1, $d['cards']['goals']);
        $this->assertEquals(2.0, $d['cards']['potential']);
    }

    public function test_pages_render_for_admin_and_a_salesperson_sees_only_their_own(): void
    {
        $this->at(self::MONDAY);
        $mine = $this->soldGoal($this->rep, 'Mine Ltd', 2.0, 'KIF', 1.0);
        $this->soldGoal($this->rep2, 'Theirs Ltd', 5.0, 'KIF', 1.0);
        Goals::ensureCheckins(Goals::activeRound($this->rep));
        Goals::submit($this->rep, 0, [$mine->id => ['status' => 'Trial Order', 'comment' => 'Sample approved by purchase']]);
        $admin = $this->person(null, 'admin');
        $c = app(NinetyDayGoalController::class);

        Auth::setUser($admin);
        $adminDash = $c->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();
        $this->assertStringContainsString('Salesperson Performance', $adminDash);
        $this->assertStringContainsString('Theirs Ltd', $adminDash);

        // The retired /dashboard sends a salesperson to their own My Dashboard.
        Auth::setUser($this->rep);
        $away = $c->dashboard($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/dashboard'));
        $this->assertStringContainsString('/my-dashboard', $away->getTargetUrl());
        $repDash = $c->myDashboard($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/my-dashboard'))->render();
        $this->assertStringContainsString('Mine Ltd', $repDash);
        $this->assertStringNotContainsString('Theirs Ltd', $repDash);

        $adminComments = $c->comments($this->ask($admin, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringContainsString('Sample approved by purchase', $adminComments);

        $otherComments = $c->comments($this->ask($this->rep2, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringNotContainsString('Sample approved by purchase', $otherComments, "nobody reads a colleague's comments");

        Auth::setUser($this->outsider);
        $out = $c->dashboard($this->ask($this->outsider, 'GET', '/performance/ninety-day-goals/dashboard'));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $out);
    }

    public function test_the_new_pages_are_login_only_routes(): void
    {
        foreach (['dashboard', 'comments'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))), $name);
        }
    }
    public function test_reporting_on_time_counts_mondays_that_have_come_due_including_today(): void
    {
        $this->at(self::MONDAY);
        $a = $this->soldGoal($this->rep, 'A Ltd', 1.0, 'KIF', 0);
        $b = $this->soldGoal($this->rep, 'B Ltd', 1.0, 'KIF', 0);
        Goals::ensureCheckins(Goals::activeRound($this->rep));
        Goals::submit($this->rep, 0, [$a->id => ['status' => 'On Track']]);   // B is left unsent

        $cards = Goals::dashboard([$this->rep->id])['cards'];

        $this->assertSame(2, $cards['dueTotal'], "today's Monday is already due");
        $this->assertSame(1, $cards['onTimeTotal']);
        $this->assertEquals(50, $cards['discipline']);
    }
    // ── comments from both sides, and nothing the sales team can delete ──

    private function weekRow(User $u, string $client = 'Note Ltd'): array
    {
        $g = $this->goal($u, Goals::roundFor($u), $client);
        Goals::ensureCheckins(Goals::activeRound($u));

        return [$g, NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first()];
    }

    private function postNote(User $u, NinetyDayGoal $g, int $week, string $body)
    {
        return app(NinetyDayGoalController::class)->note($this->ask($u, 'POST', '/performance/ninety-day-goals/notes', ['goal_id' => $g->id, 'week_no' => $week, 'body' => $body]));
    }

    public function test_the_salesperson_and_an_admin_can_both_add_comments_to_a_week(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        $admin = $this->person(null, 'admin');

        $mine = $this->body($this->postNote($this->rep, $g, 0, 'Sample sent, waiting on lab'));
        $theirs = $this->body($this->postNote($admin, $g, 0, 'Push for a trial order this week'));

        $this->assertTrue($mine['success']);
        $this->assertTrue($theirs['success']);
        $this->assertSame(2, \App\Models\NinetyDayGoalNote::where('goal_id', $g->id)->count());
        $this->assertFalse($mine['note']['can_delete'], 'the sales team cannot remove their own comment');
        $this->assertTrue($theirs['note']['can_delete']);
    }

    public function test_a_colleague_cannot_comment_but_the_reporting_manager_can(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();

        $this->assertSame(403, $this->postNote($this->rep2, $g, 0, 'not mine')->getStatusCode());
        $this->assertTrue($this->body($this->postNote($manager, $g, 0, 'Good progress'))['success']);
    }

    public function test_a_comment_needs_a_week_that_has_started(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);

        $res = $this->postNote($this->rep, $g, 5, 'too early');

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame(0, \App\Models\NinetyDayGoalNote::where('goal_id', $g->id)->count());
    }

    public function test_the_sales_team_cannot_delete_a_customer_or_a_comment_but_admin_can(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        $note = $this->body($this->postNote($this->rep, $g, 0, 'stays'))['note'];
        $admin = $this->person(null, 'admin');
        $c = app(NinetyDayGoalController::class);

        Auth::setUser($this->rep);
        $this->assertSame(403, $c->destroy($g->id)->getStatusCode(), 'even with no status yet');
        $this->assertSame(403, $c->destroyNote($note['id'])->getStatusCode());
        $this->assertNotNull(NinetyDayGoal::find($g->id));

        Auth::setUser($admin);
        $this->assertTrue($c->destroyNote($note['id'])->getData()->success);
        $this->assertTrue($c->destroy($g->id)->getData()->success);
        $this->assertNull(NinetyDayGoal::find($g->id));
        $this->assertSame(0, NinetyDayGoalCheckin::where('goal_id', $g->id)->count(), 'its weeks go with it');
    }

    public function test_a_status_already_on_record_can_be_edited_but_keeps_its_original_submitted_at(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);
        $first = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();

        $this->at(self::MONDAY, '15:00:00');
        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Dropped', 'comment' => 'Actually lost this one']]);

        $this->assertSame(1, $saved);
        $this->assertEmpty($errors);
        $row = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();
        $this->assertSame('Dropped', $row->status);
        $this->assertSame('Actually lost this one', $row->comment);
        $this->assertTrue($row->submitted_at->eq($first->submitted_at), 'the original report time never moves');
        $this->assertNotNull($row->edited_at);
        $this->assertSame($this->rep->id, $row->edited_by_id);
    }

    public function test_a_colleague_cannot_edit_someone_elses_status(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);

        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Dropped']], null, $this->rep2);

        $this->assertSame(0, $saved);
        $this->assertNotEmpty($errors);
        $this->assertSame('On Track', NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->value('status'));
    }

    public function test_an_admin_can_edit_a_salespersons_status_on_their_behalf(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);
        $admin = $this->person(null, 'admin');

        [$saved, $errors] = Goals::submit($this->rep, 0, [$g->id => ['status' => 'Off Track', 'comment' => 'Corrected by admin']], null, $admin);

        $this->assertSame(1, $saved);
        $this->assertEmpty($errors);
        $row = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();
        $this->assertSame('Off Track', $row->status);
        $this->assertSame($admin->id, $row->edited_by_id);
        $this->assertSame($this->rep->id, $row->submitted_by_id, 'the original reporter is still on record');
    }

    public function test_the_checkin_endpoint_lets_admin_edit_on_behalf_of_a_named_salesperson(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);
        $admin = $this->person(null, 'admin');

        $res = app(NinetyDayGoalController::class)->checkin($this->ask($admin, 'POST', '/x', [
            'week_no' => 0, 'user_id' => $this->rep->id,
            'entries' => [['goal_id' => $g->id, 'status' => 'Regular Order', 'comment' => 'Confirmed with the customer']],
        ]));

        $this->assertTrue($this->body($res)['success']);
        $row = NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 0)->first();
        $this->assertSame('Regular Order', $row->status);
        $this->assertSame($admin->id, $row->edited_by_id);
    }

    public function test_the_admin_page_lists_the_whole_team_in_one_table_including_people_with_no_customers(): void
    {
        $this->at(self::MONDAY);
        $this->weekRow($this->rep, 'Alpha Ltd');
        $this->weekRow($this->rep2, 'Beta Ltd');
        $idle = $this->person($this->sales);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $this->assertStringContainsString('Alpha Ltd', app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/x', ['customer' => 'Alpha Ltd']))->render());
        $this->assertStringContainsString('Beta Ltd', app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/x', ['customer' => 'Beta Ltd']))->render());
        $this->assertStringContainsString('class="ndg-note-del"', $this->withNote($admin, 'Alpha Ltd'), 'admin sees the remove button on a comment');
    }

    private function withNote(User $admin, string $client): string
    {
        $g = NinetyDayGoal::where('client_name', $client)->latest('id')->first();
        $this->postNote($admin, $g, 0, 'Seen by admin');
        Auth::setUser($admin);

        return app(NinetyDayGoalController::class)->roundDetail($this->ask($admin, 'GET', '/x'), $g->round_id)->render();
    }

    public function test_a_salesperson_sees_no_delete_button_and_the_comments_page_lists_both_kinds(): void
    {
        $this->at(self::MONDAY);
        [$g] = $this->weekRow($this->rep, 'Delete Me Ltd');
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track', 'comment' => 'first look']]);
        $this->postNote($this->person(null, 'admin'), $g, 0, 'admin says hurry');
        $c = app(NinetyDayGoalController::class);

        Auth::setUser($this->rep);
        $mine = $c->roundDetail($this->ask($this->rep, 'GET', '/x'), $g->round_id)->render();
        $this->assertStringNotContainsString('class="ndg-note-del"', $mine, 'no delete button for the sales team');
        $this->assertStringContainsString('admin says hurry', $mine);
        $this->assertStringContainsString('ndg-addnote', $mine, 'and can add their own comment');

        $page = $c->comments($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringContainsString('first look', $page);
        $this->assertStringContainsString('admin says hurry', $page);
    }

    public function test_the_comment_routes_are_login_only(): void
    {
        foreach (['note', 'note.destroy'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))), $name);
        }
    }
    public function test_the_dashboard_people_table_includes_everyone_in_scope_not_just_those_with_goals(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'Has Goals Ltd', 2.0, 'KIF', 0);
        $idle = $this->person($this->sales);   // $this->rep2 and $idle both have no customers

        $users = Goals::dashboard([$this->rep->id, $this->rep2->id, $idle->id])['users'];

        $this->assertSame(3, $users->count());
        $withGoal = $users->firstWhere('id', $this->rep->id);
        $noGoal = $users->firstWhere('id', $idle->id);
        $this->assertSame(1, $withGoal['goals']);
        $this->assertSame(0, $noGoal['goals']);
        $this->assertNull($noGoal['pct']);
        $this->assertSame($this->rep->id, $users->first()['id'], 'the one with a target sorts first');
    }
    // ── the Monday task cannot be closed without the week's data ─────────

    private function mondayTask(User $u): \App\Models\Task
    {
        Goals::runWeekly(Carbon::parse(self::MONDAY));

        return \App\Models\Task::where('user_id', $u->id)->where('title', 'like', '90-Day Goals:%')->firstOrFail();
    }

    public function test_the_weekly_task_refuses_to_close_from_my_tasks_until_every_customer_has_a_status(): void
    {
        $this->at(self::MONDAY);
        $g = $this->goal($this->rep, Goals::roundFor($this->rep), 'Guard Ltd');
        Goals::ensureCheckins(Goals::activeRound($this->rep));
        $task = $this->mondayTask($this->rep);

        Auth::setUser($this->rep);
        $blocked = app(\App\Http\Controllers\tenant\MyTasksController::class)->updateStatus(
            Request::create('/x', 'POST', ['status' => 'completed']), $task
        );
        $this->assertSame(422, $blocked->getStatusCode());
        $this->assertStringContainsString('Give a status for every customer', $this->body($blocked)['message']);
        $this->assertSame('new', $task->fresh()->status);

        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);
        // completeTaskIfDone already closed it on submit; reopen it to prove the endpoint itself also lets a truly done one through.
        $task->update(['status' => 'new', 'completed_at' => null]);
        $ok = app(\App\Http\Controllers\tenant\MyTasksController::class)->updateStatus(
            Request::create('/x', 'POST', ['status' => 'completed']), $task->fresh()
        );
        $this->assertTrue($this->body($ok)['success']);
    }

    public function test_the_add_customers_task_refuses_to_close_with_no_customers_added(): void
    {
        $this->at(self::MONDAY);
        $task = $this->mondayTask($this->rep);
        $this->assertStringContainsString('add your target customers', $task->title);

        Auth::setUser($this->rep);
        $blocked = app(\App\Http\Controllers\tenant\MyTasksController::class)->updateStatus(
            Request::create('/x', 'POST', ['status' => 'completed']), $task
        );

        $this->assertSame(422, $blocked->getStatusCode());
        $this->assertStringContainsString('Add at least one target customer', $this->body($blocked)['message']);
    }

    public function test_the_task_controller_endpoint_is_guarded_the_same_way(): void
    {
        $this->at(self::MONDAY);
        $g = $this->goal($this->rep, Goals::roundFor($this->rep), 'Guard Two Ltd');
        Goals::ensureCheckins(Goals::activeRound($this->rep));
        $task = $this->mondayTask($this->rep);

        Auth::setUser($this->rep);
        $res = app(\App\Http\Controllers\tenant\TaskController::class)->updateStatus(
            Request::create('/x', 'POST', ['status' => 'completed']), $task->id
        );

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame('new', $task->fresh()->status);
    }

    public function test_marking_a_task_in_progress_is_never_blocked(): void
    {
        $this->at(self::MONDAY);
        $this->goal($this->rep, Goals::roundFor($this->rep), 'Guard Three Ltd');
        Goals::ensureCheckins(Goals::activeRound($this->rep));
        $task = $this->mondayTask($this->rep);

        Auth::setUser($this->rep);
        $res = app(\App\Http\Controllers\tenant\MyTasksController::class)->updateStatus(
            Request::create('/x', 'POST', ['status' => 'in_progress']), $task
        );

        $this->assertTrue($this->body($res)['success']);
    }
    public function test_switching_to_all_rounds_widens_the_summary_cards_not_just_the_table(): void
    {
        $this->at(self::MONDAY);
        $active = $this->goal($this->rep, Goals::roundFor($this->rep), 'Active Ltd');
        $closedRound = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 0, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $closedRound, 'From A Closed Round Ltd');
        // A manager whose team is only $this->rep, so the server-side scope (unlike the client-side
        // salesperson filter) is exactly this one person's goals — the count below is then exact
        // regardless of whatever other 90-Day Goals data already lives in the database.
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();

        // The summary the dashboards are built from (the old Goals page that showed it is retired).
        $scope = Goals::visibleUserIds($manager);
        $current = Goals::dashboard($scope, ['round' => 'current']);
        $this->assertSame(1, (int) $current['cards']['goals']);
        $all = Goals::dashboard($scope, ['round' => 'all']);
        $this->assertSame(2, (int) $all['cards']['goals'],
            'the summary card must widen to 2 with the closed round included, not silently stay at 1');
    }
    // ── past rounds, each kept on its own ─────────────────────────────────

    public function test_the_past_list_shows_one_card_per_finished_round_not_merged_together(): void
    {
        $this->at(self::MONDAY);
        $roundA = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $roundA, 'Old Ltd');
        $roundB = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 2, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-04-06', 'ends_on' => '2026-07-05', 'status' => 'closed']);
        $this->goal($this->rep, $roundB, 'Newer Ltd');
        $admin = $this->person(null, 'admin');

        $html = app(NinetyDayGoalController::class)->past($this->ask($admin, 'GET', '/performance/ninety-day-goals/past'))->render();

        $this->assertStringContainsString('Round 1', $html);
        $this->assertStringContainsString('Round 2', $html);
        $this->assertSame(2, substr_count($html, route('performance.ninety-day-goals.past.show', $roundA->id))
            + substr_count($html, route('performance.ninety-day-goals.past.show', $roundB->id)), 'each round links to its own report');
    }

    public function test_a_past_rounds_report_shows_only_that_rounds_customers_and_comments(): void
    {
        $this->at(self::MONDAY);
        $roundA = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $gA = $this->goal($this->rep, $roundA, 'Round A Ltd');
        $gA->checkins()->create(['round_id' => $roundA->id, 'week_no' => 0, 'due_on' => '2026-01-05', 'status' => 'On Track', 'comment' => 'From round A']);
        $roundB = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 2, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-04-06', 'ends_on' => '2026-07-05', 'status' => 'closed']);
        $gB = $this->goal($this->rep, $roundB, 'Round B Ltd');
        $gB->checkins()->create(['round_id' => $roundB->id, 'week_no' => 0, 'due_on' => '2026-04-06', 'status' => 'Off Track', 'comment' => 'From round B']);
        $admin = $this->person(null, 'admin');

        $this->ask($admin, 'GET', '/x');
        $html = app(NinetyDayGoalController::class)->pastRound($roundA->id)->render();

        $this->assertStringContainsString('Round A Ltd', $html);
        $this->assertStringContainsString('From round A', $html);
        $this->assertStringNotContainsString('Round B Ltd', $html);
        $this->assertStringNotContainsString('From round B', $html);
    }

    public function test_a_colleague_cannot_open_someone_elses_past_round(): void
    {
        $this->at(self::MONDAY);
        $round = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $round, 'Private Ltd');

        Auth::setUser($this->rep2);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NinetyDayGoalController::class)->pastRound($round->id);
    }

    public function test_the_past_routes_are_login_only(): void
    {
        foreach (['past', 'past.show'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))), $name);
        }
    }
    public function test_the_past_list_has_a_closed_round_leaderboard_ranked_by_achievement(): void
    {
        $this->at(self::MONDAY);
        $roundA = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $roundA, 'Weak Ltd');   // monthly_potential 2.5, no sales -> 0%
        $roundB = NinetyDayRound::create(['user_id' => $this->rep2->id, 'round_no' => 1, 'tenant_id' => $this->rep2->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $g2 = $this->goal($this->rep2, $roundB, 'Strong Ltd');
        $pid = $this->pipeline($this->rep2, 'Strong Pipe', 'P', true);
        $g2->update(['sales_pipeline_id' => $pid]);
        foreach (['2026-01', '2026-02', '2026-03'] as $m) {
            DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $pid, 'month_year' => $m, 'sale_amount' => 2.5, 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = $this->person(null, 'admin');

        $this->ask($admin, 'GET', '/x');
        $html = app(NinetyDayGoalController::class)->past($this->ask($admin, 'GET', '/performance/ninety-day-goals/past'))->render();

        $this->assertStringContainsString('Closed-round record, by salesperson', $html);
        $strongPos = strpos($html, htmlspecialchars($this->rep2->last_name));
        $weakPos = strpos($html, htmlspecialchars($this->rep->last_name));
        $this->assertNotFalse($strongPos);
        $this->assertNotFalse($weakPos);
        $this->assertLessThan($weakPos, $strongPos, 'the fully achieved round outranks the 0% one');
    }

    public function test_a_past_rounds_report_ranks_customers_by_achievement_not_potential(): void
    {
        $this->at(self::MONDAY);
        $round = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $big = $this->goal($this->rep, $round, 'Big But Zero Ltd');
        $big->update(['monthly_potential' => 20]);
        $small = $this->goal($this->rep, $round, 'Small But Full Ltd');
        $small->update(['monthly_potential' => 1]);
        $pid = $this->pipeline($this->rep, 'Small Pipe', 'P', true);
        $small->update(['sales_pipeline_id' => $pid]);
        foreach (['2026-01', '2026-02', '2026-03'] as $m) {
            DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $pid, 'month_year' => $m, 'sale_amount' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $admin = $this->person(null, 'admin');
        $this->ask($admin, 'GET', '/x');

        $html = app(NinetyDayGoalController::class)->pastRound($round->id)->render();

        $this->assertLessThan(strpos($html, 'Big But Zero Ltd'), strpos($html, 'Small But Full Ltd'),
            'the fully achieved small target ranks above the untouched big one');
    }
    // ── the round report: everything on the one page ───────────────────────

    public function test_the_round_report_has_the_headline_the_result_table_the_matrix_and_the_full_grid_all_on_one_page(): void
    {
        $this->at(self::MONDAY);
        $round = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $g = $this->goal($this->rep, $round, 'One Page Ltd');
        $g->checkins()->create(['round_id' => $round->id, 'week_no' => 0, 'due_on' => '2026-01-05', 'status' => 'On Track']);
        $g->checkins()->create(['round_id' => $round->id, 'week_no' => 1, 'due_on' => '2026-01-12', 'status' => null]);   // never sent
        $admin = $this->person(null, 'admin');
        $this->ask($admin, 'GET', '/x');

        $html = app(NinetyDayGoalController::class)->pastRound($round->id)->render();

        $this->assertStringContainsString('ndg-hero-num', $html, 'the headline number');
        $this->assertStringContainsString('Result, by customer', $html, 'the ranked result table');
        $this->assertStringContainsString('Every customer, every week', $html, 'the compact matrix');
        $this->assertStringContainsString('Week-by-week detail', $html, 'the full status/comment grid');
        $this->assertStringContainsString('Where this round', $html, 'the trial-order funnel');
        $this->assertStringContainsString('customers needed a look', $html, 'the needs-attention section');
        // Result table, matrix, detail grid, and Needs attention (its W1 was never answered) — four sections, one customer.
        $this->assertSame(4, substr_count($html, 'One Page Ltd'));
        $this->assertStringContainsString('W0: On Track', $html, 'the matrix square tooltip');
        $this->assertStringContainsString('W1: Not sent', $html);
        $this->assertStringContainsString('ndg-mx-notsent', $html);
    }

    public function test_someone_outside_the_team_cannot_open_a_past_round(): void
    {
        $this->at(self::MONDAY);
        $round = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $round, 'Blocked Ltd');
        Auth::setUser($this->rep2);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NinetyDayGoalController::class)->pastRound($round->id);
    }

    public function test_the_person_route_is_login_only(): void
    {
        $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.person');
        $this->assertNotNull($route);
        $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))));
    }

    // ── the salesperson profile page ───────────────────────────────────────

    public function test_a_persons_page_adds_up_every_round_theyve_ever_had(): void
    {
        $this->at(self::MONDAY);
        $closed = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $closed, 'Old Career Ltd');
        $active = Goals::roundFor($this->rep);
        $this->goal($this->rep, $active, 'New Career Ltd');
        $admin = $this->person(null, 'admin');
        $this->ask($admin, 'GET', '/x');

        $html = app(NinetyDayGoalController::class)->person($this->rep->id)->render();

        $this->assertStringContainsString('Career achievement', $html);
        $this->assertStringContainsString('Old Career Ltd', $html, 'top customers pull from every round, not just the active one');
        $this->assertStringContainsString('running now', $html);
        $this->assertStringContainsString('2 rounds', $html);
    }

    public function test_a_colleague_cannot_open_someone_elses_person_page_but_their_manager_can(): void
    {
        $this->at(self::MONDAY);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();

        Auth::setUser($this->rep2);
        try {
            app(NinetyDayGoalController::class)->person($this->rep->id);
            $this->fail('a colleague should have been refused');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->ask($manager, 'GET', '/x');
        $html = app(NinetyDayGoalController::class)->person($this->rep->id)->render();
        $this->assertStringContainsString('Career achievement', $html);
    }
    // ── Team, Weekly matrix, Trial orders, Needs attention — all sections of one Dashboard ──

    public function test_the_dashboard_carries_team_matrix_trials_and_attention_as_its_own_sections(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round, 'Team Ltd');
        Goals::ensureCheckins($round);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);
        // $this->rep2 has no customers in the round.
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $view = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'));
        $html = $view->render();

        // People are checked in the data: a real dev database has 200+ salespeople and the table shows the top ones.
        $ids = collect($view->getData()['salespeople'])->pluck('id');
        $this->assertTrue($ids->contains($this->rep->id), 'Salesperson Performance');
        $this->assertTrue($ids->contains($this->rep2->id), 'idle salespeople are listed too');
        $this->assertTrue($view->getData()['trialing'] !== null && $view->getData()['allRowsCount'] >= 1, 'customer rows are there (paged on screen)');
        foreach (['Weekly Submissions', 'At Trial Order right now', 'need a look', 'Order Status'] as $section) {
            $this->assertStringContainsString($section, $html, $section);
        }
    }

    public function test_trial_orders_watchlist_only_lists_customers_at_trial_order(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $trial = $this->goal($this->rep, $round, 'Trialing Ltd');
        $onTrack = $this->goal($this->rep, $round, 'Steady Ltd');
        Goals::ensureCheckins($round);
        Goals::submit($this->rep, 0, [$trial->id => ['status' => 'Trial Order'], $onTrack->id => ['status' => 'On Track']]);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();

        // The watchlist card only holds the Trial Order row, even though other sections
        // (Customer Tracking, Weekly Submissions) legitimately mention both customers.
        $watchlist = substr($html, strpos($html, 'At Trial Order right now'));
        $watchlist = substr($watchlist, 0, strpos($watchlist, 'data-section="risk"'));
        $this->assertStringContainsString('Trialing Ltd', $watchlist);
        $this->assertStringNotContainsString('Steady Ltd', $watchlist, 'only Trial Order customers are in the watchlist');
    }

    public function test_needs_attention_lists_off_track_and_overdue_customers_but_not_healthy_ones(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $offTrack = $this->goal($this->rep, $round, 'Off Track Ltd');
        $healthy = $this->goal($this->rep, $round, 'Healthy Ltd');
        $silent = $this->goal($this->rep, $round, 'Silent Ltd');
        Goals::ensureCheckins($round);
        Goals::submit($this->rep, 0, [$offTrack->id => ['status' => 'Off Track'], $healthy->id => ['status' => 'On Track']]);
        // Silent Ltd's W0 is left unanswered.
        $this->at('2026-10-12');   // the next Monday, so W0 is now overdue
        Goals::ensureCheckins($round);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();
        $risk = substr($html, strpos($html, 'data-section="risk"'));

        $this->assertStringContainsString('need a look', $risk);
        $this->assertStringContainsString('Off Track Ltd', $risk);
        $this->assertStringContainsString('Silent Ltd', $risk);
    }

    public function test_a_salesperson_reaches_the_dashboard_scoped_to_only_their_own_data(): void
    {
        $this->at(self::MONDAY);
        $this->goal($this->rep, Goals::roundFor($this->rep), 'Mine Only Ltd');
        $this->goal($this->rep2, Goals::roundFor($this->rep2), 'Not Mine Ltd');
        $c = app(NinetyDayGoalController::class);

        Auth::setUser($this->rep);
        // Neither the retired /dashboard nor the admin/team dashboard shows a plain salesperson
        // anything but their own My Dashboard.
        $this->assertStringContainsString('/my-dashboard', $c->dashboard($this->ask($this->rep, 'GET', '/x'))->getTargetUrl());
        $this->assertStringContainsString('/my-dashboard', $c->programDashboard($this->ask($this->rep, 'GET', '/x'))->getTargetUrl());

        $html = $c->myDashboard($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/my-dashboard'))->render();
        $this->assertStringContainsString('Mine Only Ltd', $html);
        $this->assertStringNotContainsString('Not Mine Ltd', $html);
    }
    // ── the reporting sidebar: every round, live or past, carries the same set ─

    public function test_a_past_rounds_trial_order_watchlist_and_attention_list_are_scoped_to_just_that_round(): void
    {
        $this->at(self::MONDAY);
        $round = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $trial = $this->goal($this->rep, $round, 'Round Trial Ltd');
        $trial->checkins()->create(['round_id' => $round->id, 'week_no' => 0, 'due_on' => '2026-01-05', 'status' => 'Trial Order']);
        $dropped = $this->goal($this->rep, $round, 'Round Dropped Ltd');
        $dropped->checkins()->create(['round_id' => $round->id, 'week_no' => 0, 'due_on' => '2026-01-05', 'status' => 'Dropped']);
        $admin = $this->person(null, 'admin');
        $this->ask($admin, 'GET', '/x');

        $html = app(NinetyDayGoalController::class)->pastRound($round->id)->render();

        $this->assertStringContainsString('Round Trial Ltd', $html);
        $this->assertStringContainsString('Round Dropped Ltd', $html);
        $this->assertStringContainsString('At Trial Order', $html);
        $this->assertStringContainsString('data-ndg-jump="trials"', $html);
        $this->assertStringContainsString('data-ndg-jump="attention"', $html);
    }

    public function test_the_dashboard_and_a_past_round_both_carry_the_same_report_sidebar(): void
    {
        $this->at(self::MONDAY);
        $round = NinetyDayRound::create(['user_id' => $this->rep->id, 'round_no' => 1, 'tenant_id' => $this->rep->tenant_id,
            'starts_on' => '2026-01-05', 'ends_on' => '2026-04-05', 'status' => 'closed']);
        $this->goal($this->rep, $round, 'Sidebar Ltd');
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $past = app(NinetyDayGoalController::class)->pastRound($round->id)->render();
        foreach (['overview', 'result', 'matrix', 'trials', 'attention', 'detail'] as $section) {
            $this->assertStringContainsString('id="' . $section . '"', $past, "past round section {$section}");
        }
    }

    // ── admin-created programs ───────────────────────────────────────────

    public function test_creating_a_program_enrols_everyone_invited_with_the_admins_chosen_dates(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $starts = Carbon::parse('2027-01-04');   // a Monday, deliberately not "today"
        $ends = Carbon::parse('2027-04-05');

        $result = Goals::createProgram($admin, 'Q1 Push', $starts, $ends, [$this->rep->id, $this->rep2->id]);

        $this->assertSame('Q1 Push', $result['program']->name);
        $this->assertCount(2, $result['enrolled']);
        $this->assertCount(0, $result['skipped']);

        $round = NinetyDayRound::where('user_id', $this->rep->id)->first();
        $this->assertSame($result['program']->id, $round->program_id);
        $this->assertSame('2027-01-04', $round->starts_on->toDateString(), 'the exact date the admin picked, not a computed Monday');
        $this->assertSame('2027-04-05', $round->ends_on->toDateString());
        $this->assertSame('active', $round->status);

        $task = Task::where('user_id', $this->rep->id)->where('title', 'like', '90-Day Goals: add your target customers%')->first();
        $this->assertNotNull($task);
        $this->assertSame($admin->id, $task->assigned_by_id);
        $this->assertSame('2027-01-07', $task->due_date->toDateString(), 'three days from the start date');
    }

    public function test_someone_already_mid_round_is_skipped_not_replaced(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $existing = Goals::roundFor($this->rep);   // rep already has an active round...
        $this->goal($this->rep, $existing);        // ...with a customer in it: genuinely mid-round

        $result = Goals::createProgram($admin, 'Overlap Test', Carbon::parse('2027-01-04'), Carbon::parse('2027-04-05'), [$this->rep->id, $this->rep2->id]);

        $this->assertCount(1, $result['enrolled']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($this->rep->id, $result['skipped'][0]->id);
        $this->assertSame($existing->id, Goals::activeRound($this->rep)->id, 'their existing round is untouched');
    }

    public function test_a_customer_added_midweek_on_the_page_is_not_overdue(): void
    {
        $this->at('2026-10-07');   // a Wednesday in the round that starts Monday 5 Oct
        $round = Goals::roundFor($this->rep);
        $pid = $this->pipeline($this->rep, 'Midweek Co');

        app(NinetyDayGoalController::class)->store($this->ask($this->rep, 'POST', '/x', ['customers' => [
            ['sales_pipeline_id' => $pid, 'goal' => 'Order', 'monthly_potential' => 2],
        ]]));

        $this->assertCount(0, Goals::pending($round), 'nothing is due for a customer added after this Monday');
        $g = NinetyDayGoal::where('round_id', $round->id)->firstOrFail();
        $row = NinetyDayGoalCheckin::where('goal_id', $g->id)->firstOrFail();
        $this->assertSame(1, (int) $row->week_no, 'its first row is next Monday\'s');
        $this->assertNotNull($row->next_follow_up_date, 'the default follow-up date is still set');
    }

    public function test_the_owner_cannot_move_the_target_once_reported_but_admin_can(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);
        Goals::submit($this->rep, 0, [$g->id => ['status' => 'On Track']]);
        $admin = $this->person(null, 'admin');

        $res = app(NinetyDayGoalController::class)->update($this->ask($this->rep, 'PUT', '/x', ['monthly_potential' => 0.5]), $g->id);
        $this->assertSame(422, $res->getStatusCode());
        $this->assertEquals(2.5, $g->fresh()->monthly_potential);

        $ok = app(NinetyDayGoalController::class)->update($this->ask($this->rep, 'PUT', '/x', ['goal' => 'Repeat Order']), $g->id);
        $this->assertTrue($this->body($ok)['success'], 'other fields can still be edited');

        $byAdmin = app(NinetyDayGoalController::class)->update($this->ask($admin, 'PUT', '/x', ['monthly_potential' => 4]), $g->id);
        $this->assertTrue($this->body($byAdmin)['success']);
        $this->assertEquals(4.0, $g->fresh()->monthly_potential);
    }

    public function test_week_13_and_later_of_a_long_program_can_be_submitted(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        Goals::createProgram($admin, 'Long drive', Carbon::parse('2027-01-04'), Carbon::parse('2027-05-10'), [$this->rep->id]);   // 18 weeks
        $round = Goals::activeRound($this->rep);
        $g = $this->goal($this->rep, $round);
        $g->forceFill(['created_at' => '2027-01-04 09:00:00'])->save();

        $this->at('2027-04-12');   // week 14
        [$saved, $errors] = Goals::submit($this->rep, 14, [$g->id => ['status' => 'On Track']]);

        $this->assertSame(1, $saved, implode(' ', $errors));
        $this->assertSame('On Track', NinetyDayGoalCheckin::where('goal_id', $g->id)->where('week_no', 14)->value('status'));
    }

    public function test_past_page_round_figures_match_each_rounds_own_report(): void
    {
        $this->at(self::MONDAY);
        $g = $this->soldGoal($this->rep, 'Closed Co', 2.0, 'KIF', 3.0);
        $g->round->update(['status' => 'closed']);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $view = app(NinetyDayGoalController::class)->past($this->ask($admin, 'GET', '/x'));
        $round = collect($view->getData()['rounds'])->firstWhere('id', $g->round_id);
        $own = Goals::dashboard([$this->rep->id], ['round_id' => $g->round_id])['cards'];

        foreach (['goals', 'potential', 'achieved', 'pct'] as $k) {
            $this->assertEquals($own[$k], $round->stats[$k], $k);
        }
    }

    public function test_an_empty_self_started_round_is_replaced_by_the_program(): void
    {
        // The Monday job starts a round for every salesperson, so "has an active round" alone
        // would skip nearly everyone. An empty one (no program, no customers) gives way.
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $empty = Goals::roundFor($this->rep);

        $result = Goals::createProgram($admin, 'Takeover', Carbon::parse('2027-01-04'), Carbon::parse('2027-04-05'), [$this->rep->id]);

        $this->assertCount(1, $result['enrolled']);
        $this->assertCount(0, $result['skipped']);
        $this->assertNull(NinetyDayRound::find($empty->id), 'the empty self-started round is gone');
        $this->assertSame($result['program']->id, Goals::activeRound($this->rep)->program_id);
    }

    public function test_a_program_nobody_can_join_is_not_left_behind(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $this->goal($this->rep, Goals::roundFor($this->rep));   // busy mid-round

        $before = NinetyDayProgram::count();
        $result = Goals::createProgram($admin, 'Nobody', Carbon::parse('2027-01-04'), Carbon::parse('2027-04-05'), [$this->rep->id]);

        $this->assertNull($result['program']);
        $this->assertSame($before, NinetyDayProgram::count());
    }

    public function test_a_non_monday_program_start_snaps_back_to_that_weeks_monday(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');

        $result = Goals::createProgram($admin, 'Wednesday', Carbon::parse('2027-01-06'), Carbon::parse('2027-04-06'), [$this->rep->id]);

        $this->assertSame('2027-01-04', $result['program']->starts_on->toDateString());
        $this->assertSame('2027-04-04', $result['program']->ends_on->toDateString(), 'the chosen length is kept');
    }

    public function test_two_programs_can_run_at_once_with_different_dates(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');

        Goals::createProgram($admin, 'Early Drive', Carbon::parse('2027-01-04'), Carbon::parse('2027-04-05'), [$this->rep->id]);
        Goals::createProgram($admin, 'Later Drive', Carbon::parse('2027-02-01'), Carbon::parse('2027-05-02'), [$this->rep2->id]);

        $r1 = NinetyDayRound::where('user_id', $this->rep->id)->first();
        $r2 = NinetyDayRound::where('user_id', $this->rep2->id)->first();
        $this->assertNotSame($r1->program_id, $r2->program_id);
        $this->assertSame('2027-01-04', $r1->starts_on->toDateString());
        $this->assertSame('2027-02-01', $r2->starts_on->toDateString());
    }

    public function test_only_admin_can_create_a_program(): void
    {
        $this->at(self::MONDAY);

        $res = app(NinetyDayGoalController::class)->programStore($this->ask($this->rep, 'POST', '/x', [
            'name' => 'Nope', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'user_ids' => [$this->rep2->id],
        ]));

        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame(0, NinetyDayProgram::where('name', 'Nope')->count());
    }

    public function test_the_program_controller_notifies_every_enrolled_person_and_nobody_skipped(): void
    {
        Notification::fake();
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $this->goal($this->rep2, Goals::roundFor($this->rep2));   // already mid-round (has a customer), will be skipped

        $res = app(NinetyDayGoalController::class)->programStore($this->ask($admin, 'POST', '/x', [
            'name' => 'Notify Test', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'user_ids' => [$this->rep->id, $this->rep2->id],
        ]));
        app()->terminate();

        $body = $this->body($res);
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['enrolled']);
        $this->assertSame(1, $body['skipped']);
        Notification::assertSentTo($this->rep, ProgramInvite::class);
        Notification::assertNotSentTo($this->rep2, ProgramInvite::class);
    }

    public function test_the_invite_is_in_app_only_no_mail_channel_at_all(): void
    {
        // No email, no queue — Goals::createProgram() already gives everyone enrolled an "add
        // your target customers" task in the existing Task Management module, which is the
        // real notification. Mailing from this app's local Gmail account is neither wanted
        // nor needed on top of that.
        $person = $this->person($this->sales, null, ['email' => 'a.real.looking@company.com']);
        $program = \App\Models\NinetyDayProgram::create(['tenant_id' => 1, 'name' => 'X', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'created_by_id' => $this->person(null, 'admin')->id]);
        $round = NinetyDayRound::create(['tenant_id' => 1, 'user_id' => $person->id, 'program_id' => $program->id, 'round_no' => 1, 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'status' => 'active']);

        $this->assertSame(['database'], (new ProgramInvite($program, $round))->via($person));
    }

    public function test_creating_a_program_still_gives_everyone_enrolled_their_add_customers_task(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');

        $res = app(NinetyDayGoalController::class)->programStore($this->ask($admin, 'POST', '/x', [
            'name' => 'Task Notify Test', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'user_ids' => [$this->rep->id],
        ]));
        app()->terminate();

        $this->assertTrue($this->body($res)['success']);
        $this->assertTrue(Task::where('user_id', $this->rep->id)->where('due_date', '2027-01-07')->exists());
    }

    public function test_the_program_routes_are_login_only(): void
    {
        foreach (['programs.create', 'programs.store'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))), $name);
        }
    }

    // ── Admin View ──────────────────────────────────────────────────────

    public function test_admin_view_is_admin_only_a_salesperson_is_redirected_away(): void
    {
        // The old Admin View is retired: every visit lands on the Program Dashboard, which
        // itself only serves admin/HR and team managers.
        $c = app(NinetyDayGoalController::class);
        $admin = $this->person(null, 'admin');

        $res = $c->adminView($this->ask($admin, 'GET', '/performance/ninety-day-goals/admin-view', ['program_id' => 5]));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $res);
        $this->assertStringContainsString('/program-dashboard?program_id=5', $res->getTargetUrl(), 'filters are carried over');

        Auth::setUser($this->rep);
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $c->programDashboard($this->ask($this->rep, 'GET', '/x')));
    }

    public function test_the_admin_view_tab_only_appears_for_admin_not_a_plain_salesperson(): void
    {
        // No page links to the retired pages any more; the Dashboard tab points at the current one.
        $c = app(NinetyDayGoalController::class);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $this->assertStringEndsWith('/ninety-day-goals/program-dashboard', $c->index($this->ask($admin, 'GET', '/performance/ninety-day-goals'))->getTargetUrl());
        $adminHtml = $c->comments($this->ask($admin, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringNotContainsString('/ninety-day-goals/admin-view"', $adminHtml);
        $this->assertStringNotContainsString('/ninety-day-goals/dashboard"', $adminHtml);
        $this->assertStringContainsString('/ninety-day-goals/program-dashboard', $adminHtml);
        $this->assertStringContainsString('/ninety-day-goals/programs"', $adminHtml, 'the Programs list is in the navigation');

        Auth::setUser($this->rep);
        $this->assertStringEndsWith('/ninety-day-goals/my-program', $c->index($this->ask($this->rep, 'GET', '/performance/ninety-day-goals'))->getTargetUrl());
        $repHtml = $c->comments($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringNotContainsString('/ninety-day-goals/admin-view"', $repHtml);
        $this->assertStringContainsString('/ninety-day-goals/my-dashboard', $repHtml);
    }

    public function test_admin_view_shows_every_program_and_the_seven_sections(): void
    {
        $this->at(self::MONDAY);
        $program = \App\Models\NinetyDayProgram::create(['tenant_id' => 1, 'name' => 'Export Drive', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'created_by_id' => $this->person(null, 'admin')->id]);
        $goal = $this->soldGoal($this->rep, 'Risk Co', 2.0, 'KIF', 1.0);
        $goal->round->update(['program_id' => $program->id]);
        $admin = $this->person(null, 'admin');

        Auth::setUser($admin);
        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();

        $this->assertStringContainsString('Risk Co', $html);
        $this->assertStringContainsString('Export Drive', $html);
        foreach (['Program Overview', 'Salesperson Performance', 'Customer Tracking', 'Weekly Submissions', 'Orders &amp; Results', 'At Risk', 'Reports'] as $section) {
            $this->assertStringContainsString($section, $html, $section);
        }
    }

    public function test_admin_view_export_streams_a_csv_of_every_filtered_row_not_just_one_page(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'Export Me Ltd', 2.0, 'KIF', 1.0);
        $admin = $this->person(null, 'admin');

        $res = app(NinetyDayGoalController::class)->adminViewExport($this->ask($admin, 'GET', '/performance/ninety-day-goals/admin-view/export'));
        $body = '';
        ob_start();
        $res->sendContent();
        $body = ob_get_clean();

        $this->assertStringContainsString('Export Me Ltd', $body);
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
    }

    public function test_admin_view_export_is_admin_only(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NinetyDayGoalController::class)->adminViewExport($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/admin-view/export'));
    }

    public function test_the_admin_view_routes_are_login_only(): void
    {
        foreach (['admin-view', 'admin-view.export'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))), $name);
        }
    }

    // ── the sidebar's "90-Day Goals ▸ Dashboard" page (left entirely separate from Admin View) ──

    public function test_program_dashboard_is_admin_only_a_salesperson_is_redirected_away(): void
    {
        $c = app(NinetyDayGoalController::class);

        $blocked = $c->programDashboard($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/program-dashboard'));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $blocked);

        $admin = $this->person(null, 'admin');
        $ok = $c->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'));
        $this->assertNotInstanceOf(\Illuminate\Http\RedirectResponse::class, $ok);
        $this->assertStringContainsString('Program Wise Performance', $ok->render());
    }

    public function test_program_dashboard_defaults_to_every_program_past_and_live_not_just_current(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $goal = $this->goal($this->rep, Goals::roundFor($this->rep), 'Dbg ' . uniqid());
        // Close the round — adminView() (current-only default) would drop it; the sidebar
        // Dashboard's own 'all' default must still show it. Filtered straight to this one
        // customer, so a real dev database's own accumulated rows can never push it off the
        // first page of an otherwise-unfiltered, unsorted table.
        $goal->round->update(['status' => 'closed']);

        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard', ['customer' => $goal->client_name]))->render();

        $this->assertStringContainsString($goal->client_name, $html);
    }

    public function test_program_dashboard_shows_real_trend_counts_not_fabricated_ones(): void
    {
        $admin = $this->person(null, 'admin');
        $before = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();
        preg_match('/Active Programs.*?ndg-kpi-v">(\d+)</s', $before, $m);
        $countBefore = (int) ($m[1] ?? 0);

        Goals::createProgram($admin, 'Fresh Program', Carbon::today(), Carbon::today()->addDays(90), [$this->rep->id]);

        $after = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();
        preg_match('/Active Programs.*?ndg-kpi-v">(\d+)</s', $after, $m);
        $countAfter = (int) ($m[1] ?? 0);

        // A real dev database may already have programs from before this test — the trend must
        // still move by exactly the one program this test just created, not a made-up number.
        $this->assertSame($countBefore + 1, $countAfter);
        $this->assertMatchesRegularExpression('/↑\d+ new/', $after, "the KPI card's own trend line must be a real count");
    }

    public function test_program_dashboard_route_is_login_only_and_admin_view_is_left_untouched(): void
    {
        $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.program-dashboard');
        $this->assertNotNull($route);
        $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))));

        $admin = $this->person(null, 'admin');
        Auth::setUser($admin);
        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/x'))->render();
        $this->assertStringContainsString('Order Status', $html, 'the Order Status split moved here from the retired pages');
    }

    public function test_a_manager_gets_the_dashboard_for_their_team_only_without_admin_actions(): void
    {
        $this->at(self::MONDAY);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();
        $this->goal($this->rep, Goals::roundFor($this->rep), 'My Team Co');
        $this->goal($this->rep2, Goals::roundFor($this->rep2), 'Other Team Co');

        Auth::setUser($manager);
        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($manager, 'GET', '/x'))->render();

        $this->assertStringContainsString('My Team Co', $html);
        $this->assertStringNotContainsString('Other Team Co', $html, 'only the manager\'s own team');
        $this->assertStringNotContainsString('Create New Program', $html);
        $this->assertStringNotContainsString('admin-view/export', $html, 'no whole-company export for a manager');
    }

    // ── the individual round detail page ("90-Day Goals ▸ My Programs") ──────────────────

    public function test_a_colleague_cannot_open_someone_elses_round_but_their_manager_can(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();

        Auth::setUser($this->rep2);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(NinetyDayGoalController::class)->roundDetail(new Request(), $round->id);
    }

    public function test_a_manager_can_open_their_reports_round(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();

        Auth::setUser($manager);
        $html = app(NinetyDayGoalController::class)->roundDetail(new Request(), $round->id)->render();
        $this->assertStringContainsString('Customer Targets', $html);
    }

    public function test_round_detail_buckets_every_customer_into_exactly_one_kpi_count(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);

        $this->soldGoal($this->rep, 'Ordered Co', 2.0, 'KIF', 5.0);   // achieved > 0
        $dropped = $this->goal($this->rep, $round, 'Dropped Co');
        Goals::submit($this->rep, 0, [$dropped->id => ['status' => 'Dropped']]);
        $atRisk = $this->goal($this->rep, $round, 'AtRisk Co');
        Goals::submit($this->rep, 0, [$atRisk->id => ['status' => 'Off Track', 'comment' => 'stalled']]);
        $inProgress = $this->goal($this->rep, $round, 'Progress Co');
        Goals::submit($this->rep, 0, [$inProgress->id => ['status' => 'On Track']]);

        Auth::setUser($this->rep);
        $html = app(NinetyDayGoalController::class)->roundDetail(new Request(), $round->id)->render();

        $this->assertStringContainsString('Ordered Co', $html);
        $this->assertStringContainsString('Dropped Co', $html);
        $this->assertStringContainsString('AtRisk Co', $html);
        $this->assertStringContainsString('Progress Co', $html);
    }

    public function test_add_customer_is_hidden_once_the_round_is_closed(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $this->goal($this->rep, $round, 'Closed Round Co');
        $round->update(['status' => 'closed']);

        Auth::setUser($this->rep);
        $html = app(NinetyDayGoalController::class)->roundDetail(new Request(), $round->id)->render();

        $this->assertStringNotContainsString('Add Customer', $html, 'a finished round is not where new targets get added');
        $this->assertStringContainsString('Edit', $html, 'editing an existing target is still allowed');
    }

    public function test_my_program_redirects_a_salesperson_to_their_own_active_round(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);

        Auth::setUser($this->rep);
        $res = app(NinetyDayGoalController::class)->myProgram();

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $res);
        $this->assertStringContainsString('/round/' . $round->id, $res->getTargetUrl());
    }

    public function test_expected_order_date_can_be_set_through_the_existing_update_endpoint(): void
    {
        $this->at(self::MONDAY);
        $round = Goals::roundFor($this->rep);
        $g = $this->goal($this->rep, $round);

        $res = app(NinetyDayGoalController::class)->update($this->ask($this->rep, 'PUT', '/x', ['expected_order_date' => '2027-02-14']), $g->id);
        $this->assertTrue($this->body($res)['success']);
        $this->assertSame('2027-02-14', $g->fresh()->expected_order_date->toDateString());
    }

    public function test_the_round_and_my_program_routes_are_login_only(): void
    {
        foreach (['round', 'my-program'] as $name) {
            $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))), $name);
        }
    }

    // ── Program Details (one program, every participant) ───────────────────

    public function test_program_detail_is_admin_only_a_salesperson_is_redirected_away(): void
    {
        $admin = $this->person(null, 'admin');
        $program = NinetyDayProgram::create(['tenant_id' => 1, 'name' => 'Detail Test', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'created_by_id' => $admin->id]);
        $c = app(NinetyDayGoalController::class);

        $blocked = $c->programDetail($this->ask($this->rep, 'GET', '/x'), $program->id);
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $blocked);

        $ok = $c->programDetail($this->ask($admin, 'GET', '/x'), $program->id);
        $this->assertStringContainsString('Program Code', $ok->render());
    }

    public function test_program_detail_shows_every_participant_even_with_no_customers_yet(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $result = Goals::createProgram($admin, 'Empty Participant Co', Carbon::parse(self::MONDAY), Carbon::parse(self::MONDAY)->addDays(90), [$this->rep->id, $this->rep2->id]);
        $round = $result['enrolled'][0]['round'];
        $this->goal($result['enrolled'][0]['user'], $round, 'Only One Customer');
        // rep2 has a round in this program but never added a customer.

        $html = app(NinetyDayGoalController::class)->programDetail($this->ask($admin, 'GET', '/x'), $result['program']->id)->render();

        $this->assertStringContainsString('Only One Customer', $html);
        // Both enrolled people count as participants, even though only one of them has a
        // customer yet — the sub-tab label spells the real count out directly.
        $this->assertStringContainsString('Participants (2)', $html);
        $this->assertStringContainsString('Customers (1)', $html);
    }

    public function test_program_detail_never_shows_a_salesperson_from_outside_the_program(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $result = Goals::createProgram($admin, 'Just Rep Co', Carbon::parse(self::MONDAY), Carbon::parse(self::MONDAY)->addDays(90), [$this->rep->id]);
        // rep2 is a real salesperson in the company but was never invited to this program.

        $html = app(NinetyDayGoalController::class)->programDetail($this->ask($admin, 'GET', '/x'), $result['program']->id)->render();
        // The Manage program panel's "Add people" list exists to offer exactly the people
        // outside the program; everything else on the page must not show them.
        $html = preg_replace('/<div class="modal fade" id="ndgManageModal".*?<\/select>/s', '', $html);

        $this->assertStringNotContainsString(trim($this->rep2->first_name . ' ' . $this->rep2->last_name), $html,
            "Top Performing Salespersons must only ever list this program's own participants");
    }

    public function test_program_detail_shows_a_clear_empty_state_not_a_confusing_zero_table(): void
    {
        $this->at(self::MONDAY);
        $admin = $this->person(null, 'admin');
        $result = Goals::createProgram($admin, 'Nobody Added Anyone Co', Carbon::parse(self::MONDAY), Carbon::parse(self::MONDAY)->addDays(90), [$this->rep->id]);

        $html = app(NinetyDayGoalController::class)->programDetail($this->ask($admin, 'GET', '/x'), $result['program']->id)->render();

        $this->assertStringContainsString('No customers added yet', $html);
        $this->assertStringContainsString('No customers have been added to this program yet', $html);
    }

    public function test_program_dashboards_view_button_links_to_the_real_program_detail_page(): void
    {
        $admin = $this->person(null, 'admin');
        $program = NinetyDayProgram::create(['tenant_id' => 1, 'name' => 'Linked Program', 'starts_on' => '2027-01-04', 'ends_on' => '2027-04-05', 'created_by_id' => $admin->id]);

        $html = app(NinetyDayGoalController::class)->programDashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/program-dashboard'))->render();

        $this->assertStringContainsString('Linked Program', $html);
        $this->assertStringContainsString(route('performance.ninety-day-goals.program', $program->id), $html);
    }

    public function test_the_program_detail_route_is_login_only(): void
    {
        $route = app('router')->getRoutes()->getByName('performance.ninety-day-goals.program');
        $this->assertNotNull($route);
        $this->assertSame([], array_values(array_filter($route->gatherMiddleware(), fn ($m) => is_string($m) && str_starts_with($m, 'role'))));
    }
}
