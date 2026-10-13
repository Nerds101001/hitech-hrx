<?php

namespace Tests\Feature;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineHandover;
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
 * After a leaver's customer is handed over from March, every report must give the
 * leaver January and February and the replacement March -- and the company total
 * for every month must not move by a rupee.
 *
 * Fixed dates: "today" is 10 March 2031, in FY 2030-31.
 */
class SalesLeaverHandoverReportsTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $leaver;
    private User $next;
    private SalesPipeline $p;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2031-03-10 11:00:00');
        // Handovers that already exist in this database (real ones made through the page) are set aside for
        // the length of the test, which runs in a transaction that is rolled back.
        DB::table('sales_pipeline_handovers')->update(['reverted_at' => now()]);
        SalesPipelineHandover::flush();
        $this->admin  = $this->person('admin');
        $this->leaver = $this->person();
        $this->next   = $this->person();

        $this->p = SalesPipeline::forceCreate([
            'party_name' => 'QA HOREPORT ' . uniqid(), 'is_locked' => 1, 'status_stage' => 'Order', 'product' => 'KIF',
            'salesperson_id' => $this->leaver->id,
        ]);
        foreach (['2031-01' => 2, '2031-02' => 3, '2031-03' => 5] as $m => $amt) {
            SalesPipelineMonth::forceCreate(['sales_pipeline_id' => $this->p->id, 'month_year' => $m, 'sale_amount' => $amt, 'forecast_amount' => $amt]);
        }
        foreach ([$this->leaver, $this->next] as $u) {
            foreach (['2031-01', '2031-02', '2031-03'] as $m) {
                DB::table('salesperson_targets')->insert(['salesperson_id' => $u->id, 'month_year' => $m, 'kingo_target' => 10, 'bingo_target' => 10, 'super_kingo_target' => 10]);
            }
        }
    }

    protected function tearDown(): void
    {
        SalesPipelineHandover::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'HoRep', 'email' => 'hr' . uniqid() . '@example.test',
            'code' => 'HR' . random_int(100000, 999999), 'password' => Hash::make('x-not-used'), 'status' => 'active', 'department_id' => 30,
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }

        return $u->fresh();
    }

    /** What the Sales Report page shows for this customer, month by month. */
    private function reportTotals(): array
    {
        $r = $this->actingAs($this->admin)->get('/sales-visits/sales-report?' . http_build_query(['party' => $this->p->party_name]));
        $r->assertOk();
        $m = $r->viewData('monthlyActuals');

        return [(float) $m['2031-01'], (float) $m['2031-02'], (float) $m['2031-03']];
    }

    private function handOver(): void
    {
        DB::table('sales_pipelines')->where('id', $this->p->id)->update(['salesperson_id' => $this->next->id]);
        DB::table('sales_pipeline_handovers')->insert([
            'batch_id' => 0, 'sales_pipeline_id' => $this->p->id, 'role' => 'salesperson_id',
            'from_user_id' => $this->leaver->id, 'to_user_id' => $this->next->id, 'effective_month' => '2031-03',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        SalesPipelineHandover::flush();
    }

    private function revenue(User $u): float
    {
        return KpiAutoSourceService::revenue($u->id, Carbon::parse('2031-01-01'), Carbon::parse('2031-03-31'));
    }

    private function achievement(User $u, string $month): ?float
    {
        $start = Carbon::parse($month . '-01');

        return KpiAutoSourceService::scorecardAchievement($u->id, $start, $start->copy()->endOfMonth(), 'kingo_target');
    }

    public function test_before_any_handover_everything_is_as_it_was(): void
    {
        $this->assertSame([2.0, 3.0, 5.0], $this->reportTotals());
        $this->assertEquals(10.0, $this->revenue($this->leaver));
        $this->assertEquals(0.0, $this->revenue($this->next));
    }

    public function test_the_company_total_does_not_move_by_a_rupee(): void
    {
        $before = $this->reportTotals();
        $this->handOver();

        $this->assertSame($before, $this->reportTotals(), 'the Sales Report total for every month is unchanged');
    }

    public function test_kpi_revenue_and_achievement_follow_the_months(): void
    {
        $this->handOver();

        $this->assertEquals(5.0, $this->revenue($this->leaver), 'leaver: January + February');
        $this->assertEquals(5.0, $this->revenue($this->next), 'replacement: March');
        $this->assertEquals(30.0, $this->achievement($this->leaver, '2031-02'));
        $this->assertEquals(0.0, $this->achievement($this->next, '2031-02'), 'the replacement is not credited with February');
        $this->assertEquals(0.0, $this->achievement($this->leaver, '2031-03'), 'the leaver is not credited with March');
        $this->assertEquals(50.0, $this->achievement($this->next, '2031-03'));
    }

    public function test_kingo_bingo_credits_each_person_their_own_months(): void
    {
        $this->handOver();

        $r = $this->actingAs($this->admin)->get('/sales-visits/kingo-bingo?fy=2030-2031');
        $r->assertOk();
        $auto = $r->viewData('autoAchieved');

        $this->assertEquals([2.0, 3.0, 0.0], [$auto[$this->leaver->id]['2031-01']['actual_sale'], $auto[$this->leaver->id]['2031-02']['actual_sale'], $auto[$this->leaver->id]['2031-03']['actual_sale']]);
        $this->assertEquals([0.0, 0.0, 5.0], [$auto[$this->next->id]['2031-01']['actual_sale'], $auto[$this->next->id]['2031-02']['actual_sale'], $auto[$this->next->id]['2031-03']['actual_sale']]);
    }

    public function test_the_sales_by_salesperson_sums_follow_the_months(): void
    {
        $this->handOver();

        foreach (['2031-02' => [$this->leaver->id => 3.0], '2031-03' => [$this->next->id => 5.0]] as $month => $expected) {
            $by = DB::table('sales_pipeline_months as m')->join('sales_pipelines as sp', 'sp.id', '=', 'm.sales_pipeline_id')
                ->where('m.month_year', $month)->where('sp.id', $this->p->id)
                ->groupBy('spid')
                ->selectRaw(SalesPipelineHandover::holderSql('salesperson_id', 'sp', 'm') . ' AS spid, SUM(m.sale_amount) AS achieved')
                ->pluck('achieved', 'spid');
            $this->assertEquals($expected, $by->map(fn ($v) => (float) $v)->all(), "salesperson of $month");
        }
    }
}
