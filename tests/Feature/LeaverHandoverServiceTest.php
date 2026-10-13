<?php

namespace Tests\Feature;

use App\Models\CcSalespersonMap;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineHandover;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use App\Services\KpiAutoSourceService;
use App\Services\LeaverHandoverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Handing a leaver's customers over: previewed first, applied exactly as previewed,
 * past months stay with the leaver, nothing else on the customer is disturbed,
 * and it can be undone.
 *
 * Fixed dates: "today" is 10 March 2031.
 */
class LeaverHandoverServiceTest extends TestCase
{
    use DatabaseTransactions;

    private User $leaver;
    private User $next;
    private User $ccAgent;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2031-03-10 11:00:00');
        // Handovers that already exist in this database (real ones made through the page) are set aside for
        // the length of the test, which runs in a transaction that is rolled back.
        DB::table('sales_pipeline_handovers')->update(['reverted_at' => now()]);
        SalesPipelineHandover::flush();
        $this->leaver  = $this->person('terminated');
        $this->next    = $this->person('active');
        $this->ccAgent = $this->person('active');
        $this->other   = $this->person('active');
    }

    protected function tearDown(): void
    {
        SalesPipelineHandover::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $status): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Ho' . $status, 'email' => 'hs' . uniqid() . '@example.test',
            'code' => 'HS' . random_int(100000, 999999), 'password' => Hash::make('x-not-used'), 'status' => $status,
        ])->fresh();
    }

    private function customer(array $cols, array $sales = ['2031-01' => 2, '2031-02' => 3, '2031-03' => 5, '2031-04' => 7]): SalesPipeline
    {
        $p = SalesPipeline::forceCreate($cols + ['party_name' => 'QA HS ' . uniqid(), 'is_locked' => 1, 'status_stage' => 'Order']);
        foreach ($sales as $m => $a) {
            SalesPipelineMonth::forceCreate(['sales_pipeline_id' => $p->id, 'month_year' => $m, 'sale_amount' => $a]);
        }

        return $p;
    }

    private function repl(array $extra = []): array
    {
        return ['salesperson' => $this->next->id, 'ccare' => $this->next->id, 'new_biz' => $this->next->id] + $extra;
    }

    private function company(): array
    {
        return DB::table('sales_pipeline_months')->whereIn('sales_pipeline_id', DB::table('sales_pipelines')->where('party_name', 'like', 'QA HS %')->pluck('id'))
            ->groupBy('month_year')->orderBy('month_year')->selectRaw('month_year, SUM(sale_amount) s')->pluck('s', 'month_year')->map(fn ($v) => (float) $v)->all();
    }

    private function revenue(User $u): float
    {
        return KpiAutoSourceService::revenue($u->id, Carbon::parse('2031-01-01'), Carbon::parse('2031-04-30'));
    }

    public function test_the_preview_changes_nothing_and_refuses_bad_requests(): void
    {
        $p = $this->customer(['salesperson_id' => $this->leaver->id]);

        $ok = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $this->assertSame([], $ok['errors']);
        $this->assertSame(1, $ok['summary']['customers']);
        $this->assertSame($this->leaver->id, (int) $p->fresh()->salesperson_id, 'a preview moves nothing');
        $this->assertFalse(SalesPipelineHandover::hasAny());

        $this->assertNotEmpty(LeaverHandoverService::plan($this->person('onboarding'), $this->repl(), '2031-04')['errors'], 'someone who has not started cannot be handed over');
        $this->assertNotEmpty(LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-02')['errors'], 'a month that has already started is refused');
        $this->assertNotEmpty(LeaverHandoverService::plan($this->leaver, $this->repl(), 'March')['errors']);
        $this->assertNotEmpty(LeaverHandoverService::plan($this->leaver, ['salesperson' => null], '2031-04')['errors'], 'no replacement chosen for a role the leaver holds');
        $this->assertNotEmpty(LeaverHandoverService::plan($this->leaver, ['salesperson' => $this->leaver->id], '2031-04')['errors'], 'not themselves');
        $this->assertNotEmpty(LeaverHandoverService::plan($this->leaver, ['salesperson' => $this->person('terminated')->id], '2031-04')['errors'], 'an inactive replacement');
    }

    public function test_a_transfer_from_someone_still_working_moves_only_the_roles_chosen(): void
    {
        $worker = $this->person('active');
        $p = $this->customer(['salesperson_id' => $worker->id, 'ccare_id' => $worker->id]);

        // Only the salesperson role is moved; the CCare role is left empty and stays with them.
        $plan = LeaverHandoverService::plan($worker, ['salesperson' => $this->next->id], '2031-04');
        $this->assertSame([], $plan['errors'], 'no complaint about the CCare role left empty');
        $this->assertNotEmpty($plan['warnings'], 'but they are told it is a transfer');
        $this->assertSame(1, $plan['summary']['role_moves']);

        LeaverHandoverService::apply($worker, ['salesperson' => $this->next->id], '2031-04', $plan['fingerprint'], null, 'transfer');
        $p->refresh();
        $this->assertSame($this->next->id, (int) $p->salesperson_id);
        $this->assertSame($worker->id, (int) $p->ccare_id, 'the role that was not chosen stays');
        $this->assertEquals(17.0, $this->revenue($worker), 'still credited on every month through the CCare role they kept');
        $this->assertEquals(7.0, $this->revenue($this->next), 'the new salesperson gets April on');
    }

    public function test_a_leaver_still_needs_every_role_covered(): void
    {
        $this->customer(['salesperson_id' => $this->leaver->id, 'ccare_id' => $this->leaver->id]);
        $plan = LeaverHandoverService::plan($this->leaver, ['salesperson' => $this->next->id], '2031-04');
        $this->assertNotEmpty($plan['errors'], 'a leaver cannot leave a role without a new owner');
    }

    public function test_apply_splits_the_months_and_the_company_total_does_not_move(): void
    {
        $p = $this->customer(['salesperson_id' => $this->leaver->id, 'new_biz_id' => $this->other->id]);
        $before = $this->company();

        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $batch = LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);

        $p->refresh();
        $this->assertSame($this->next->id, (int) $p->salesperson_id);
        $this->assertSame($this->other->id, (int) $p->new_biz_id, 'the other people on the customer are untouched');
        $this->assertEquals(10.0, $this->revenue($this->leaver), 'the leaver keeps January to March');
        $this->assertEquals(7.0, $this->revenue($this->next), 'the replacement gets April on');
        $this->assertSame($before, $this->company());
        $this->assertSame('applied', DB::table('sales_pipeline_handover_batches')->where('id', $batch)->value('status'));
    }

    public function test_apply_refuses_when_the_data_changed_since_the_preview(): void
    {
        $this->customer(['salesperson_id' => $this->leaver->id]);
        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $this->customer(['salesperson_id' => $this->leaver->id]);   // another customer arrives after the preview

        $this->expectException(\RuntimeException::class);
        try {
            LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);
        } finally {
            $this->assertFalse(SalesPipelineHandover::hasAny(), 'nothing was applied');
        }
    }

    public function test_the_new_biz_to_ccare_split_is_not_disturbed(): void
    {
        $p = $this->customer(['ccare_id' => $this->leaver->id, 'ccare_from' => '2031-02', 'former_new_biz_id' => $this->other->id]);

        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);

        $p->refresh();
        $this->assertSame('2031-02', $p->ccare_from, 'ccare_from is not rewritten');
        $this->assertSame($this->other->id, (int) $p->former_new_biz_id);
        $this->assertSame($this->next->id, (int) $p->ccare_id);
        $this->assertEquals(2.0, $this->revenue($this->other), 'the earlier New Biz person still has January');
        $this->assertEquals(8.0, $this->revenue($this->leaver), 'the leaver keeps their CCare months, February and March');
        $this->assertEquals(7.0, $this->revenue($this->next));
    }

    public function test_tags_end_client_master_moves_and_the_ccare_map_is_added_to(): void
    {
        $p = $this->customer(['salesperson_id' => $this->other->id]);
        DB::table('sales_pipeline_extra_reps')->insert(['sales_pipeline_id' => $p->id, 'user_id' => $this->leaver->id, 'type' => 'other', 'created_at' => now(), 'updated_at' => now()]);
        $crm = DB::table('crm_client_mappings')->insertGetId(['crm_company_code' => random_int(900000000, 999999999), 'crm_company_name' => 'QA HS CRM', 'salesperson_id' => $this->leaver->id, 'created_at' => now(), 'updated_at' => now()]);
        CcSalespersonMap::forceCreate(['cc_user_id' => $this->ccAgent->id, 'sales_user_id' => $this->leaver->id, 'role' => 'ccare']);

        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $this->assertSame(1, $plan['summary']['tags_ending']);
        $this->assertSame(1, $plan['summary']['client_master']);
        $this->assertSame(1, $plan['summary']['ccare_map_added']);
        $batch = LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);

        $this->assertEquals(10.0, $this->revenue($this->leaver), 'the tag still credits January to March');
        $this->assertEquals(0.0, $this->revenue($this->next), 'and the tag is not handed on');
        $this->assertSame($this->next->id, (int) DB::table('crm_client_mappings')->where('id', $crm)->value('salesperson_id'));
        $this->assertTrue(DB::table('cc_salesperson_map')->where('cc_user_id', $this->ccAgent->id)->where('sales_user_id', $this->next->id)->where('role', 'ccare')->exists());
        $this->assertTrue(DB::table('cc_salesperson_map')->where('cc_user_id', $this->ccAgent->id)->where('sales_user_id', $this->leaver->id)->exists(), 'the leaver row stays');

        LeaverHandoverService::revert($batch, null);
        $this->assertSame($this->leaver->id, (int) DB::table('crm_client_mappings')->where('id', $crm)->value('salesperson_id'));
        $this->assertFalse(DB::table('cc_salesperson_map')->where('sales_user_id', $this->next->id)->exists(), 'the added map row is removed');
        $this->assertEquals(17.0, $this->revenue($this->leaver));
    }

    public function test_undo_puts_everything_back(): void
    {
        $p = $this->customer(['salesperson_id' => $this->leaver->id]);
        $before = $this->company();
        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $batch = LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);

        $r = LeaverHandoverService::revert($batch, null);

        $this->assertSame(1, $r['reverted']);
        $this->assertSame($this->leaver->id, (int) $p->fresh()->salesperson_id);
        $this->assertFalse(SalesPipelineHandover::hasAny());
        $this->assertEquals(17.0, $this->revenue($this->leaver));
        $this->assertSame($before, $this->company());
        $this->expectException(\RuntimeException::class);
        LeaverHandoverService::revert($batch, null);   // not twice
    }

    public function test_undo_is_refused_when_a_customer_was_changed_by_hand_afterwards(): void
    {
        $p = $this->customer(['salesperson_id' => $this->leaver->id]);
        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $batch = LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);
        DB::table('sales_pipelines')->where('id', $p->id)->update(['salesperson_id' => $this->other->id]);

        try {
            LeaverHandoverService::revert($batch, null);
            $this->fail('should have been refused');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('changed after the handover', $e->getMessage());
        }
        $this->assertTrue(SalesPipelineHandover::hasAny(), 'the history was left alone');
        $this->assertSame('applied', DB::table('sales_pipeline_handover_batches')->where('id', $batch)->value('status'));
    }

    public function test_targets_are_reported_but_never_moved(): void
    {
        $this->customer(['salesperson_id' => $this->leaver->id]);
        DB::table('salesperson_targets')->insert(['salesperson_id' => $this->leaver->id, 'month_year' => '2031-05', 'kingo_target' => 10]);

        $plan = LeaverHandoverService::plan($this->leaver, $this->repl(), '2031-04');
        $this->assertSame(1, $plan['notMoved']['targets']);
        LeaverHandoverService::apply($this->leaver, $this->repl(), '2031-04', $plan['fingerprint'], null);

        $this->assertSame(1, DB::table('salesperson_targets')->where('salesperson_id', $this->leaver->id)->where('month_year', '2031-05')->count());
        $this->assertSame(0, DB::table('salesperson_targets')->where('salesperson_id', $this->next->id)->count());
    }
}
