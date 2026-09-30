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
        $this->assertGreaterThan(strlen($one->getContent()), strlen($all->getContent()), 'two people, two pages of one page each');
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

    public function test_the_pdf_carries_every_detail_and_a_year_to_date_page(): void
    {
        $this->alice->update(['official_phone' => '9000000001', 'date_of_joining' => '2024-01-15']);
        $this->check($this->alice, 'ZZ Volume', 100, 60.0);

        $cards = KpiScorecardService::cardsFor(collect([$this->alice->fresh()]), $this->month);
        $html = view('tenant.kpi.scorecard-pdf', ['cards' => $cards, 'month' => $this->month, 'companyName' => 'Test Co'])->render();

        foreach ([$this->alice->email, $this->alice->code, '9000000001', '15 Jan 2024', $this->dept->name, 'Test Co', 'Total score', 'Reporting manager'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertSame(1, substr_count($html, 'MONTHLY KPI SCORECARD'), 'one title band per person');
        // One person, one page: a review is read side by side, not turned
        // over. So a single card has NO break at all -- breaks separate people.
        $this->assertSame(0, substr_count($html, 'page-break-after: always'), 'a single card must not break');
        foreach (['Year to date', 'Needs attention', 'Remarks'] as $section) {
            $this->assertStringContainsString($section, $html);
        }
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf);
    }

    public function test_each_person_is_exactly_one_page_in_a_bulk_file(): void
    {
        $this->check($this->alice, 'ZZ Volume', 100, 60.0);
        $this->check($this->bob, 'ZZ Volume', 50, 30.0);

        $one  = KpiScorecardService::download(KpiScorecardService::cardsFor(collect([$this->alice]), $this->month), $this->month, 'a.pdf')->getContent();
        $both = KpiScorecardService::download(KpiScorecardService::cardsFor(collect([$this->alice, $this->bob]), $this->month), $this->month, 'b.pdf')->getContent();

        $this->assertSame(1, $this->pageCount($one));
        $this->assertSame(2, $this->pageCount($both));
    }

    public function test_year_to_date_lists_earlier_months_and_flags_what_needs_attention(): void
    {
        $this->check($this->alice, 'ZZ Volume', 30, 18.0);   // 30 of 100: scored 18 of 60, under half

        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();

        $this->assertNotEmpty($card->ytd);
        $this->assertTrue(end($card->ytd)->current);
        $why = collect($card->attention)->keyBy('title');
        $this->assertStringContainsString('30% of target', $why['ZZ Volume']->why);
        $this->assertStringContainsString('Nothing was reported', $why['ZZ Quality']->why);
    }
    public function test_an_empty_pending_check_reads_as_not_reported_not_as_reported(): void
    {
        // The scoring engine creates the month's check early, with nothing in it.
        $a = KpiAssignment::where('user_id', $this->alice->id)->where('title', 'ZZ Volume')->firstOrFail();
        KpiCheck::create(['kpi_assignment_id' => $a->id, 'user_id' => $this->alice->id, 'period_start' => $this->month->toDateString(),
            'period_end' => $this->month->copy()->endOfMonth()->toDateString(), 'due_date' => $this->month->copy()->endOfMonth()->toDateString(), 'status' => 'pending']);

        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();
        $status = collect($card->rows)->pluck('status', 'title');

        $this->assertSame('Not reported', $status['ZZ Volume']);
        $this->assertSame(['ZZ Volume', 'ZZ Quality'], collect($card->attention)->pluck('title')->all(), 'both are flagged, biggest marks first');
    }

    public function test_a_number_waiting_for_the_approver_is_shown_as_such(): void
    {
        $a = KpiAssignment::where('user_id', $this->alice->id)->where('title', 'ZZ Quality')->firstOrFail();
        \App\Services\KpiEntryService::submit($a, 7, 'sent');

        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();
        $row = collect($card->rows)->firstWhere('title', 'ZZ Quality');

        $this->assertSame('Awaiting approval', $row->status);
        $this->assertStringContainsString('waiting for the approver', collect($card->attention)->firstWhere('title', 'ZZ Quality')->why);
    }

    public function test_rows_follow_the_order_the_scorecard_was_set_up_in_not_the_alphabet(): void
    {
        // ZZ Volume was created before ZZ Quality; alphabetically Quality would come first.
        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();

        $this->assertSame(['ZZ Volume', 'ZZ Quality'], collect($card->rows)->pluck('title')->all());
    }
    public function test_a_yearly_kpi_appears_in_every_month_it_spans_as_year_to_date(): void
    {
        $t = KpiTemplate::create(['title' => 'ZZ Yearly', 'category' => 'KPI', 'department_id' => $this->dept->id, 'frequency' => 'yearly',
            'check_month' => 3, 'check_day' => 31, 'scoring_type' => 'banded', 'bands' => [['min' => 0, 'marks' => 0], ['min' => 50, 'marks' => 8]],
            'max_marks' => 10, 'target_value' => 100, 'data_source' => 'auto:bingo_achievement', 'is_active' => true]);
        KpiTemplateService::apply($t);
        $a = KpiAssignment::where('user_id', $this->alice->id)->where('title', 'ZZ Yearly')->firstOrFail();

        // The year's one check starts long before this month and runs to next March.
        $fy = $this->month->month >= 4 ? Carbon::create($this->month->year, 4, 1) : Carbon::create($this->month->year - 1, 4, 1);
        KpiCheck::create(['kpi_assignment_id' => $a->id, 'user_id' => $this->alice->id, 'period_start' => $fy->toDateString(),
            'period_end' => $fy->copy()->addYear()->subDay()->toDateString(), 'due_date' => $fy->copy()->addYear()->subDay()->toDateString(),
            'status' => 'completed_on_time', 'achieved_value' => 60, 'score' => 8]);

        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();
        $row = collect($card->rows)->firstWhere('title', 'ZZ Yearly');

        $this->assertNotNull($row);
        $this->assertSame('Year to date', $row->status);
        $this->assertSame(8.0, $row->score);
        $this->assertTrue($row->banded);
    }

    public function test_a_banded_row_shows_no_target_and_no_percent_of_target(): void
    {
        $t = KpiTemplate::create(['title' => 'ZZ Banded', 'category' => 'KPI', 'department_id' => $this->dept->id, 'frequency' => 'monthly',
            'scoring_type' => 'banded', 'bands' => [['min' => 0, 'marks' => 0], ['min' => 5, 'marks' => 4]],
            'max_marks' => 4, 'target_value' => 100, 'data_source' => 'approval', 'is_active' => true]);
        KpiTemplateService::apply($t);
        $this->check($this->alice, 'ZZ Banded', 6, 4.0);

        $cards = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month);
        $html = view('tenant.kpi.scorecard-pdf', ['cards' => $cards, 'month' => $this->month, 'companyName' => ''])->render();
        $pos = strpos($html, 'ZZ Banded');
        $rowHtml = substr($html, $pos, strpos($html, '</tr>', $pos) - $pos);

        $this->assertStringNotContainsString('100', $rowHtml, 'the stand-in target of 100 must not be printed');
        $this->assertStringNotContainsString('600%', $rowHtml);
        $this->assertStringContainsString('>6<', $rowHtml, 'the figure itself is shown');
    }
    public function test_the_sales_rule_book_is_graded_on_raw_points_out_of_100_so_63_is_a_c(): void
    {
        $desig = \App\Models\Designation::create(['name' => 'ZZ Mgr ' . uniqid(), 'code' => 'ZZR' . random_int(10000, 99999)]);
        $rep = $this->person();
        $rep->update(['designation_id' => $desig->id]);
        foreach (\App\Support\SalesRuleBook::install($this->dept->id, [$desig->id]) as $t) {
            KpiTemplateService::apply($t);
        }

        // 63 points in total, each within its KPI's maximum (scores are capped at
        // the maximum since 2026-09-30, and Demos and Cost of Selling have left the
        // rule book). What is under test is the scale: 63 out of 100 is a C.
        $points = ['Target Achievement (Bingo %)' => 15, 'Pipeline Strength' => 5, 'YoY Revenue Growth' => 10,
            'New Customer Wins (%)' => 13, '90-Day Goals' => 3, 'Sales Pitch Score' => 2, 'Learn@HiTech' => 2, 'Seminars / Presentations' => 2,
            'Competitor Insights' => 3, 'New Product Ideas' => 3, 'Cross-Sell / Basket Mix' => 0,
            'Market Share %' => 10, 'Attrition Penalty' => -5];
        $fy = $this->month->month >= 4 ? Carbon::create($this->month->year, 4, 1) : Carbon::create($this->month->year - 1, 4, 1);
        foreach ($points as $title => $score) {
            $a = KpiAssignment::where('user_id', $rep->id)->where('title', $title)->firstOrFail();
            KpiCheck::create(['kpi_assignment_id' => $a->id, 'user_id' => $rep->id, 'period_start' => $fy->toDateString(),
                'period_end' => $fy->copy()->addYear()->subDay()->toDateString(), 'due_date' => $fy->copy()->addYear()->subDay()->toDateString(),
                'status' => 'completed_on_time', 'achieved_value' => 1, 'score' => $score]);
        }

        // forMonth(), not cardsFor(): cardsFor() is the PRINT path and re-reads
        // every auto source, which would recompute these hand-written points
        // out of thin source data. What is under test here is the scale.
        $card = KpiScorecardService::forMonth(collect([$rep]), $this->month)->first();
        $card->onPoints = $card->onPoints ?? false;

        $this->assertSame(63.0, $card->earned);
        $this->assertSame(100.0, $card->possible, 'graded out of 100, not out of the 110 the weights add to');
        $this->assertSame(63.0, $card->pct);
        $this->assertSame('C - Meets Expectation', $card->grade);
        $this->assertTrue($card->onPoints);

        $html = view('tenant.kpi.scorecard-pdf', ['cards' => collect([$card]), 'month' => $this->month, 'companyName' => ''])->render();
        $this->assertStringContainsString('63 points out of 100', $html);
        $this->assertStringContainsString('C - Meets Expectation', $html);
    }

    public function test_before_new_customers_can_be_counted_the_sales_card_is_graded_on_the_other_87_points(): void
    {
        $desig = \App\Models\Designation::create(['name' => 'ZZ Mgr ' . uniqid(), 'code' => 'ZZR' . random_int(10000, 99999)]);
        $rep = $this->person();
        $rep->update(['designation_id' => $desig->id]);
        foreach (\App\Support\SalesRuleBook::install($this->dept->id, [$desig->id]) as $t) {
            KpiTemplateService::apply($t);
        }
        KpiAssignment::where('user_id', $rep->id)->update(['created_at' => '2026-07-01']);

        // August 2026: the Sales Report had just been loaded, so New Customer Wins has no figure.
        $aug = Carbon::create(2026, 8, 1);
        $points = ['Target Achievement (Bingo %)' => 15, 'YoY Revenue Growth' => 20, 'Market Share %' => 10, 'Pipeline Strength' => 5, 'New Customer Wins (%)' => null];
        foreach ($points as $title => $score) {
            $a = KpiAssignment::where('user_id', $rep->id)->where('title', $title)->firstOrFail();
            KpiCheck::create(['kpi_assignment_id' => $a->id, 'user_id' => $rep->id, 'period_start' => '2026-08-01',
                'period_end' => '2026-08-31', 'due_date' => '2026-08-31', 'status' => $score === null ? 'pending' : 'completed_on_time',
                'achieved_value' => $score === null ? null : 1, 'score' => $score ?? 0]);
        }

        $card = KpiScorecardService::forMonth(collect([$rep]), $aug)->first();
        $row = collect($card->rows)->firstWhere('title', 'New Customer Wins (%)');

        $this->assertSame('Not measurable', $row->status);
        $this->assertSame(50.0, $card->earned);
        $this->assertSame(87.0, $card->possible, '100 less New Customer Wins\' 13');
        $this->assertSame(57.5, $card->pct);

        $html = view('tenant.kpi.scorecard-pdf', ['cards' => collect([$card]), 'month' => $aug, 'companyName' => ''])->render();
        $this->assertStringContainsString('50 points out of 87 measurable (57.5%)', $html);
        $this->assertStringContainsString('Not measurable', $html);
    }

    public function test_a_figure_stored_for_august_under_the_old_rule_is_ignored(): void
    {
        $desig = \App\Models\Designation::create(['name' => 'ZZ Mgr ' . uniqid(), 'code' => 'ZZR' . random_int(10000, 99999)]);
        $rep = $this->person();
        $rep->update(['designation_id' => $desig->id]);
        foreach (\App\Support\SalesRuleBook::install($this->dept->id, [$desig->id]) as $t) {
            KpiTemplateService::apply($t);
        }
        KpiAssignment::where('user_id', $rep->id)->update(['created_at' => '2026-07-01']);
        foreach (['Target Achievement (Bingo %)' => [1, 15], 'New Customer Wins (%)' => [100, 13]] as $title => [$achieved, $score]) {
            $a = KpiAssignment::where('user_id', $rep->id)->where('title', $title)->firstOrFail();
            KpiCheck::create(['kpi_assignment_id' => $a->id, 'user_id' => $rep->id, 'period_start' => '2026-08-01',
                'period_end' => '2026-08-31', 'due_date' => '2026-08-31', 'status' => 'completed_on_time',
                'achieved_value' => $achieved, 'score' => $score]);
        }

        $card = KpiScorecardService::forMonth(collect([$rep]), Carbon::create(2026, 8, 1))->first();
        $row = collect($card->rows)->firstWhere('title', 'New Customer Wins (%)');

        $this->assertSame('Not measurable', $row->status);
        $this->assertNull($row->achieved);
        $this->assertSame(0.0, $row->score);
        $this->assertSame(15.0, $card->earned, 'the stale 13 marks are not counted');
        $this->assertSame(87.0, $card->possible);
    }

    public function test_other_scorecards_are_still_graded_on_their_own_marks(): void
    {
        $this->check($this->alice, 'ZZ Volume', 90, 54.0);

        $card = KpiScorecardService::cardsFor(collect([$this->alice]), $this->month)->first();

        $this->assertFalse($card->onPoints);
        $this->assertSame(100.0, $card->possible);
        $this->assertSame(54.0, $card->pct);
    }
}
