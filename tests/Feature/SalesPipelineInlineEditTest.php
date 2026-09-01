<?php

namespace Tests\Feature;

use App\Models\SalesPipeline;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Inline editing on the Sales Pipeline and Sales Report grids.
 *
 * These go through the real HTTP stack on purpose. The bug that prompted them
 * was invisible to any controller-level test: the Sales Pipeline page PUT to
 * `/sales-visits/pipeline/{id}`, a URL with no route behind it, so every edit
 * 404'd and was silently discarded. The controller was fine — the wiring was
 * not. Only a request through the router could have caught it.
 *
 * DatabaseTransactions, never RefreshDatabase — the local database holds real
 * imported production data.
 */
class SalesPipelineInlineEditTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::create([
            'first_name' => 'QA', 'last_name' => 'Editor',
            'email'      => 'spe' . uniqid() . '@example.test',
            'code'       => 'SPE' . random_int(100000, 999999),
            'password'   => Hash::make('secret-not-used'),
            'status'     => 'active',
        ]);
        if ($r = Role::where('name', 'admin')->first()) {
            $this->admin->assignRole($r);
        }
    }

    private function row(array $overrides = []): SalesPipeline
    {
        return SalesPipeline::forceCreate(array_merge([
            'party_name'   => 'QA PIPELINE ' . uniqid(),
            'is_locked'    => 0,                 // 0 = Sales Pipeline, 1 = Sales Report
            'status_stage' => 'Prospect',
            'product'      => 'KIF',
        ], $overrides));
    }

    /**
     * REGRESSION — the reported bug. Change the stage, reload, and it was back
     * to the old value because the save 404'd.
     */
    public function test_a_stage_change_is_actually_saved(): void
    {
        $row = $this->row(['status_stage' => 'Prospect']);

        $this->actingAs($this->admin)
            ->putJson('/sales-visits/sales-report/' . $row->id, ['status_stage' => 'Negotiation'])
            ->assertOk();

        $this->assertSame('Negotiation', $row->fresh()->status_stage,
            'the stage must survive a reload');
    }

    /** The URL the Sales Pipeline page actually calls must exist. */
    public function test_the_pipeline_grids_save_url_is_routable(): void
    {
        $row = $this->row();

        // This is the path the page had hardcoded — it must NOT be what we use.
        $this->actingAs($this->admin)
            ->putJson('/sales-visits/pipeline/' . $row->id, ['status_stage' => 'Order'])
            ->assertNotFound();

        // The named route the view now builds its URL from.
        $this->assertSame(
            url('/sales-visits/sales-report/' . $row->id),
            route('sales-visits.pipeline.update', ['id' => $row->id])
        );
    }

    /** Every other field on the grid saved through the same broken URL. */
    public function test_the_other_inline_fields_save_too(): void
    {
        $row = $this->row();

        $cases = [
            'party_name'               => 'QA RENAMED CUSTOMER',
            'product'                  => 'RUSTX',
            'status_remarks'           => 'spoke to procurement',
            'total_business_potential' => '250.50',
        ];

        foreach ($cases as $field => $value) {
            $this->actingAs($this->admin)
                ->putJson('/sales-visits/sales-report/' . $row->id, [$field => $value])
                ->assertOk();

            $this->assertEquals($value, $row->fresh()->$field, "$field did not persist");
        }
    }

    /**
     * Moving a row to Order flips is_locked, which moves it from the Sales
     * Pipeline book into the Sales Report book. Worth pinning: it is why a row
     * legitimately disappears from the Pipeline grid after the change.
     */
    public function test_setting_the_stage_to_order_moves_the_row_into_the_sales_report_book(): void
    {
        $row = $this->row(['is_locked' => 0]);

        $this->actingAs($this->admin)
            ->putJson('/sales-visits/sales-report/' . $row->id, ['status_stage' => 'Order'])
            ->assertOk();

        $fresh = $row->fresh();
        $this->assertSame('Order', $fresh->status_stage);
        $this->assertEquals(1, $fresh->is_locked,
            'Order/Repeat Order belong to the Sales Report book');
    }

    public function test_a_stage_that_is_not_order_leaves_the_row_in_the_pipeline(): void
    {
        $row = $this->row(['is_locked' => 0]);

        $this->actingAs($this->admin)
            ->putJson('/sales-visits/sales-report/' . $row->id, ['status_stage' => 'Negotiation'])
            ->assertOk();

        $this->assertEquals(0, $row->fresh()->is_locked);
    }

    /**
     * REGRESSION — clearing the potential/YoY fields and saving crashed with
     * a 500: these columns are NOT NULL, and store() already defaulted a
     * blank value to 0 but update() sent it straight through. Reported
     * live: an existing row's Total Business Potential inline-edit failed
     * silently after the field was cleared.
     */
    public function test_clearing_a_numeric_field_saves_as_zero_instead_of_crashing(): void
    {
        $row = $this->row(['total_business_potential' => 500]);

        $this->actingAs($this->admin)
            ->putJson('/sales-visits/sales-report/' . $row->id, ['total_business_potential' => ''])
            ->assertOk();

        $this->assertEquals(0, $row->fresh()->total_business_potential);
    }

    /** A guest must not be able to edit the sales book. */
    public function test_a_guest_cannot_edit_a_row(): void
    {
        $row = $this->row(['status_stage' => 'Prospect']);

        $this->putJson('/sales-visits/sales-report/' . $row->id, ['status_stage' => 'Order']);

        $this->assertSame('Prospect', $row->fresh()->status_stage);
    }
}
