<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\User;
use App\Services\KpiAutoSourceService as Src;
use App\Support\SalesRuleBook;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The data behind the Sales rule book. Each source is checked against a book of
 * customers where the right answer is known by construction. Windows are fixed
 * in the past (FY 2024-25 against FY 2023-24) so the results never depend on
 * today's date; the two sources that read "now" use the current year instead.
 */
class SalesRuleBookSourcesTest extends TestCase
{
    use DatabaseTransactions;

    private User $rep;
    private Department $dept;
    private Carbon $start;   // 1 Apr 2024
    private Carbon $end;     // 31 Mar 2025

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->dept = Department::create(['name' => 'ZZ Sales ' . uniqid(), 'code' => 'ZZS' . random_int(1000, 9999)]);
        $this->rep = $this->person(['date_of_joining' => '2019-01-01']);
        $this->start = Carbon::create(2024, 4, 1)->startOfDay();
        $this->end   = Carbon::create(2025, 3, 31)->endOfDay();
    }

    private function person(array $extra = []): User
    {
        static $n = 0;
        $n++;
        return User::create(array_merge([
            'first_name' => 'QA', 'last_name' => "Rep {$n}", 'email' => 'qarb' . uniqid() . '@example.test',
            'code' => 'QARB' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => 'active', 'department_id' => $this->dept->id,
        ], $extra));
    }

    private function customer(float $potential = 0, ?User $owner = null): int
    {
        return DB::table('sales_pipelines')->insertGetId([
            'party_name' => 'ZZ Customer ' . uniqid(), 'salesperson_id' => ($owner ?? $this->rep)->id,
            'total_business_potential' => $potential, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sale(int $customer, string $month, float $lakh, float $forecast = 0): void
    {
        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $customer, 'month_year' => $month, 'sale_amount' => $lakh,
            'forecast_amount' => $forecast, 'pending_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function currentFiscalStart(): Carbon
    {
        $t = Carbon::today();
        return $t->month >= 4 ? Carbon::create($t->year, 4, 1) : Carbon::create($t->year - 1, 4, 1);
    }

    // ── pipeline ÷ target ────────────────────────────────────────────────

    private function bingo(string $month, float $lakh): void
    {
        DB::table('salesperson_targets')->insert(['salesperson_id' => $this->rep->id, 'month_year' => $month,
            'bingo_target' => $lakh, 'kingo_target' => 0, 'super_kingo_target' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function monthOf(string $ym): array
    {
        $m = Carbon::createFromFormat('!Y-m', $ym);

        return [$m->copy()->startOfMonth(), $m->copy()->endOfMonth()];
    }

    public function test_pipeline_strength_is_the_months_forecast_over_the_months_bingo_target(): void
    {
        // Potential is deliberately enormous: it must no longer count.
        $this->sale($this->customer(9000), '2026-08', 0, 60);
        $this->sale($this->customer(9000), '2026-08', 0, 30);
        $this->bingo('2026-08', 100);

        [$s, $e] = $this->monthOf('2026-08');
        $this->assertSame(90.0, Src::pipelineVsTarget($this->rep->id, $s, $e));
    }

    public function test_pipeline_strength_differs_month_to_month(): void
    {
        $c = $this->customer(0);
        $this->sale($c, '2026-08', 0, 80);
        $this->sale($c, '2026-09', 0, 50);
        $this->bingo('2026-08', 100);
        $this->bingo('2026-09', 100);

        [$s, $e] = $this->monthOf('2026-08');
        $this->assertSame(80.0, Src::pipelineVsTarget($this->rep->id, $s, $e));
        [$s, $e] = $this->monthOf('2026-09');
        $this->assertSame(50.0, Src::pipelineVsTarget($this->rep->id, $s, $e));
    }

    public function test_pipeline_strength_is_null_without_a_target_that_month(): void
    {
        $this->sale($this->customer(60), '2026-08', 0, 60);
        $this->bingo('2026-09', 100);   // a target, but for another month

        [$s, $e] = $this->monthOf('2026-08');
        $this->assertNull(Src::pipelineVsTarget($this->rep->id, $s, $e));
    }

    // ── growth ───────────────────────────────────────────────────────────

    public function test_growth_compares_the_same_stretch_of_last_year(): void
    {
        $c = $this->customer();
        $this->sale($c, '2024-05', 30);   // this year
        $this->sale($c, '2023-05', 20);   // the same month last year
        $this->sale($c, '2023-12', 500);  // last year, but AFTER the stretch measured so far

        $this->assertSame(50.0, Src::yoyGrowth($this->rep->id, $this->start, Carbon::create(2024, 6, 30)));
    }

    public function test_growth_from_nothing_is_no_figure_not_zero(): void
    {
        $this->sale($this->customer(), '2024-05', 30);

        $this->assertNull(Src::yoyGrowth($this->rep->id, $this->start, $this->end));
    }

    // ── attrition ────────────────────────────────────────────────────────

    public function test_attrition_is_last_years_customers_with_no_order_this_year(): void
    {
        [$a, $b, $c, $d] = [$this->customer(), $this->customer(), $this->customer(), $this->customer()];
        foreach ([$a, $b, $c, $d] as $id) {
            $this->sale($id, '2023-07', 5);           // all ordered last year
        }
        $this->sale($a, '2024-08', 5);                // two of them came back
        $this->sale($b, '2024-09', 5);
        $this->sale($this->customer(), '2024-08', 9); // a brand-new customer must not dilute the base

        $this->assertSame(50.0, Src::customerAttrition($this->rep->id, $this->start, $this->end));
    }

    public function test_attrition_is_null_when_there_was_nobody_to_lose(): void
    {
        $this->sale($this->customer(), '2024-08', 5);

        $this->assertNull(Src::customerAttrition($this->rep->id, $this->start, $this->end));
    }

    // ── new customer revenue ─────────────────────────────────────────────

    public function test_new_customer_revenue_is_the_share_from_customers_first_seen_in_the_period(): void
    {
        $old = $this->customer();
        $this->sale($old, '2023-10', 5);      // first order before the period, so last year is on record
        $this->sale($old, '2024-07', 30);
        $new = $this->customer();
        $this->sale($new, '2024-06', 10);     // first ever order inside the period

        $this->assertSame(25.0, Src::newCustomerRevenuePct($this->rep->id, $this->start, $this->end));
    }

    public function test_new_customer_revenue_is_zero_when_nobody_is_new_and_null_with_no_revenue(): void
    {
        $this->assertNull(Src::newCustomerRevenuePct($this->rep->id, $this->start, $this->end));

        $old = $this->customer();
        $this->sale($old, '2023-10', 5);
        $this->sale($old, '2024-07', 30);

        $this->assertSame(0.0, Src::newCustomerRevenuePct($this->rep->id, $this->start, $this->end));
    }

    // ── cost of selling ──────────────────────────────────────────────────

    private function claim(string $status, float $amount, string $on): void
    {
        DB::table('travel_claims')->insert(['user_id' => $this->rep->id, 'claim_month' => '2024-08', 'status' => $status,
            'total_amount' => $amount, 'created_at' => $on, 'updated_at' => $on]);
    }

    public function test_cost_of_selling_converts_lakh_to_rupees_and_counts_only_verified_claims(): void
    {
        $this->sale($this->customer(), '2024-08', 10);                 // 10 lakh = 10,00,000 rupees
        $this->claim('verified', 30000, '2024-08-10');                 // counts
        $this->claim('draft', 500000, '2024-08-11');                   // does not
        $this->claim('verified', 700000, '2025-06-01');                // outside the year

        $this->assertSame(3.0, Src::costOfSelling($this->rep->id, $this->start, $this->end));
    }

    public function test_someone_under_six_months_in_gets_the_three_point_case_whatever_they_spent(): void
    {
        $new = $this->person(['date_of_joining' => now()->subMonths(2)->toDateString()]);
        DB::table('travel_claims')->insert(['user_id' => $new->id, 'claim_month' => '2024-08', 'status' => 'verified',
            'total_amount' => 900000, 'created_at' => '2024-08-10', 'updated_at' => '2024-08-10']);

        $this->assertSame(3.0, Src::costOfSelling($new->id, $this->start, $this->end));
    }

    public function test_cost_of_selling_is_null_with_no_revenue(): void
    {
        $this->claim('verified', 30000, '2024-08-10');

        $this->assertNull(Src::costOfSelling($this->rep->id, $this->start, $this->end));
    }

    // ── mind speak, goals, visits ────────────────────────────────────────

    public function test_mind_speak_counts_accepted_entries_of_the_category_inside_the_period(): void
    {
        $add = fn (string $cat, string $status, string $on, $deleted = null) => DB::table('mind_speaks')->insert([
            'user_id' => $this->rep->id, 'category' => $cat, 'content' => 'x', 'status' => $status,
            'created_at' => $on, 'updated_at' => $on, 'deleted_at' => $deleted]);
        $add('Idea', 'approved', '2024-06-01');
        $add('Idea', 'acknowledged', '2024-07-01');
        $add('Idea', 'pending', '2024-07-02');                    // not yet accepted
        $add('Idea', 'rejected', '2024-07-03');
        $add('Idea', 'approved', '2024-07-04', '2024-08-01');     // deleted
        $add('Idea', 'approved', '2023-06-01');                   // last year
        $add('Competitor Insights', 'approved', '2024-06-01');    // another category

        $this->assertSame(2.0, Src::mindSpeakAccepted($this->rep->id, $this->start, $this->end, 'Idea'));
        $this->assertSame(1.0, Src::mindSpeakAccepted($this->rep->id, $this->start, $this->end, 'Competitor Insights'));
    }

    public function test_ninety_day_goals_is_the_share_on_track_and_null_with_none(): void
    {
        $this->assertNull(Src::ninetyDayGoals($this->rep->id, $this->start, $this->end));

        $cycle = DB::table('appraisal_cycles')->insertGetId(['name' => 'ZZ FY24-25', 'start_date' => '2024-04-01', 'end_date' => '2025-03-31',
            'created_at' => now(), 'updated_at' => now()]);
        foreach (['Regular Order', 'On Track', 'Slipping'] as $w12) {
            DB::table('ninety_day_goals')->insert(['user_id' => $this->rep->id, 'appraisal_cycle_id' => $cycle, 'client_name' => 'ZZ',
                'product' => 'ZZ', 'goal_type' => 'order', 'target_value' => 1, 'w12' => $w12, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->assertSame(66.67, Src::ninetyDayGoals($this->rep->id, $this->start, $this->end));
    }

    public function test_product_trial_visits_are_counted_under_the_spelling_the_database_stores(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (['product_trial', 'product_trial', 'client_visit'] as $type) {
                DB::table('sales_visits')->insert(['client_id' => 999999, 'salesperson_id' => $this->rep->id, 'cc_user_id' => $this->rep->id,
                    'scheduled_at' => '2024-07-01 10:00:00', 'completed_at' => '2024-07-01 11:00:00', 'status' => 'completed',
                    'visit_type' => $type, 'created_at' => now(), 'updated_at' => now()]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->assertSame(2.0, Src::visitTypeCount($this->rep->id, $this->start->copy(), $this->end->copy(), 'product_trial'));
    }

    // ── installing the rule book ─────────────────────────────────────────

    public function test_installing_reaches_only_the_chosen_designations_and_never_the_heading(): void
    {
        $manager = \App\Models\Designation::create(['name' => 'ZZ Manager ' . uniqid(), 'code' => 'ZZM' . random_int(10000, 99999)]);
        $gm      = \App\Models\Designation::create(['name' => 'ZZ GM ' . uniqid(), 'code' => 'ZZG' . random_int(10000, 99999)]);
        $mine  = $this->person(['designation_id' => $manager->id]);
        $boss  = $this->person(['designation_id' => $gm->id]);

        $templates = SalesRuleBook::install($this->dept->id, [$manager->id]);
        \App\Services\KpiTemplateService::apply($templates['Pipeline Strength']);
        foreach ($templates as $t) {
            \App\Services\KpiTemplateService::apply($t);
        }

        $this->assertCount(15, $templates);
        $this->assertSame(15, KpiAssignment::where('user_id', $mine->id)->count());
        $this->assertSame(0, KpiAssignment::where('user_id', $boss->id)->count(), 'the other designation gets nothing');
        $this->assertSame(0, KpiAssignment::where('user_id', $mine->id)->where('category', 'KRA')->count(), 'a heading is never assigned');

        // Running it again changes nothing.
        SalesRuleBook::install($this->dept->id, [$manager->id]);
        $this->assertSame(16, \App\Models\KpiTemplate::where('department_id', $this->dept->id)->count(), '1 heading + 15 KPIs, no duplicates');
    }

    public function test_the_nightly_run_scores_a_yearly_bingo_kpi_from_the_data(): void
    {
        $manager = \App\Models\Designation::create(['name' => 'ZZ Manager ' . uniqid(), 'code' => 'ZZM' . random_int(10000, 99999)]);
        $me = $this->person(['designation_id' => $manager->id, 'date_of_joining' => '2019-01-01']);
        $t = SalesRuleBook::install($this->dept->id, [$manager->id]);
        foreach ($t as $tpl) {
            \App\Services\KpiTemplateService::apply($tpl);
        }

        $key = $this->currentFiscalStart()->format('Y-m');
        DB::table('salesperson_targets')->insert(['salesperson_id' => $me->id, 'month_year' => $key, 'bingo_target' => 100,
            'kingo_target' => 100, 'super_kingo_target' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $c = $this->customer(0, $me);
        $this->sale($c, $key, 98);   // 98 % of Bingo

        Artisan::call('kpi:auto-score', ['--user' => $me->id]);

        $a = KpiAssignment::where('user_id', $me->id)->where('title', 'Target Achievement (Bingo %)')->firstOrFail();
        $check = \App\Models\KpiCheck::where('kpi_assignment_id', $a->id)->firstOrFail();
        $this->assertSame(98.0, (float) $check->achieved_value);
        $this->assertSame(15.0, (float) $check->score, '97 % and over is 15 points');
    }
    public function test_new_customer_revenue_is_no_figure_when_last_year_is_not_on_record(): void
    {
        // Revenue this year but nothing before: every customer would look new.
        $this->sale($this->customer(), '2024-07', 30);

        $this->assertNull(Src::newCustomerRevenuePct($this->rep->id, $this->start, $this->end));
    }

    public function test_cost_of_selling_is_no_figure_when_no_claim_was_ever_entered(): void
    {
        $this->sale($this->customer(), '2024-08', 10);

        $this->assertNull(Src::costOfSelling($this->rep->id, $this->start, $this->end), 'not a perfect score for missing claims');
    }
}
