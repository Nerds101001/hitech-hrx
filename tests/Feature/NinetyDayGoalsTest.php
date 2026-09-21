<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\NinetyDayGoalController;
use App\Models\Department;
use App\Models\NinetyDayGoal;
use App\Models\NinetyDayGoalCheckin;
use App\Models\NinetyDayRound;
use App\Models\Task;
use App\Models\User;
use App\Services\KpiAutoSourceService;
use App\Services\NinetyDayGoalService as Goals;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

    // ── the weekly status ────────────────────────────────────────────────

    public function test_the_status_choices_are_the_sheets_seven(): void
    {
        $this->assertSame(['On Track', 'Off Track', 'Trial Order', 'Regular Order', 'No Report', 'Dropped', 'Transferred'], Goals::STATUSES);
        $this->assertSame(Goals::STATUSES, array_keys(Goals::STATUS_COLORS));
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

        $html = app(NinetyDayGoalController::class)->index($this->ask($this->rep, 'GET', '/performance/ninety-day-goals'))->render();

        $this->assertStringContainsString('Mine Ltd', $html);
        $this->assertStringNotContainsString('Theirs Ltd', $html);
        $this->assertStringContainsString('Add customers', $html);
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

        $html = app(NinetyDayGoalController::class)->index($this->ask($admin, 'GET', '/performance/ninety-day-goals', ['user_id' => $this->rep->id]))->render();
        $this->assertStringContainsString('Alpha Ltd', $html);
        $this->assertStringContainsString('Regular Order', $html);
        $this->assertStringContainsString('Second PO in', $html, 'the comment is on the chip');
        $this->assertStringNotContainsString('Beta Ltd', $html, 'the salesperson filter works');

        $all = app(NinetyDayGoalController::class)->index($this->ask($admin, 'GET', '/performance/ninety-day-goals'))->render();
        $this->assertStringContainsString('Alpha Ltd', $all);
        $this->assertStringContainsString('Beta Ltd', $all);
        $this->assertStringContainsString('Statuses submitted, week by week', $all);
    }

    public function test_a_manager_sees_only_their_team(): void
    {
        $this->at(self::MONDAY);
        $manager = $this->person($this->sales, 'manager');
        $this->rep->forceFill(['reporting_to_id' => $manager->id])->save();
        $this->goal($this->rep, Goals::roundFor($this->rep), 'Team Ltd');
        $this->goal($this->rep2, Goals::roundFor($this->rep2), 'Stranger Ltd');

        $html = app(NinetyDayGoalController::class)->index($this->ask($manager, 'GET', '/performance/ninety-day-goals', ['view' => 'overview']))->render();

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

    public function test_achievement_is_what_was_sold_in_the_round_against_three_months_of_potential(): void
    {
        $this->at(self::MONDAY);
        $this->soldGoal($this->rep, 'Half Ltd', 2.0, 'KIF', 3.0);       // target 6, sold 3
        $this->soldGoal($this->rep, 'Over Ltd', 1.0, 'Rust-X', 6.0);    // target 3, sold 6

        $d = Goals::dashboard([$this->rep->id, $this->rep2->id]);

        $this->assertSame(2, $d['cards']['goals']);
        $this->assertEquals(3.0, $d['cards']['potential']);
        $this->assertEquals(9.0, $d['cards']['target']);
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
        $this->soldGoal($this->rep, 'A Ltd', 10.0, 'KIF', 3.0);     // 3 of 30 = 10%
        $this->soldGoal($this->rep2, 'B Ltd', 1.0, 'KIF', 3.0);     // 3 of 3 = 100%

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

        $adminDash = $c->dashboard($this->ask($admin, 'GET', '/performance/ninety-day-goals/dashboard'))->render();
        $this->assertStringContainsString('Top 10 brands', $adminDash);
        $this->assertStringContainsString('Achievement by salesperson', $adminDash);
        $this->assertStringContainsString('Theirs Ltd', $adminDash);

        $repDash = $c->dashboard($this->ask($this->rep, 'GET', '/performance/ninety-day-goals/dashboard'))->render();
        $this->assertStringContainsString('Mine Ltd', $repDash);
        $this->assertStringNotContainsString('Theirs Ltd', $repDash);
        $this->assertStringNotContainsString('Achievement by salesperson', $repDash, 'a salesperson has no league table of colleagues');

        $adminComments = $c->comments($this->ask($admin, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringContainsString('Sample approved by purchase', $adminComments);

        $otherComments = $c->comments($this->ask($this->rep2, 'GET', '/performance/ninety-day-goals/comments'))->render();
        $this->assertStringNotContainsString('Sample approved by purchase', $otherComments, "nobody reads a colleague's comments");

        $away = $c->dashboard($this->ask($this->outsider, 'GET', '/performance/ninety-day-goals/dashboard'));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $away);
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
}
