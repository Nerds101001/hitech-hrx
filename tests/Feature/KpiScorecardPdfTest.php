<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\KpiController;
use App\Http\Controllers\tenant\SalesAssessmentController;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiCheck;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiScorecardService;
use App\Services\KpiTemplateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The month-wise scorecard PDF. Two things matter: the figures on the page are
 * the ones the scoring engine stored, and nobody can pull another person's
 * scorecard through the "own scorecard" door.
 */
class KpiScorecardPdfTest extends TestCase
{
    use DatabaseTransactions;

    private Department $dept;
    private User $alice;
    private User $bob;
    private User $admin;
    private Carbon $month;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        View::share('errors', new \Illuminate\Support\ViewErrorBag());

        $this->month = Carbon::today()->startOfMonth();
        $this->dept  = Department::create(['name' => 'ZZ Scorecard ' . uniqid(), 'code' => 'ZZS' . random_int(1000, 9999)]);
        $this->alice = $this->person();
        $this->bob   = $this->person();
        $this->admin = $this->person('admin');

        $kra = KpiTemplate::create(['title' => 'ZZ Output', 'category' => 'KRA', 'department_id' => $this->dept->id,
            'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => 0, 'is_active' => true]);
        foreach ([['ZZ Volume', 60, 100], ['ZZ Quality', 40, 10]] as [$title, $marks, $target]) {
            $t = KpiTemplate::create(['title' => $title, 'category' => 'KPI', 'parent_template_id' => $kra->id, 'department_id' => $this->dept->id,
                'frequency' => 'monthly', 'scoring_type' => 'target', 'max_marks' => $marks, 'target_value' => $target,
                'data_source' => 'approval', 'is_active' => true]);
            KpiTemplateService::apply($t);
        }
    }

    private function person(?string $role = null): User
    {
        static $n = 0;
        $n++;
        $u = User::create(['first_name' => 'QA', 'last_name' => "Score {$n}", 'email' => 'qasc' . uniqid() . '@example.test',
            'code' => 'QASC' . random_int(100000, 999999), 'password' => Hash::make('secret-not-used'), 'status' => 'active',
            'department_id' => $role ? null : $this->dept->id]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    private function check(User $u, string $title, float $achieved, float $score): void
    {
        $a = KpiAssignment::where('user_id', $u->id)->where('title', $title)->firstOrFail();
        KpiCheck::create(['kpi_assignment_id' => $a->id, 'user_id' => $u->id,
            'period_start' => $this->month->toDateString(), 'period_end' => $this->month->copy()->endOfMonth()->toDateString(),
            'due_date' => $this->month->copy()->endOfMonth()->toDateString(), 'status' => 'completed_on_time',
            'achieved_value' => $achieved, 'score' => $score]);
    }

    private function asUser(User $u, string $uri): Request
    {
        Auth::setUser($u);
        $req = Request::create($uri, 'GET');
        $req->setUserResolver(fn () => $u);
        return $req;
    }

    public function test_month_parsing_rejects_junk_and_the_future(): void
    {
        $this->assertNotNull(KpiScorecardService::parseMonth($this->month->format('Y-m')));
        $this->assertNull(KpiScorecardService::parseMonth($this->month->copy()->addMonth()->format('Y-m')), 'future');
        foreach (['', null, '2026-13', '2026-9', '26-09', "2026-09'; drop", '2026-09-01'] as $bad) {
            $this->assertNull(KpiScorecardService::parseMonth($bad), var_export($bad, true));
        }
        $months = KpiScorecardService::months();
        $this->assertCount(12, $months);
        $this->assertSame($this->month->format('Y-m'), $months[0]['key']);
    }

    public function test_the_page_holds_what_the_scoring_engine_stored(): void
    {
        $this->check($this->alice, 'ZZ Volume', 90, 54.0);   // 90 of 100 -> 54 of 60
        // ZZ Quality is left unreported: it still counts towards the marks possible.

        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();

        $this->assertSame(54.0, $card->earned);
        $this->assertSame(100.0, $card->possible);
        $this->assertSame(54.0, $card->pct);
        $this->assertSame('D - Needs Improvement', $card->grade);
        $this->assertCount(2, $card->rows);
        $this->assertSame('ZZ Output', $card->rows[0]->kra, 'grouped under its KRA heading');
        $byTitle = collect($card->rows)->keyBy('title');
        $this->assertSame(90.0, $byTitle['ZZ Volume']->achieved);
        $this->assertSame(90.0, $byTitle['ZZ Volume']->result);
        $this->assertSame('On time', $byTitle['ZZ Volume']->status);
        $this->assertSame('Not reported', $byTitle['ZZ Quality']->status);
        $this->assertSame(0.0, $byTitle['ZZ Quality']->score);
    }

    public function test_a_month_before_the_kpis_existed_has_no_scorecard(): void
    {
        $lastYear = $this->month->copy()->subYear();

        $this->assertTrue(KpiScorecardService::cardsFor(collect([$this->alice]), $lastYear)->isEmpty());
    }

    public function test_my_scorecard_downloads_a_pdf_of_my_own_month_only(): void
    {
        $this->check($this->alice, 'ZZ Volume', 100, 60.0);

        $res = app(KpiController::class)->my($this->asUser($this->alice, '/kpi/my?pdf=' . $this->month->format('Y-m')));

        $this->assertStringContainsString('pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
        $this->assertStringContainsString('KPI-Scorecard-qa-score', $res->headers->get('content-disposition'));
    }

    public function test_my_scorecard_refuses_a_bad_month_and_an_empty_one_without_a_pdf(): void
    {
        $bad = app(KpiController::class)->my($this->asUser($this->alice, '/kpi/my?pdf=nonsense'));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $bad);

        $none = app(KpiController::class)->my($this->asUser($this->alice, '/kpi/my?pdf=' . $this->month->copy()->subYear()->format('Y-m')));
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $none);
    }

    public function test_my_scorecard_ignores_a_user_id_someone_adds(): void
    {
        $this->check($this->alice, 'ZZ Volume', 100, 60.0);
        $this->check($this->bob, 'ZZ Volume', 10, 6.0);

        $res = app(KpiController::class)->my($this->asUser($this->alice, '/kpi/my?pdf=' . $this->month->format('Y-m') . '&user_id=' . $this->bob->id));

        $ym = $this->month->format('Y-m');
        $this->assertStringContainsString(\Illuminate\Support\Str::slug($this->alice->first_name . ' ' . $this->alice->last_name) . '-' . $ym, $res->headers->get('content-disposition'));
        $this->assertStringNotContainsString(\Illuminate\Support\Str::slug($this->bob->first_name . ' ' . $this->bob->last_name) . '-' . $ym, $res->headers->get('content-disposition'));
    }

    public function test_admin_can_download_one_person_or_a_whole_department(): void
    {
        $this->check($this->alice, 'ZZ Volume', 100, 60.0);
        $this->check($this->bob, 'ZZ Volume', 50, 30.0);
        $ym = $this->month->format('Y-m');

        $one = app(SalesAssessmentController::class)->index($this->asUser($this->admin, "/performance/scorecards?pdf={$ym}&user_id={$this->bob->id}"));
        $this->assertStringStartsWith('%PDF', $one->getContent());
        $this->assertStringContainsString(\Illuminate\Support\Str::slug($this->bob->first_name . ' ' . $this->bob->last_name), $one->headers->get('content-disposition'));

        $all = app(SalesAssessmentController::class)->index($this->asUser($this->admin, "/performance/scorecards?pdf={$ym}&department_id={$this->dept->id}"));
        $this->assertStringStartsWith('%PDF', $all->getContent());
        $this->assertStringContainsString('all-', $all->headers->get('content-disposition'));
        $this->assertGreaterThan(strlen($one->getContent()), strlen($all->getContent()), 'two people, two pages');
    }

    public function test_someone_without_the_manager_admin_or_hr_role_cannot_pull_a_colleague(): void
    {
        $this->check($this->bob, 'ZZ Volume', 50, 30.0);

        $res = app(SalesAssessmentController::class)->index(
            $this->asUser($this->alice, '/performance/scorecards?pdf=' . $this->month->format('Y-m') . '&user_id=' . $this->bob->id)
        );

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $res, 'no PDF, just a refusal');
    }
    public function test_both_pages_offer_a_month_picker_and_a_pdf_button(): void
    {
        $mine = app(KpiController::class)->my($this->asUser($this->alice, '/kpi/my'))->render();
        $this->assertStringContainsString('name="pdf"', $mine);
        $this->assertStringContainsString($this->month->format('F Y'), $mine);

        $list = app(SalesAssessmentController::class)->index($this->asUser($this->admin, '/performance/scorecards?department_id=' . $this->dept->id))->render();
        $this->assertStringContainsString('id="pdfMonth"', $list);
        $this->assertStringContainsString('id="pdfAll"', $list);
        $this->assertStringContainsString('user_id=' . $this->alice->id, $list, 'a PDF button on the row');
    }

    public function test_the_pdf_page_carries_every_detail_on_one_page(): void
    {
        $this->alice->update(['official_phone' => '9000000001', 'date_of_joining' => '2024-01-15']);
        $this->check($this->alice, 'ZZ Volume', 100, 60.0);

        $cards = KpiScorecardService::cardsFor(collect([$this->alice->fresh()]), $this->month);
        $html = view('tenant.kpi.scorecard-pdf', ['cards' => $cards, 'month' => $this->month, 'companyName' => 'Test Co'])->render();

        foreach ([$this->alice->email, $this->alice->code, '9000000001', '15 Jan 2024', $this->dept->name, 'Test Co', 'Total score', 'Reporting manager'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertSame(1, substr_count($html, 'MONTHLY KPI SCORECARD'), 'one page per person');
        $this->assertStringNotContainsString('page-break-after: always', $html, 'a single card must not add a blank trailing page');
    }
}
