<?php

namespace Tests\Feature;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineHandover;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The rule every report follows once a leaver's customers are handed over:
 * the holder of a role in month m is the PREVIOUS holder of the earliest handover
 * that starts after m, otherwise the person in the column now.
 *
 * Two implementations of that rule exist -- creditsMonth() in PHP and
 * whereMonthCredited() in SQL -- and they must always agree, and must give
 * exactly today's answer whenever no handover is recorded.
 */
class SalesLeaverHandoverCreditTest extends TestCase
{
    use DatabaseTransactions;

    private User $leaver;
    private User $next;
    private User $third;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        // Handovers that already exist in this database (real ones made through the page) are set aside for
        // the length of the test, which runs in a transaction that is rolled back.
        DB::table('sales_pipeline_handovers')->update(['reverted_at' => now()]);
        SalesPipelineHandover::flush();
        $this->leaver = $this->person();
        $this->next   = $this->person();
        $this->third  = $this->person();
        $this->other  = $this->person();
    }

    protected function tearDown(): void
    {
        SalesPipelineHandover::flush();
        parent::tearDown();
    }

    private function person(): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Handover', 'email' => 'ho' . uniqid() . '@example.test',
            'code' => 'HO' . random_int(100000, 999999), 'password' => Hash::make('x-not-used'), 'status' => 'active',
        ])->fresh();
    }

    /** A customer with ₹1 in each of the months given. */
    private function customer(array $cols, array $months = ['2031-01', '2031-02', '2031-03', '2031-04']): SalesPipeline
    {
        $p = SalesPipeline::forceCreate($cols + ['party_name' => 'QA HO ' . uniqid(), 'is_locked' => 1, 'status_stage' => 'Order']);
        foreach ($months as $m) {
            SalesPipelineMonth::forceCreate(['sales_pipeline_id' => $p->id, 'month_year' => $m, 'sale_amount' => 1]);
        }

        return $p;
    }

    private function handover(SalesPipeline $p, string $role, ?User $from, ?User $to, string $month): void
    {
        DB::table('sales_pipeline_handovers')->insert([
            'batch_id' => 0, 'sales_pipeline_id' => $p->id, 'role' => $role,
            'from_user_id' => $from->id, 'to_user_id' => $to?->id, 'effective_month' => $month,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        SalesPipelineHandover::flush();
    }

    /** What the SQL rule credits a person on one customer, month by month. */
    private function sqlMonths(SalesPipeline $p, User $u): array
    {
        $q = DB::table('sales_pipeline_months')->where('sales_pipeline_id', $p->id);
        SalesPipeline::whereMonthCredited($q, $u->id);

        return $q->orderBy('month_year')->pluck('month_year')->all();
    }

    private function phpMonths(SalesPipeline $p, User $u): array
    {
        $p = SalesPipeline::with('months')->find($p->id);

        return $p->months->sortBy('month_year')->filter(fn ($m) => $p->creditsMonth($u->id, $m->month_year))
            ->pluck('month_year')->values()->all();
    }

    private function both(SalesPipeline $p, User $u): array
    {
        $php = $this->phpMonths($p, $u);
        $sql = $this->sqlMonths($p, $u);
        $this->assertSame($php, $sql, "PHP and SQL disagree for user {$u->id} on pipeline {$p->id}");

        return $php;
    }

    public function test_a_customer_with_no_handover_is_credited_exactly_as_before(): void
    {
        $p = $this->customer(['salesperson_id' => $this->leaver->id]);

        $this->assertFalse(SalesPipelineHandover::hasAny() && false);
        $this->assertSame(['2031-01', '2031-02', '2031-03', '2031-04'], $this->both($p, $this->leaver));
    }

    public function test_the_leaver_keeps_the_past_and_the_replacement_gets_the_future(): void
    {
        $p = $this->customer(['salesperson_id' => $this->next->id]);   // the column already holds the replacement
        $this->handover($p, 'salesperson_id', $this->leaver, $this->next, '2031-03');

        $this->assertSame(['2031-01', '2031-02'], $this->both($p, $this->leaver));
        $this->assertSame(['2031-03', '2031-04'], $this->both($p, $this->next));
        $this->assertSame([], $this->both($p, $this->other), 'somebody unrelated is credited nothing');
    }

    public function test_a_chain_of_handovers_credits_each_person_their_own_months(): void
    {
        // A -> B from March, B -> C from May. The column holds C.
        $p = $this->customer(['ccare_id' => $this->third->id], ['2031-01', '2031-02', '2031-03', '2031-04', '2031-05', '2031-06']);
        $this->handover($p, 'ccare_id', $this->leaver, $this->next, '2031-03');
        $this->handover($p, 'ccare_id', $this->next, $this->third, '2031-05');

        $this->assertSame(['2031-01', '2031-02'], $this->both($p, $this->leaver));
        $this->assertSame(['2031-03', '2031-04'], $this->both($p, $this->next));
        $this->assertSame(['2031-05', '2031-06'], $this->both($p, $this->third));
    }

    public function test_other_people_on_the_customer_keep_every_month(): void
    {
        $p = $this->customer(['salesperson_id' => $this->other->id, 'new_biz_id' => $this->next->id]);
        $this->handover($p, 'new_biz_id', $this->leaver, $this->next, '2031-03');

        $this->assertSame(['2031-01', '2031-02', '2031-03', '2031-04'], $this->both($p, $this->other));
        $this->assertSame(['2031-01', '2031-02'], $this->both($p, $this->leaver));
        $this->assertSame(['2031-03', '2031-04'], $this->both($p, $this->next));
    }

    public function test_an_additional_rep_tag_ends_at_the_month(): void
    {
        $p = $this->customer(['salesperson_id' => $this->other->id]);
        DB::table('sales_pipeline_extra_reps')->insert(['sales_pipeline_id' => $p->id, 'user_id' => $this->leaver->id, 'type' => 'other', 'created_at' => now(), 'updated_at' => now()]);
        $this->handover($p, 'extra', $this->leaver, null, '2031-03');

        $this->assertSame(['2031-01', '2031-02'], $this->both($p, $this->leaver));
        $this->assertSame(['2031-01', '2031-02', '2031-03', '2031-04'], $this->both($p, $this->other));
    }

    public function test_a_handed_over_ccare_keeps_the_new_biz_to_ccare_split(): void
    {
        // Moved to CCare from March (ex-New Biz person kept January-February),
        // and the CCare person then changed from April.
        $p = $this->customer(['ccare_id' => $this->next->id, 'ccare_from' => '2031-03', 'former_new_biz_id' => $this->other->id]);
        $this->handover($p, 'ccare_id', $this->leaver, $this->next, '2031-04');

        $this->assertSame(['2031-01', '2031-02'], $this->both($p, $this->other));
        $this->assertSame(['2031-03'], $this->both($p, $this->leaver));
        $this->assertSame(['2031-04'], $this->both($p, $this->next));
    }

    public function test_an_undone_handover_changes_nothing(): void
    {
        $p = $this->customer(['salesperson_id' => $this->next->id]);
        $this->handover($p, 'salesperson_id', $this->leaver, $this->next, '2031-03');
        DB::table('sales_pipeline_handovers')->update(['reverted_at' => now()]);
        SalesPipelineHandover::flush();

        $this->assertFalse(SalesPipelineHandover::hasAny());
        $this->assertSame(['2031-01', '2031-02', '2031-03', '2031-04'], $this->both($p, $this->next));
    }

    /**
     * Across real customers: put a handover on a sample of them and require the
     * PHP and the SQL rule to agree for every person on the row and every month,
     * and the two halves of each handover to add up to the customer's whole history.
     */
    public function test_php_and_sql_agree_on_real_customers_and_nothing_is_lost(): void
    {
        $rows = DB::table('sales_pipelines')->whereNull('deleted_at')->whereNotNull('salesperson_id')
            ->whereExists(fn ($q) => $q->selectRaw(1)->from('sales_pipeline_months as mm')->whereColumn('mm.sales_pipeline_id', 'sales_pipelines.id')->where('mm.sale_amount', '>', 0))
            ->inRandomOrder()->limit(60)->get();
        $this->assertNotEmpty($rows, 'needs real customers in the local database');

        foreach ($rows as $row) {
            $months = DB::table('sales_pipeline_months')->where('sales_pipeline_id', $row->id)->orderBy('month_year')->pluck('month_year')->all();
            $cut = $months[intdiv(count($months), 2)];
            $old = $row->salesperson_id;                      // becomes the leaver
            $new = $this->next->id;
            DB::table('sales_pipelines')->where('id', $row->id)->update(['salesperson_id' => $new]);
            DB::table('sales_pipeline_handovers')->insert([
                'batch_id' => 0, 'sales_pipeline_id' => $row->id, 'role' => 'salesperson_id',
                'from_user_id' => $old, 'to_user_id' => $new, 'effective_month' => $cut, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        SalesPipelineHandover::flush();

        foreach ($rows as $row) {
            $p = SalesPipeline::find($row->id);
            $whole = DB::table('sales_pipeline_months')->where('sales_pipeline_id', $p->id)->orderBy('month_year')->pluck('month_year')->all();
            $people = array_filter(array_unique([$row->salesperson_id, $row->ccare_id, $row->new_biz_id, $row->new_biz_2_id, $row->former_new_biz_id, $this->next->id]));
            foreach ($people as $uid) {
                $this->both($p, User::find($uid) ?? $this->other);
            }
            $before = $this->both($p, User::find($row->salesperson_id) ?? $this->other);
            $after  = $this->both($p, $this->next);
            // Somebody who also holds another role (or a tag) on the row stays credited
            // through that role, so only a sole-role holder splits cleanly.
            $soleRole = collect([$row->ccare_id, $row->new_biz_id, $row->new_biz_2_id, $row->former_new_biz_id])->doesntContain($row->salesperson_id)
                && !DB::table('sales_pipeline_extra_reps')->where('sales_pipeline_id', $row->id)->where('user_id', $row->salesperson_id)->exists();
            if (empty($row->ccare_from) && $soleRole) {
                // The salesperson column's two holders split the history exactly.
                $this->assertSame($whole, array_values(array_unique(array_merge($before, $after))), "history of {$p->id} must be fully covered");
                $this->assertSame([], array_values(array_intersect($before, $after)), "no month of {$p->id} credited to both holders");
            }
        }
    }
}
