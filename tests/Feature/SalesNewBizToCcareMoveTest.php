<?php

namespace Tests\Feature;

use App\Models\CcSalespersonMap;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use App\Services\KpiAutoSourceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A customer that moves from New Biz to CCare keeps its earlier months under
 * New Biz. Switching used to re-label the whole history: every month it sold
 * as a New Biz customer moved to CCare - in the Sales Report's Category totals
 * and in both people's numbers. Now ccare_from marks the first CCare month.
 *
 * Also the 5-month rule: sales in 5 different months move a New Biz customer
 * to CCare (its old check tested the customer *type* column and never fired).
 *
 * Fixed dates: "today" is 10 March 2031, in FY 2030-31.
 */
class SalesNewBizToCcareMoveTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $salesperson;
    private User $newBiz;
    private User $ccare;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2031-03-10 11:00:00');
        $this->admin = $this->person('admin');
        $this->salesperson = $this->person();
        $this->newBiz = $this->person();
        $this->ccare = $this->person();
        CcSalespersonMap::forceCreate(['cc_user_id' => $this->ccare->id, 'sales_user_id' => $this->salesperson->id, 'role' => 'ccare']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Move', 'email' => 'nbm' . uniqid() . '@example.test',
            'code' => 'NBM' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }

        return $u->fresh();
    }

    /** A New Biz customer on the Sales Report with sales in the given months. */
    private function customer(array $sales): SalesPipeline
    {
        $p = SalesPipeline::forceCreate([
            'party_name' => 'QA NBMOVE ' . uniqid(), 'is_locked' => 1, 'status_stage' => 'Order', 'product' => 'KIF',
            'salesperson_id' => $this->salesperson->id, 'new_biz_id' => $this->newBiz->id,
        ]);
        foreach ($sales as $month => $amount) {
            SalesPipelineMonth::forceCreate(['sales_pipeline_id' => $p->id, 'month_year' => $month, 'sale_amount' => $amount]);
        }

        return $p;
    }

    private function switchToCcare(SalesPipeline $p): void
    {
        $this->actingAs($this->admin)
            ->putJson('/sales-visits/sales-report/' . $p->id, ['category' => 'CCare'])
            ->assertOk()->assertJson(['success' => true]);
    }

    private function revenue(User $u): float
    {
        return KpiAutoSourceService::revenue($u->id, Carbon::parse('2031-01-01'), Carbon::parse('2031-03-31'));
    }

    public function test_switching_to_ccare_keeps_the_earlier_months_under_new_biz(): void
    {
        $p = $this->customer(['2031-01' => 2, '2031-02' => 3, '2031-03' => 5]);
        $this->switchToCcare($p);
        $p->refresh();

        $this->assertSame($this->ccare->id, (int) $p->ccare_id);
        $this->assertNull($p->new_biz_id);
        $this->assertSame('2031-03', $p->ccare_from, 'CCare from the month of the switch');
        $this->assertSame($this->newBiz->id, (int) $p->former_new_biz_id);

        $this->assertTrue($p->isNewBizMonth('2031-02'));
        $this->assertFalse($p->isNewBizMonth('2031-03'));
        $this->assertTrue($p->creditsMonth($this->newBiz->id, '2031-02'));
        $this->assertFalse($p->creditsMonth($this->newBiz->id, '2031-03'));
        $this->assertFalse($p->creditsMonth($this->ccare->id, '2031-02'));
        $this->assertTrue($p->creditsMonth($this->ccare->id, '2031-03'));
        $this->assertTrue($p->creditsMonth($this->salesperson->id, '2031-02'), 'the salesperson keeps every month');
        $this->assertTrue($p->creditsMonth($this->salesperson->id, '2031-03'));
    }

    public function test_putting_it_back_under_new_biz_makes_every_month_new_biz_again(): void
    {
        $p = $this->customer(['2031-01' => 2]);
        $this->switchToCcare($p);
        $this->actingAs($this->admin)->putJson('/sales-visits/sales-report/' . $p->id, ['category' => 'New Biz'])->assertOk();
        $p->refresh();

        $this->assertNotNull($p->new_biz_id);
        $this->assertNull($p->ccare_from);
        $this->assertNull($p->former_new_biz_id);
        $this->assertTrue($p->isNewBizMonth('2031-03'));
    }

    public function test_sales_report_totals_split_at_the_move(): void
    {
        $p = $this->customer(['2031-01' => 2, '2031-02' => 3, '2031-03' => 5]);
        $this->switchToCcare($p);

        $totals = function (array $filters) use ($p) {
            $r = $this->actingAs($this->admin)->get('/sales-visits/sales-report?' . http_build_query(['party' => $p->party_name] + $filters));
            $r->assertOk();
            $m = $r->viewData('monthlyActuals');

            return [$r->viewData('totalNewClients'), $m['2031-01'], $m['2031-02'], $m['2031-03']];
        };

        $this->assertEquals([1, 2, 3, 0], $totals(['cat' => 'New Biz']), 'New Biz: the customer, with its months before the move');
        $this->assertEquals([1, 0, 0, 5], $totals(['cat' => 'CCare']), 'CCare: only from the move');
        $this->assertEquals([1, 2, 3, 5], $totals([]), 'no filter: every month, as before');
        $this->assertEquals([1, 2, 3, 0], $totals(['nb' => $this->newBiz->id]), 'its New Biz person still finds it, for their months');
        $this->assertEquals([1, 0, 0, 5], $totals(['ccare' => $this->ccare->id]));

        $html = $this->actingAs($this->admin)->get('/sales-visits/sales-report?' . http_build_query(['party' => $p->party_name, 'cat' => 'New Biz']))->getContent();
        $this->assertStringContainsString('New Biz till Feb 31', $html);
        $this->assertStringContainsString('month-out', $html, 'the March figure is greyed out under New Biz');
    }

    public function test_each_side_is_credited_only_its_own_months(): void
    {
        $p = $this->customer(['2031-01' => 2, '2031-02' => 3, '2031-03' => 5]);
        // Picking the CCare rep directly also keeps the New Biz person on as an
        // extra rep - which must not hand them the CCare months.
        $this->actingAs($this->admin)->putJson('/sales-visits/sales-report/' . $p->id, ['ccare_id' => $this->ccare->id])->assertOk();
        $this->assertSame('2031-03', $p->fresh()->ccare_from);
        $this->assertTrue(DB::table('sales_pipeline_extra_reps')->where('sales_pipeline_id', $p->id)->where('user_id', $this->newBiz->id)->exists());

        $this->assertEquals(5.0, $this->revenue($this->newBiz), 'New Biz: January + February');
        $this->assertEquals(5.0, $this->revenue($this->ccare), 'CCare: March');
        $this->assertEquals(10.0, $this->revenue($this->salesperson), 'salesperson: all of it');

        DB::table('salesperson_targets')->insert(['salesperson_id' => $this->newBiz->id, 'month_year' => '2031-02', 'kingo_target' => 10]);
        $this->assertEquals(30.0, KpiAutoSourceService::scorecardAchievement($this->newBiz->id, Carbon::parse('2031-02-01'), Carbon::parse('2031-02-28'), 'kingo_target'));
        DB::table('salesperson_targets')->insert(['salesperson_id' => $this->ccare->id, 'month_year' => '2031-02', 'kingo_target' => 10]);
        $this->assertEquals(0.0, KpiAutoSourceService::scorecardAchievement($this->ccare->id, Carbon::parse('2031-02-01'), Carbon::parse('2031-02-28'), 'kingo_target'));
    }

    public function test_a_customer_that_never_moved_is_counted_exactly_as_before(): void
    {
        $p = $this->customer(['2031-01' => 2, '2031-03' => 5]);

        $this->assertNull($p->fresh()->ccare_from);
        $this->assertEquals(7.0, $this->revenue($this->newBiz));
        $this->assertTrue($p->creditsMonth($this->ccare->id, '2031-01'), 'no move: nobody loses a month');
    }

    public function test_the_fifth_month_with_sales_moves_it_to_ccare_from_the_next_month(): void
    {
        $p = $this->customer(['2030-10' => 1, '2030-11' => 1, '2030-12' => 1, '2031-01' => 1]);

        $r = $this->actingAs($this->admin)->postJson('/sales-visits/sales-report/month', [
            'sales_pipeline_id' => $p->id, 'month_year' => '2031-03', 'field' => 'sale_amount', 'value' => 4,
        ]);
        $r->assertOk()->assertJson(['success' => true, 'moved_to_ccare' => true, 'ccare_id' => $this->ccare->id, 'ccare_from' => 'Apr 2031']);

        $p->refresh();
        $this->assertNull($p->new_biz_id);
        $this->assertSame($this->ccare->id, (int) $p->ccare_id);
        $this->assertSame('2031-04', $p->ccare_from, 'March - the 5th month - still counts as New Biz');
        $this->assertTrue($p->isNewBizMonth('2031-03'));
    }

    public function test_a_fifth_month_reached_in_the_past_moves_it_from_this_month_not_retroactively(): void
    {
        $p = $this->customer(['2030-08' => 1, '2030-09' => 1, '2030-10' => 1, '2030-11' => 1, '2030-12' => 1, '2031-01' => 1]);

        $this->assertTrue($p->graduateIfDue());
        $this->assertSame('2031-03', $p->fresh()->ccare_from, 'closed months are never re-labelled');
    }

    public function test_four_months_is_not_enough_and_no_ccare_person_means_no_move(): void
    {
        $four = $this->customer(['2030-10' => 1, '2030-11' => 1, '2030-12' => 1, '2031-01' => 1]);
        $this->assertFalse($four->graduateIfDue());
        $this->assertNotNull($four->fresh()->new_biz_id);

        $five = $this->customer(['2030-10' => 1, '2030-11' => 1, '2030-12' => 1, '2031-01' => 1, '2031-02' => 1]);
        $five->forceFill(['salesperson_id' => $this->person()->id])->save();   // nobody mapped
        $this->assertFalse($five->graduateIfDue());
        $this->assertNotNull($five->fresh()->new_biz_id);
    }

    public function test_the_daily_command_moves_customers_that_are_due(): void
    {
        $p = $this->customer(['2030-10' => 1, '2030-11' => 1, '2030-12' => 1, '2031-01' => 1, '2031-02' => 2]);

        $this->artisan('sales:graduate-newbiz', ['--dry-run' => true])->assertExitCode(0);
        $this->assertNotNull($p->fresh()->new_biz_id, 'a dry run changes nothing');

        $this->artisan('sales:graduate-newbiz')->assertExitCode(0);
        $this->assertSame('2031-03', $p->fresh()->ccare_from);
    }
}
