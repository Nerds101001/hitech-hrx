<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\User;
use App\Services\KpiAutoSourceService as Src;
use App\Services\KpiEntryService;
use App\Services\KpiScorecardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 2026-09-30, Sales / New Biz scorecards:
 *  - Market Share % is counted: the month's sales ÷ the book's MONTHLY business potential.
 *  - "Total Leads Allocated" is a shown-not-scored figure (data_source 'info'): entered in
 *    Bulk KPI Entry, a card on the scorecard, never a row, never in the total or the grade.
 *  - kpi:auto-score --backfill scores months before the KPI was assigned.
 */
class SalesNewBizScorecardAdditionsTest extends TestCase
{
    use DatabaseTransactions;

    private User $rep;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $dept = Department::create(['name' => 'ZZ Sales ' . uniqid(), 'code' => 'ZZS' . random_int(1000, 9999)]);
        $this->rep = User::create([
            'first_name' => 'QA', 'last_name' => 'Rep ' . uniqid(), 'email' => 'qasn' . uniqid() . '@example.test',
            'code' => 'QS' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'),
            'status' => 'active', 'department_id' => $dept->id,
        ]);
    }

    private function customer(float $monthlyPotential): int
    {
        return DB::table('sales_pipelines')->insertGetId(['party_name' => 'ZZ ' . uniqid(), 'salesperson_id' => $this->rep->id,
            'total_business_potential' => $monthlyPotential, 'is_locked' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function sale(int $c, string $month, float $lakh): void
    {
        DB::table('sales_pipeline_months')->insert(['sales_pipeline_id' => $c, 'month_year' => $month, 'sale_amount' => $lakh,
            'forecast_amount' => 0, 'pending_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function assignment(array $o = []): KpiAssignment
    {
        return KpiAssignment::create($o + ['user_id' => $this->rep->id, 'title' => 'QA KPI ' . uniqid(), 'category' => 'KPI',
            'frequency' => 'monthly', 'scoring_type' => 'target', 'target_value' => 10, 'max_marks' => 10,
            'data_source' => 'manual', 'is_active' => true]);
    }

    public function test_market_share_is_the_months_sales_over_the_books_monthly_potential(): void
    {
        $a = $this->customer(60);
        $b = $this->customer(40);          // book potential: 100 L a month
        $this->customer(0);                // no potential recorded: ignored
        $this->sale($a, '2026-08', 30);
        $this->sale($b, '2026-08', 5);

        $this->assertSame(35.0, Src::marketShare($this->rep->id, Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31)));
        // Two months are measured against two months of potential.
        $this->sale($a, '2026-07', 45);
        $this->assertSame(40.0, Src::marketShare($this->rep->id, Carbon::create(2026, 7, 1), Carbon::create(2026, 8, 31)));
    }

    public function test_market_share_is_no_figure_without_potential(): void
    {
        $this->sale($this->customer(0), '2026-08', 30);
        $this->assertNull(Src::marketShare($this->rep->id, Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31)));
    }

    public function test_leads_allocated_is_a_card_not_a_scored_row(): void
    {
        $scored = $this->assignment(['title' => 'QA Calls']);
        $leads = $this->assignment(['title' => 'Total Leads Allocated', 'data_source' => KpiAssignment::INFO, 'max_marks' => 0, 'target_value' => 0]);
        $month = Carbon::today()->startOfMonth();

        KpiEntryService::fillForTeam($scored, 5.0, $this->rep->id, null, $month->copy());
        $entry = KpiEntryService::fillForTeam($leads, 25.0, $this->rep->id, null, $month->copy());
        $this->assertNotNull($entry, 'leads are typed in the same way as any bulk-entry figure');
        $this->assertSame(0.0, (float) KpiCheck::where('kpi_assignment_id', $leads->id)->value('score'), 'no marks for leads');

        $card = KpiScorecardService::forMonth(collect([$this->rep->fresh()]), $month)->first();
        $this->assertSame(['Total Leads Allocated' => 25.0], $card->info);
        $this->assertNotContains('Total Leads Allocated', array_column($card->rows, 'title'), 'not a row');
        $this->assertEquals(10.0, $card->possible, 'not in the possible marks');
        $this->assertEquals(5.0, $card->earned);

        $this->assertSame(['Total Leads Allocated' => 25.0], KpiScorecardService::infoFor($this->rep->id, $month));
    }

    public function test_bulk_entry_offers_info_figures(): void
    {
        $leads = $this->assignment(['title' => 'Total Leads Allocated', 'data_source' => KpiAssignment::INFO, 'max_marks' => 0, 'target_value' => 0,
            'kpi_template_id' => \App\Models\KpiTemplate::create(['department_id' => $this->rep->department_id, 'title' => 'Total Leads Allocated', 'category' => 'KPI',
                'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 0, 'target_value' => 0, 'data_source' => KpiAssignment::INFO,
                'late_policy' => 'none', 'late_score' => 0, 'grace_days' => 0, 'is_active' => true])->id]);
        $admin = User::create(['first_name' => 'QA', 'last_name' => 'Admin ' . uniqid(), 'email' => 'qaba' . uniqid() . '@example.test',
            'code' => 'QB' . random_int(100000, 999999), 'password' => Hash::make('x'), 'status' => 'active']);
        $admin->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $m = new \ReflectionMethod(\App\Http\Controllers\tenant\KpiBulkEntryController::class, 'fillableTemplates');
        $m->setAccessible(true);
        $ids = $m->invoke(app(\App\Http\Controllers\tenant\KpiBulkEntryController::class), $admin)->pluck('id')->all();
        $this->assertContains($leads->kpi_template_id, $ids);
    }

    public function test_backfill_scores_months_before_the_kpi_was_assigned(): void
    {
        $c = $this->customer(100);
        $this->sale($c, '2026-07', 50);
        $a = $this->assignment(['title' => 'QA Market', 'data_source' => 'auto:market_share', 'target_value' => 100]);
        $a->forceFill(['created_at' => Carbon::create(2026, 9, 19)])->saveQuietly();

        Artisan::call('kpi:auto-score', ['--user' => $this->rep->id, '--as-of' => '2026-09', '--months' => 3]);
        $this->assertSame(0, KpiCheck::where('kpi_assignment_id', $a->id)->whereDate('period_start', '2026-07-01')->count(), 'without --backfill July is skipped');

        Artisan::call('kpi:auto-score', ['--user' => $this->rep->id, '--as-of' => '2026-09', '--months' => 3, '--backfill' => true]);
        $july = KpiCheck::where('kpi_assignment_id', $a->id)->whereDate('period_start', '2026-07-01')->first();
        $this->assertNotNull($july, 'with --backfill July is scored');
        $this->assertEquals(50.0, (float) $july->achieved_value);
    }
}
