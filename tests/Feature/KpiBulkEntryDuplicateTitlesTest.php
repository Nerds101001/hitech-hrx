<?php

namespace Tests\Feature;

use App\Http\Controllers\tenant\KpiBulkEntryController;
use App\Models\Department;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Services\KpiTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * New Biz and Sales each carry their own "Cost of Selling" KPI. Bulk KPI Entry
 * named KPIs by title alone, so the picker showed two identical chips, and the
 * upload -- which matches sheet columns by header text -- read every "Cost of
 * Selling" column as the first such KPI: a Sales-only sheet was rejected and a
 * mixed sheet silently dropped every Sales figure. A shared title now carries
 * its department everywhere the page names a KPI.
 */
class KpiBulkEntryDuplicateTitlesTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private KpiTemplate $nb;
    private KpiTemplate $sales;
    private User $nbRep;
    private User $salesRep;
    private string $title;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->title = 'ZZ Cost of Selling ' . uniqid();
        [$this->nb, $this->nbRep] = $this->deptWithKpi('ZZ New Biz');
        [$this->sales, $this->salesRep] = $this->deptWithKpi('ZZ Sales');

        $this->admin = $this->person(null);
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));
        $this->actingAs($this->admin);
    }

    private function person(?int $deptId): User
    {
        return User::create([
            'first_name' => 'QA', 'last_name' => 'Bulk ' . uniqid(),
            'email' => 'qa' . uniqid() . '@example.test', 'code' => 'QB' . random_int(100000, 999999),
            'password' => Hash::make('secret-not-used'), 'status' => 'active', 'department_id' => $deptId,
        ]);
    }

    private function deptWithKpi(string $deptName): array
    {
        $d = Department::create(['name' => $deptName . ' ' . uniqid(), 'code' => 'ZZ' . random_int(10000, 99999)]);
        $rep = $this->person($d->id);
        $t = KpiTemplate::create([
            'department_id' => $d->id, 'title' => $this->title, 'category' => 'KPI', 'frequency' => 'monthly',
            'scoring_type' => 'banded', 'trend' => 'decrease', 'target_value' => 1, 'max_marks' => 5,
            'bands' => [['min' => 0, 'marks' => -5], ['min' => 50.0001, 'marks' => 5]],
            'data_source' => 'approval', 'late_policy' => 'none', 'late_score' => 0, 'grace_days' => 0, 'is_active' => true,
            'description' => 'COS % for the month. Lower is better.',
        ]);
        KpiTemplateService::apply($t);

        return [$t, $rep];
    }

    private function sheet(array $templateIds, array $headers, float $value): UploadedFile
    {
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $book->getActiveSheet();
        $sh->fromArray([['intro'], array_merge(['User ID (do not edit)', 'Employee', 'Department'], $headers)], null, 'A1');
        $row = 3;
        foreach ([$this->nbRep, $this->salesRep] as $u) {
            $sh->fromArray(array_merge([$u->id, $u->first_name, '-'], array_fill(0, count($headers), $value)), null, "A$row");
            $row++;
        }
        $path = tempnam(sys_get_temp_dir(), 'kbe') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        return new UploadedFile($path, 'sheet.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** @return array<int,int> template id => cells the upload preview matched */
    private function upload(array $templateIds, array $headers): array
    {
        $this->post(route('tenant.kpi.bulk-entry.import'), [
            'month' => '2026-09', 'templates' => $templateIds, 'file' => $this->sheet($templateIds, $headers, 3.5),
        ]);
        $pv = session('kpi_bulk_preview');
        $counts = [];
        foreach ($pv['rows'] ?? [] as $r) {
            foreach ($r['cells'] as $tid => $c) {
                $counts[$tid] = ($counts[$tid] ?? 0) + 1;
            }
        }
        session()->forget('kpi_bulk_preview');

        return $counts;
    }

    public function test_a_shared_title_is_labelled_with_its_department(): void
    {
        $labels = KpiBulkEntryController::labelsFor(collect([$this->nb, $this->sales]));

        $this->assertStringStartsWith($this->title . ' — ZZ New Biz', $labels[$this->nb->id]);
        $this->assertStringStartsWith($this->title . ' — ZZ Sales', $labels[$this->sales->id]);
        $this->assertSame([$this->nb->id => $this->title], KpiBulkEntryController::labelsFor(collect([$this->nb])),
            'a title nobody else uses stays as it is');
    }

    public function test_the_page_names_each_kpi_by_its_department(): void
    {
        $html = $this->get(route('tenant.kpi.bulk-entry', ['month' => '2026-09', 'templates' => [$this->nb->id, $this->sales->id]]))
            ->assertOk()->getContent();
        $labels = KpiBulkEntryController::labelsFor(collect([$this->nb, $this->sales]));

        $this->assertStringContainsString(e($labels[$this->nb->id]), $html);
        $this->assertStringContainsString(e($labels[$this->sales->id]), $html);
        $this->assertStringContainsString('lower is better', $html);
    }

    public function test_the_downloaded_sheet_and_its_upload_keep_the_two_apart(): void
    {
        $resp = $this->get(route('tenant.kpi.bulk-entry.template', ['month' => '2026-09', 'templates' => [$this->nb->id, $this->sales->id]]));
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($resp->baseResponse->getFile()->getPathname());
        $header = array_slice($book->getActiveSheet()->rangeToArray('A2:E2')[0], 3);
        $labels = KpiBulkEntryController::labelsFor(collect([$this->nb, $this->sales]));
        $this->assertContains($labels[$this->nb->id], $header);
        $this->assertContains($labels[$this->sales->id], $header);

        $this->assertSame([$this->nb->id => 1, $this->sales->id => 1],
            $this->upload([$this->nb->id, $this->sales->id], $header), 'each column lands in its own department\'s KPI');
    }

    public function test_a_sales_only_sheet_is_accepted(): void
    {
        $labels = KpiBulkEntryController::labelsFor(collect([$this->nb, $this->sales]));

        $this->assertSame([$this->sales->id => 1], $this->upload([$this->sales->id], [$labels[$this->sales->id]]));
        // A sheet downloaded before the labels, with the bare title, still works
        // when only one of the KPIs being uploaded has that title.
        $this->assertSame([$this->sales->id => 1], $this->upload([$this->sales->id], [$this->title]));
    }

    public function test_an_old_sheet_with_two_bare_titles_is_refused_rather_than_guessed(): void
    {
        $counts = $this->upload([$this->nb->id, $this->sales->id], [$this->title, $this->title]);

        $this->assertSame([], $counts, 'nothing is filed under the wrong KPI');
        $this->assertStringContainsString('download a fresh sheet', (string) session('error'));
    }

    public function test_saving_the_grid_scores_each_department_separately(): void
    {
        $a = KpiAssignment::where('kpi_template_id', $this->sales->id)->where('user_id', $this->salesRep->id)->firstOrFail();

        $this->post(route('tenant.kpi.bulk-entry.store'), [
            'month' => '2026-09', 'templates' => [$this->nb->id, $this->sales->id], 'values' => [$a->id => 1.5],
        ])->assertRedirect();

        $this->assertSame(5.0, (float) \App\Models\KpiCheck::where('kpi_assignment_id', $a->id)->value('score'));
    }
}
