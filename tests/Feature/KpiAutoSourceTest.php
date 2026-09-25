<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\KpiAutoSourceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Auto-sourced KPIs put a number on someone's appraisal with no human in the
 * loop, so each formula is checked against a hand-built book of customers
 * where the right answer is known by construction.
 *
 * Two behaviours are deliberate and asserted: percentage sources return a
 * percentage (pair them with target_value = 100), and a source with nothing to
 * measure returns null — scoring somebody zero against a target that was never
 * set would be unfair, so the command skips them instead.
 */
class KpiAutoSourceTest extends TestCase
{
    use DatabaseTransactions;

    private User $rep;
    private const APR = '2026-04';
    private const MAY = '2026-05';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $department = Department::create([
            'name' => 'ZZ QA Dept ' . uniqid(),
            'code' => 'ZZQ' . random_int(1000, 9999),
        ]);

        $this->rep = User::create([
            'first_name' => 'QA', 'last_name' => 'Rep',
            'email'      => 'qa' . uniqid() . '@example.test',
            'code'       => 'QA' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
            'department_id' => $department->id,
        ]);
    }

    /** A customer owned through one of the three ownership columns. */
    private function customer(string $ownership = 'salesperson_id', float $potential = 0): int
    {
        return DB::table('sales_pipelines')->insertGetId([
            'party_name'               => 'ZZ QA Customer ' . uniqid(),
            $ownership                 => $this->rep->id,
            'total_business_potential' => $potential,
            'is_locked'                => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sale(int $customerId, string $month, float $amount, float $forecast = 0): void
    {
        DB::table('sales_pipeline_months')->insert([
            'sales_pipeline_id' => $customerId,
            'month_year'        => $month,
            'sale_amount'       => $amount,
            'forecast_amount'   => $forecast,
            'pending_amount'    => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function period(string $from = self::APR, string $to = self::MAY): array
    {
        return [Carbon::parse($from . '-01')->startOfMonth(), Carbon::parse($to . '-01')->endOfMonth()];
    }

    // ── revenue ──────────────────────────────────────────────────────────

    public function test_revenue_sums_only_months_inside_the_period(): void
    {
        $customer = $this->customer();
        $this->sale($customer, self::APR, 10);
        $this->sale($customer, self::MAY, 5);
        $this->sale($customer, '2026-06', 999);      // outside the window

        [$start, $end] = $this->period();

        $this->assertSame(15.0, KpiAutoSourceService::revenue($this->rep->id, $start, $end));
    }

    /**
     * A New Biz executive owns customers through new_biz_id, and a CCare rep
     * through ccare_id — measuring only salesperson_id would score them zero.
     */
    public function test_revenue_counts_customers_owned_through_any_of_the_three_roles(): void
    {
        $this->sale($this->customer('salesperson_id'), self::APR, 1);
        $this->sale($this->customer('new_biz_id'),     self::APR, 2);
        $this->sale($this->customer('ccare_id'),       self::APR, 4);

        [$start, $end] = $this->period();

        $this->assertSame(7.0, KpiAutoSourceService::revenue($this->rep->id, $start, $end));
    }

    public function test_revenue_ignores_customers_belonging_to_someone_else(): void
    {
        $mine = $this->customer();
        $this->sale($mine, self::APR, 3);

        $theirs = DB::table('sales_pipelines')->insertGetId([
            'party_name' => 'ZZ QA Other ' . uniqid(), 'salesperson_id' => null,
            'is_locked' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->sale($theirs, self::APR, 100);

        [$start, $end] = $this->period();

        $this->assertSame(3.0, KpiAutoSourceService::revenue($this->rep->id, $start, $end));
    }

    public function test_soft_deleted_customers_are_excluded(): void
    {
        $live = $this->customer();
        $this->sale($live, self::APR, 5);
        $removed = $this->customer();
        $this->sale($removed, self::APR, 50);
        DB::table('sales_pipelines')->where('id', $removed)->update(['deleted_at' => now()]);

        [$start, $end] = $this->period();

        $this->assertSame(5.0, KpiAutoSourceService::revenue($this->rep->id, $start, $end));
    }

    // ── customer health ──────────────────────────────────────────────────

    public function test_customer_health_is_the_share_of_the_book_that_ordered(): void
    {
        $ordered = $this->customer();
        $this->sale($ordered, self::APR, 5);
        $this->customer();      // silent
        $this->customer();      // silent
        $this->customer();      // silent

        [$start, $end] = $this->period();

        // 1 of 4 customers ordered.
        $this->assertSame(25.0, KpiAutoSourceService::customerHealth($this->rep->id, $start, $end));
    }

    public function test_a_zero_value_order_does_not_count_as_having_ordered(): void
    {
        $customer = $this->customer();
        $this->sale($customer, self::APR, 0);

        [$start, $end] = $this->period();

        $this->assertSame(0.0, KpiAutoSourceService::customerHealth($this->rep->id, $start, $end));
    }

    public function test_customer_health_is_null_when_the_rep_has_no_customers(): void
    {
        [$start, $end] = $this->period();

        $this->assertNull(KpiAutoSourceService::customerHealth($this->rep->id, $start, $end));
    }

    // ── target achievement ───────────────────────────────────────────────

    public function test_target_achievement_is_revenue_as_a_percentage_of_target(): void
    {
        $customer = $this->customer();
        $this->sale($customer, self::APR, 45);
        DB::table('salesperson_targets')->insert([
            'salesperson_id' => $this->rep->id, 'month_year' => self::APR,
            'kingo_target'   => 90, 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$start, $end] = $this->period(self::APR, self::APR);

        $this->assertSame(50.0, KpiAutoSourceService::targetAchievement($this->rep->id, $start, $end));
    }

    /** No target set is not the same as missing the target. */
    public function test_target_achievement_is_null_when_no_target_was_set(): void
    {
        $customer = $this->customer();
        $this->sale($customer, self::APR, 45);

        [$start, $end] = $this->period();

        $this->assertNull(KpiAutoSourceService::targetAchievement($this->rep->id, $start, $end));
    }

    // ── pipeline ─────────────────────────────────────────────────────────

    public function test_total_pipeline_is_the_sale_against_the_forecast(): void
    {
        // A huge potential must play no part: the pipeline is the forecast.
        $customer = $this->customer('salesperson_id', 5000);
        $this->sale($customer, self::APR, 30, 40);
        $this->sale($this->customer('new_biz_id', 0), self::APR, 15, 20);

        [$start, $end] = $this->period(self::APR, self::APR);

        // 45 sold of 60 forecast.
        $this->assertSame(75.0, KpiAutoSourceService::totalPipeline($this->rep->id, $start, $end));
    }

    public function test_total_pipeline_reads_only_the_months_asked_for(): void
    {
        $customer = $this->customer('salesperson_id', 0);
        $this->sale($customer, self::APR, 50, 50);
        $this->sale($customer, self::MAY, 10, 40);

        [$start, $end] = $this->period(self::MAY, self::MAY);

        $this->assertSame(25.0, KpiAutoSourceService::totalPipeline($this->rep->id, $start, $end));
    }

    public function test_total_pipeline_is_null_when_nothing_was_forecast(): void
    {
        $customer = $this->customer('salesperson_id', 200);
        $this->sale($customer, self::APR, 50, 0);

        [$start, $end] = $this->period();

        $this->assertNull(KpiAutoSourceService::totalPipeline($this->rep->id, $start, $end));
    }

    public function test_the_headline_pipeline_card_is_the_months_forecast(): void
    {
        $this->sale($this->customer('salesperson_id', 900), self::APR, 5, 42.18);
        $this->sale($this->customer('salesperson_id', 0), self::MAY, 5, 99);

        [$start, $end] = $this->period(self::APR, self::APR);

        $this->assertSame(42.18, KpiAutoSourceService::totalPipelineValue($this->rep->id, $start, $end));
    }

    // ── new customers ────────────────────────────────────────────────────

    /** "First order in Tally" — the customer must be genuinely new. */
    public function test_customers_added_counts_only_first_ever_orders(): void
    {
        $brandNew = $this->customer();
        $this->sale($brandNew, self::MAY, 5);

        $existing = $this->customer();
        $this->sale($existing, '2026-01', 5);     // ordered long before the window
        $this->sale($existing, self::MAY, 5);     // ordering again is not "added"

        [$start, $end] = $this->period();

        $this->assertSame(1.0, KpiAutoSourceService::customersAdded($this->rep->id, $start, $end));
    }

    // ── retention ────────────────────────────────────────────────────────

    public function test_retention_measures_older_customers_still_ordering(): void
    {
        $loyal = $this->customer();
        $this->sale($loyal, '2025-06', 5);        // first seen well over 6 months ago
        $this->sale($loyal, self::APR, 5);        // still ordering

        $lapsed = $this->customer();
        $this->sale($lapsed, '2025-06', 5);       // same vintage, went quiet

        [$start, $end] = $this->period();

        // 1 of 2 eligible customers still ordering.
        $this->assertSame(50.0, KpiAutoSourceService::customersRetained($this->rep->id, $start, $end));
    }

    public function test_retention_is_null_when_nobody_is_old_enough_to_count(): void
    {
        $recent = $this->customer();
        $this->sale($recent, self::APR, 5);

        [$start, $end] = $this->period();

        $this->assertNull(KpiAutoSourceService::customersRetained($this->rep->id, $start, $end));
    }
}
