<?php

namespace Tests\Feature;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineHandover;
use App\Models\SalesPipelineMonth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** The Admin / HR screen for handovers: who may use it, and that every step behaves. */
class LeaverHandoverPageTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $plain;
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
        $this->admin  = $this->person('active', 'admin');
        config(['handover.viewer_emails' => [$this->admin->email]]);
        $this->plain  = $this->person('active');
        $this->leaver = $this->person('terminated');
        $this->next   = $this->person('active');
        $this->p = SalesPipeline::forceCreate(['party_name' => 'QA HOPAGE ' . uniqid(), 'is_locked' => 1, 'status_stage' => 'Order', 'salesperson_id' => $this->leaver->id]);
        SalesPipelineMonth::forceCreate(['sales_pipeline_id' => $this->p->id, 'month_year' => '2031-02', 'sale_amount' => 3]);
        SalesPipelineMonth::forceCreate(['sales_pipeline_id' => $this->p->id, 'month_year' => '2031-05', 'sale_amount' => 4]);
    }

    protected function tearDown(): void
    {
        SalesPipelineHandover::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $status, ?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'HoPage', 'email' => 'hp' . uniqid() . '@example.test',
            'code' => 'HP' . random_int(100000, 999999), 'password' => Hash::make('x-not-used'), 'status' => $status,
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }

        return $u->fresh();
    }

    private function body(array $extra = []): array
    {
        return ['leaver_id' => $this->leaver->id, 'month' => '2031-04', 'salesperson' => $this->next->id] + $extra;
    }

    public function test_only_admin_and_hr_may_open_or_use_it(): void
    {
        // Hidden: anyone not on the viewer list gets "not found" -- a plain user, and an admin who is not named.
        $otherAdmin = $this->person('active', 'admin');
        foreach ([$this->plain, $otherAdmin] as $who) {
            foreach ([['get', ''], ['postJson', '/preview'], ['postJson', '/apply']] as [$verb, $path]) {
                $r = $this->actingAs($who)->{$verb}('/sales-visits/leaver-handover' . $path, $verb === 'get' ? [] : $this->body());
                // A user with no Sales access is turned away earlier by the Sales area's own guard
                // (a redirect, same as every Sales page); an admin who is not named gets "not found".
                if ($who === $otherAdmin) {
                    $r->assertNotFound();
                } else {
                    $this->assertContains($r->getStatusCode(), [302, 403, 404]);
                }
            }
        }
        // And an empty list admits nobody, not even the admin.
        config(['handover.viewer_emails' => []]);
        $this->actingAs($this->admin)->get('/sales-visits/leaver-handover')->assertNotFound();
        $this->assertSame($this->leaver->id, (int) $this->p->fresh()->salesperson_id);
    }

    public function test_the_page_lists_the_leaver_and_what_they_hold(): void
    {
        $r = $this->actingAs($this->admin)->get('/sales-visits/leaver-handover');
        $r->assertOk()->assertSee('Leaver Handover');
        $this->assertTrue(collect($r->viewData('leavers'))->contains(fn ($l) => $l['id'] === $this->leaver->id && $l['salesperson'] >= 1));
    }

    public function test_preview_then_apply_then_undo(): void
    {
        $prev = $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/preview', $this->body())->assertOk();
        $this->assertSame([], $prev->json('errors'));
        $this->assertEquals(3.0, $prev->json('summary.sale_stays_L'));
        $this->assertEquals(4.0, $prev->json('summary.sale_moves_L'));
        $this->assertSame($this->leaver->id, (int) $this->p->fresh()->salesperson_id, 'previewing moved nothing');
        $fp = $prev->json('fingerprint');

        // Without the typed word, or with a stale fingerprint, nothing happens.
        $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/apply', $this->body(['fingerprint' => $fp, 'confirm' => 'yes']))->assertStatus(422);
        $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/apply', $this->body(['fingerprint' => 'stale', 'confirm' => 'HANDOVER']))->assertStatus(422);
        $this->assertSame($this->leaver->id, (int) $this->p->fresh()->salesperson_id);

        $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/apply', $this->body(['fingerprint' => $fp, 'confirm' => 'HANDOVER']))
            ->assertOk()->assertJson(['success' => true]);
        $this->assertSame($this->next->id, (int) $this->p->fresh()->salesperson_id);
        $batch = (int) DB::table('sales_pipeline_handover_batches')->where('leaver_id', $this->leaver->id)->value('id');
        $this->assertSame($this->admin->id, (int) DB::table('sales_pipeline_handover_batches')->where('id', $batch)->value('created_by'), 'who did it is recorded');

        $this->actingAs($this->admin)->postJson("/sales-visits/leaver-handover/{$batch}/revert")->assertOk()->assertJson(['success' => true]);
        $this->assertSame($this->leaver->id, (int) $this->p->fresh()->salesperson_id);
    }

    public function test_applying_opens_a_detailed_sheet_of_who_moved_to_whom(): void
    {
        $fp = $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/preview', $this->body())->json('fingerprint');
        $apply = $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/apply', $this->body(['fingerprint' => $fp, 'confirm' => 'HANDOVER']))->assertOk();

        $url = $apply->json('redirect');
        $this->assertNotEmpty($url, 'the apply response says where the detailed sheet is');
        $batch = (int) $apply->json('batch');

        $sheet = $this->actingAs($this->admin)->get("/sales-visits/leaver-handover/{$batch}")->assertOk();
        $sheet->assertSee($this->p->party_name)->assertSee('QA HoPage');
        $d = $sheet->viewData('customers');
        $this->assertCount(1, $d);
        $this->assertSame('Salesperson', $d[0]['role']);
        $this->assertSame('QA HoPage', $d[0]['from']);
        $this->assertEquals(3.0, $d[0]['stays'], 'February stays with the leaver');
        $this->assertEquals(4.0, $d[0]['goes'], 'May goes to the replacement');
    }

    public function test_the_sheet_downloads_as_a_csv_and_is_for_admin_and_hr_only(): void
    {
        $fp = $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/preview', $this->body())->json('fingerprint');
        $batch = (int) $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/apply', $this->body(['fingerprint' => $fp, 'confirm' => 'HANDOVER']))->json('batch');

        $csv = $this->actingAs($this->admin)->get("/sales-visits/leaver-handover/{$batch}/export")->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $text = $csv->streamedContent();
        $this->assertStringContainsString($this->p->party_name, $text);
        $this->assertStringContainsString('Customer,Role,From,To', $text);

        foreach (["/sales-visits/leaver-handover/{$batch}", "/sales-visits/leaver-handover/{$batch}/export"] as $url) {
            $this->assertContains($this->actingAs($this->plain)->get($url)->getStatusCode(), [302, 403, 404]);
            $this->actingAs($this->person('active', 'admin'))->get($url)->assertNotFound();
        }
        $this->actingAs($this->admin)->get('/sales-visits/leaver-handover/99999999')->assertNotFound();
    }

    public function test_the_sidebar_link_is_only_for_the_named_person(): void
    {
        $named = User::where('email', 'ai@rustx.com')->first();
        $this->assertNotNull($named, 'needs the real ai@rustx.com account in the local database');
        config(['handover.viewer_emails' => ['ai@rustx.com']]);

        $page = '/sales-visits/delete-requests';   // any Sales page that carries the sidebar, open to Admin/HR
        $theirs = $this->actingAs($named)->get($page)->assertOk()->getContent();
        $this->assertStringContainsString('/sales-visits/leaver-handover', $theirs, 'ai@rustx.com sees the link');

        $otherAdmin = $this->person('active', 'admin');
        $others = $this->actingAs($otherAdmin)->get($page)->assertOk()->getContent();
        $this->assertStringNotContainsString('/sales-visits/leaver-handover', $others, 'another admin does not see it');
    }

    public function test_a_bad_request_is_explained_not_applied(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/preview', ['leaver_id' => $this->leaver->id, 'month' => '2031-04']);
        $r->assertOk();
        $this->assertNotEmpty($r->json('errors'), 'no replacement chosen');
        $this->actingAs($this->admin)->postJson('/sales-visits/leaver-handover/preview', ['leaver_id' => $this->leaver->id, 'month' => 'soon'])->assertStatus(422);
    }
}
