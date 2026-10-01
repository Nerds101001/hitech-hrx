<?php

namespace Tests\Feature;

use App\Models\SalespersonTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Tally Sale column on the Sales Report's Target Achievement popup.
 *
 * These go through the real HTTP stack on purpose, because the bug that
 * prompted them was invisible to any test of the endpoint alone: the save
 * worked and the row was written, but the popup's target query listed its
 * columns explicitly and did not include tally_sale, so every cell rendered
 * empty and the Tally-vs-Actual flag could never fire. Nothing errored — the
 * figure was simply never loaded. Only rendering the page could catch it.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class SalesReportTallySaleTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::create([
            'first_name' => 'QA', 'last_name' => 'Tally',
            'email'      => 'tly' . uniqid() . '@example.test',
            'code'       => 'TLY' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($r = Role::where('name', 'admin')->first()) {
            $this->admin->assignRole($r);
        }
    }

    /** The salesperson/month the popup is already rendering, so the row exists. */
    private function renderedCell(string $html): ?array
    {
        return preg_match('/data-sp="(\d+)" data-month="([0-9\-]{7})"/', $html, $m)
            ? ['sp' => (int) $m[1], 'month' => $m[2]]
            : null;
    }

    private function rowFor(string $html, int $sp, string $month): ?string
    {
        $needle = 'data-sp="' . $sp . '" data-month="' . $month . '"';
        foreach (preg_split('/(?=<tr class="tgt-row)/', $html) as $chunk) {
            if (str_contains($chunk, $needle)) {
                return substr($chunk, 0, 8000);
            }
        }
        return null;
    }

    /**
     * REGRESSION — a saved Tally Sale has to come back out on the page. This
     * failed while tally_sale was missing from the popup's column list.
     */
    public function test_a_saved_tally_sale_is_rendered_in_the_cell(): void
    {
        $first = $this->actingAs($this->admin)->get('/sales-visits/sales-report');
        $first->assertOk();
        $cell = $this->renderedCell($first->getContent());
        if ($cell === null) {
            $this->markTestSkipped('No editable Tally cell rendered for this dataset.');
        }

        $this->actingAs($this->admin)
            ->postJson(route('sales-visits.pipeline.tally-sale.update'), [
                'salesperson_id' => $cell['sp'],
                'month_year'     => $cell['month'],
                'value'          => 4321.75,
            ])->assertOk()->assertJson(['success' => true]);

        $html = $this->actingAs($this->admin)->get('/sales-visits/sales-report')->getContent();
        $row  = $this->rowFor($html, $cell['sp'], $cell['month']);

        $this->assertNotNull($row, 'The edited row was not rendered.');
        $this->assertStringContainsString('value="4321.75"', $row,
            'The saved Tally Sale did not reach the cell.');
    }

    /**
     * The whole point of the column: a Tally that disagrees with Actual Sale
     * flags the row. Equal, and blank, must not.
     */
    public function test_a_tally_that_disagrees_with_actual_flags_the_row(): void
    {
        $first = $this->actingAs($this->admin)->get('/sales-visits/sales-report');
        $cell  = $this->renderedCell($first->getContent());
        if ($cell === null) {
            $this->markTestSkipped('No editable Tally cell rendered for this dataset.');
        }

        $row = $this->rowFor($first->getContent(), $cell['sp'], $cell['month']);
        $this->assertNotNull($row);
        $this->assertSame(8, substr_count($row, '<td'),
            'The achiever row must have one cell per header column.');
        $this->assertMatchesRegularExpression('/fw-bold tgt-actual">([0-9,.]+)</', $row);
        preg_match('/fw-bold tgt-actual">([0-9,.]+)</', $row, $m);
        $actual = (float) str_replace(',', '', $m[1]);

        $save = function ($value) use ($cell) {
            $this->actingAs($this->admin)
                ->postJson(route('sales-visits.pipeline.tally-sale.update'), [
                    'salesperson_id' => $cell['sp'],
                    'month_year'     => $cell['month'],
                    'value'          => $value,
                ])->assertOk();
            $html = $this->actingAs($this->admin)->get('/sales-visits/sales-report')->getContent();
            return $this->rowFor($html, $cell['sp'], $cell['month']) ?? '';
        };

        $off = $save($actual + 12.34);
        $this->assertStringContainsString('tgt-mm', $off, 'A mismatched row was not flagged.');
        $this->assertStringContainsString('bx-error-circle', $off, 'No warning icon on the flagged row.');

        $this->assertStringNotContainsString('tgt-mm', $save($actual),
            'A Tally equal to Actual must not be flagged.');

        // Blank means nobody has filled it in yet, which is not a discrepancy.
        $this->assertStringNotContainsString('tgt-mm', $save(null),
            'A blank Tally must not be flagged.');
    }

    /** Only the sales book's editors may write, and only while the month is open. */
    public function test_a_plain_employee_cannot_write_a_tally_sale(): void
    {
        $t = SalespersonTarget::whereNotNull('salesperson_id')->first();
        if (!$t) {
            $this->markTestSkipped('No salesperson_targets row to test against.');
        }

        $employee = User::create([
            'first_name' => 'QA', 'last_name' => 'Outsider',
            'email'      => 'out' . uniqid() . '@example.test',
            'code'       => 'OUT' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);

        $this->actingAs($employee)
            ->postJson(route('sales-visits.pipeline.tally-sale.update'), [
                'salesperson_id' => $t->salesperson_id,
                'month_year'     => $t->month_year,
                'value'          => 100,
            ])->assertForbidden();
    }

    public function test_the_endpoint_rejects_a_malformed_month_and_a_negative_value(): void
    {
        $t = SalespersonTarget::whereNotNull('salesperson_id')->first();
        if (!$t) {
            $this->markTestSkipped('No salesperson_targets row to test against.');
        }

        $this->actingAs($this->admin)
            ->postJson(route('sales-visits.pipeline.tally-sale.update'), [
                'salesperson_id' => $t->salesperson_id,
                'month_year'     => 'nope',
                'value'          => 10,
            ])->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson(route('sales-visits.pipeline.tally-sale.update'), [
                'salesperson_id' => $t->salesperson_id,
                'month_year'     => $t->month_year,
                'value'          => -1,
            ])->assertStatus(422);
    }
}
