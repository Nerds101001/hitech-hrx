<?php

namespace Tests\Feature;

use App\Exports\TravelClaimExport;
use App\Http\Controllers\tenant\TravelClaimController;
use App\Models\TravelClaim;
use App\Models\TravelClaimItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * The per-claim Excel export an approver uses to verify a claim offline.
 *
 * This exists because of a defect it would have caught: the first version
 * summed eleven of the twelve amount columns, silently omitting other_amount.
 * On a live claim that under-reported the total by ₹5,316.99 — a figure an
 * approver would have used to reject a perfectly valid claim, or to pay the
 * wrong amount. Any new amount column must appear here or these tests fail.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class TravelClaimExportTest extends TestCase
{
    use DatabaseTransactions;

    /** Every per-item money column that must reach the sheet and the totals. */
    private const AMOUNT_FIELDS = [
        'conveyance_amount', 'auto_taxi_amount', 'food_allowance', 'lodging_amount',
        'courier_amount', 'transport_amount', 'bills_amount', 'freight_amount',
        'toll_amount', 'additional_food_amount', 'special_approval_amount', 'other_amount',
    ];

    private User $admin;
    private User $owner;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin    = $this->makeUser('admin');
        $this->owner    = $this->makeUser();
        $this->stranger = $this->makeUser();
    }

    private function makeUser(?string $role = null): User
    {
        $u = User::create([
            'first_name' => 'QA', 'last_name' => 'Claimant',
            'email'      => 'tce' . uniqid() . '@example.test',
            'code'       => 'TCE' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($role && ($r = Role::where('name', $role)->first())) {
            $u->assignRole($r);
        }
        return $u;
    }

    /**
     * A claim whose every amount column carries a DISTINCT value, so an omitted
     * column cannot be masked by another column happening to hold the same
     * figure. total_amount is the true sum of all of them.
     */
    private function claimWithEveryAmount(int $items = 2): TravelClaim
    {
        $claim = TravelClaim::forceCreate([
            'user_id'     => $this->owner->id,
            'claim_month' => '2026-07',
            'company'     => 'Hi Tech International',
            'status'      => 'submitted',
        ]);

        $gross = 0.0;
        for ($i = 0; $i < $items; $i++) {
            $row = [
                'travel_claim_id' => $claim->id,
                'date'            => '2026-07-0' . ($i + 1),
                'from_location'   => 'A', 'to_location' => 'B',
                'party_visited'   => 'Party ' . $i,
                'mode_of_travel'  => 'Personal Bike',
                'distance_km'     => 10 + $i,
            ];
            // 101, 202, 303 … a distinct value per column per row
            foreach (self::AMOUNT_FIELDS as $n => $field) {
                $value = ($n + 1) * 101 + $i;
                $row[$field] = $value;
                $gross += $value;
            }
            TravelClaimItem::forceCreate($row);
        }

        $claim->forceFill(['total_amount' => $gross, 'net_payable' => $gross, 'total_advances' => 0])->save();

        return $claim->fresh('items');
    }

    private function sheetFor(TravelClaim $claim)
    {
        $path = sys_get_temp_dir() . '/tce_' . uniqid() . '.xlsx';
        Excel::store(new TravelClaimExport($claim), basename($path), null, \Maatwebsite\Excel\Excel::XLSX);
        $stored = storage_path('app/' . basename($path));
        $sheet = IOFactory::load($stored)->getActiveSheet();
        @unlink($stored); // every run used to leave its workbook behind in storage/app

        return $sheet;
    }

    private function totalRow($sheet): int
    {
        for ($r = $sheet->getHighestRow(); $r > 1; $r--) {
            if ($sheet->getCell('A' . $r)->getValue() === 'TOTAL') {
                return $r;
            }
        }
        $this->fail('the sheet has no TOTAL row');
    }

    /** Column letter for a heading, so the tests survive columns being reordered. */
    private function colOf($sheet, string $heading): string
    {
        foreach (range('A', 'Z') as $c) {
            if ($sheet->getCell($c . '8')->getValue() === $heading) return $c;
        }
        foreach (range('A', 'Z') as $a) {
            foreach (range('A', 'Z') as $b) {
                if ($sheet->getCell($a . $b . '8')->getValue() === $heading) return $a . $b;
            }
        }
        $this->fail("no column headed '$heading'");
    }

    // ══ the money ════════════════════════════════════════════════════════

    /**
     * REGRESSION — other_amount was left out of the row total, under-reporting
     * a live claim by ₹5,316.99.
     */
    public function test_the_sheet_total_equals_the_claims_gross(): void
    {
        $claim = $this->claimWithEveryAmount();
        $sheet = $this->sheetFor($claim);

        $row = $this->totalRow($sheet);
        $this->assertEqualsWithDelta(
            (float) $claim->total_amount,
            (float) $sheet->getCell($this->colOf($sheet, 'Row Total') . $row)->getValue(),
            0.01,
            'the sheet total must reconcile with the claim gross'
        );
    }

    /** Each amount column must total independently — not just the grand total. */
    public function test_every_amount_column_totals_correctly(): void
    {
        $claim = $this->claimWithEveryAmount(3);
        $sheet = $this->sheetFor($claim);
        $row   = $this->totalRow($sheet);

        $headings = [
            'conveyance_amount'       => 'Conveyance',
            'auto_taxi_amount'        => 'Auto/Taxi',
            'food_allowance'          => 'Food',
            'lodging_amount'          => 'Lodging',
            'courier_amount'          => 'Courier',
            'transport_amount'        => 'Transport',
            'bills_amount'            => 'Bills',
            'freight_amount'          => 'Freight',
            'toll_amount'             => 'Toll',
            'additional_food_amount'  => 'Additional Food',
            'special_approval_amount' => 'Special Approval',
            'other_amount'            => 'Other',
        ];

        foreach ($headings as $field => $heading) {
            $this->assertEqualsWithDelta(
                (float) $claim->items->sum($field),
                (float) $sheet->getCell($this->colOf($sheet, $heading) . $row)->getValue(),
                0.01,
                "the '$heading' column does not total $field"
            );
        }
    }

    /** Guards against a new amount column being added to items but not here. */
    public function test_the_sheet_carries_a_column_for_every_amount_field(): void
    {
        $claim = $this->claimWithEveryAmount(1);
        $sheet = $this->sheetFor($claim);
        $item  = $claim->items->first();

        foreach (self::AMOUNT_FIELDS as $field) {
            $value = (float) $item->$field;
            $found = false;
            foreach (range('A', 'Z') as $c) {
                if (abs((float) $sheet->getCell($c . '9')->getValue() - $value) < 0.01) { $found = true; break; }
            }
            if (!$found) {
                foreach (range('A', 'Z') as $a) {
                    foreach (range('A', 'Z') as $b) {
                        if (abs((float) $sheet->getCell($a . $b . '9')->getValue() - $value) < 0.01) { $found = true; break 2; }
                    }
                }
            }
            $this->assertTrue($found, "$field ({$value}) does not appear anywhere on the sheet");
        }
    }

    /**
     * REGRESSION — a 0.00 was written as an empty cell, which reads as "no
     * figure given" and breaks any SUM the verifier applies.
     */
    public function test_fields_the_form_no_longer_collects_stay_off_an_ordinary_claim(): void
    {
        // Auto/Taxi, Transport, Bills, Freight and Additional Food were trimmed
        // from the sheet (054f6a157) because the form stopped collecting them.
        // They only come back when a claim actually carries an amount in one.
        $claim = $this->claimWithEveryAmount();
        foreach ($claim->items as $item) {
            $item->forceFill(['auto_taxi_amount' => 0, 'transport_amount' => 0, 'bills_amount' => 0, 'freight_amount' => 0, 'additional_food_amount' => 0])->save();
        }
        $gross = $claim->items->sum(fn ($i) => $i->conveyance_amount + $i->food_allowance + $i->lodging_amount + $i->courier_amount
            + $i->toll_amount + $i->special_approval_amount + $i->other_amount);
        $claim->forceFill(['total_amount' => $gross])->save();

        $sheet = $this->sheetFor($claim->fresh('items'));
        $headings = $sheet->rangeToArray('A8:' . $sheet->getHighestColumn() . '8')[0];
        foreach (['Auto/Taxi', 'Transport', 'Bills', 'Freight', 'Additional Food'] as $gone) {
            $this->assertNotContains($gone, $headings);
        }
        $this->assertEquals($gross, (float) $sheet->getCell($this->colOf($sheet, 'Row Total') . $this->totalRow($sheet))->getCalculatedValue());
    }

    public function test_zero_amounts_are_written_as_numeric_zero_not_blank(): void
    {
        $claim = $this->claimWithEveryAmount(1);
        $claim->items->first()->forceFill(['toll_amount' => 0, 'courier_amount' => 0])->save();
        $claim->refresh();

        $sheet = $this->sheetFor($claim->fresh('items'));

        foreach (['Toll', 'Courier'] as $heading) {
            $cell = $sheet->getCell($this->colOf($sheet, $heading) . '9')->getValue();
            $this->assertNotNull($cell, "$heading was left blank instead of 0");
            $this->assertSame(0.0, (float) $cell);
        }
    }

    /** Amounts must be numbers, or the verifier cannot sum or sort them. */
    public function test_amounts_are_numeric_not_formatted_text(): void
    {
        $sheet = $this->sheetFor($this->claimWithEveryAmount(1));

        $value = $sheet->getCell($this->colOf($sheet, 'Conveyance') . '9')->getValue();
        $this->assertIsNumeric($value);
        $this->assertStringNotContainsString('₹', (string) $value);
    }

    // ══ structure ════════════════════════════════════════════════════════

    /**
     * REGRESSION — an empty spacer row is dropped by the writer, which put the
     * freeze pane and the autofilter on a data row instead of the headings.
     */
    public function test_the_headings_row_is_frozen_and_filtered(): void
    {
        $sheet = $this->sheetFor($this->claimWithEveryAmount());

        $this->assertSame('Date', $sheet->getCell('A8')->getValue(), 'headings must be on row 8');
        $this->assertSame('D9', $sheet->getFreezePane(), 'heading row and first three columns must be frozen');
        $this->assertStringContainsString('8:', $sheet->getAutoFilter()->getRange());
    }

    public function test_every_line_item_reaches_the_sheet(): void
    {
        $claim = $this->claimWithEveryAmount(5);
        $sheet = $this->sheetFor($claim);

        $this->assertSame(5, $this->totalRow($sheet) - 10,
            'rows 9..N hold the items, then a spacer, then TOTAL');
    }

    // ══ authorisation ════════════════════════════════════════════════════

    public function test_an_unrelated_employee_cannot_export_someone_elses_claim(): void
    {
        $claim = $this->claimWithEveryAmount(1);
        Auth::login($this->stranger);

        $this->expectException(HttpException::class);
        app(TravelClaimController::class)->exportExcel($claim->id);
    }

    public function test_the_owner_and_an_admin_can_export(): void
    {
        $claim = $this->claimWithEveryAmount(1);

        foreach ([$this->owner, $this->admin] as $user) {
            Auth::login($user);
            $response = app(TravelClaimController::class)->exportExcel($claim->id);
            $this->assertSame(200, $response->getStatusCode());
        }
    }
}
