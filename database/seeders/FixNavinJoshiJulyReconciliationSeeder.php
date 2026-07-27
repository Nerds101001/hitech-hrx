<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Illuminate\Database\Seeder;

/**
 * Closes the remaining July 2026 gap for Navin Joshi (DB was 82.76L vs
 * Setu's 96.94L). Fills genuine blanks on 6 existing customers, and adds
 * Davi Engineering's Chakan location as a new customer (a 3rd distinct
 * plant, consistent with how Bhosari is already a separate record from the
 * base Davi Engineering entry). Two cases where Setu disagrees with an
 * EXISTING non-zero value (Hical Technologies, Nelson Global Pune) are
 * deliberately left untouched pending confirmation.
 */
class FixNavinJoshiJulyReconciliationSeeder extends Seeder
{
    private const SALESPERSON_ID = 375;
    private const CCARE_ID = 253; // Shallu Sharma

    public function run(): void
    {
        $fills = [
            ['id' => 9913, 'amount' => 0.078],  // Kross Limited
            ['id' => 9909, 'amount' => -0.153], // Yes Tecno Limited
            ['id' => 9921, 'amount' => 0.904],  // RSR Aviation Services
            ['id' => 9904, 'amount' => 0.252],  // Castpro India Private Limited
            ['id' => 9922, 'amount' => 9.490],  // Signode India (Telangana)
            ['id' => 9905, 'amount' => 0.769],  // Syrma Sgs Technology
        ];

        $filled = 0;
        foreach ($fills as $f) {
            $pipeline = SalesPipeline::withoutGlobalScopes()->find($f['id']);
            if (!$pipeline || $pipeline->salesperson_id != self::SALESPERSON_ID) {
                $this->command->warn("Skipped fill, id mismatch: {$f['id']}");
                continue;
            }
            SalesPipelineMonth::updateOrCreate(
                ['sales_pipeline_id' => $pipeline->id, 'month_year' => '2026-07'],
                ['sale_amount' => $f['amount'], 'tenant_id' => null]
            );
            $filled++;
        }

        // New customer: Davi Engineering's Chakan location (distinct from
        // the base and Bhosari records already present)
        $exists = SalesPipeline::withoutGlobalScopes()
            ->where('salesperson_id', self::SALESPERSON_ID)
            ->where('party_name', 'Davi Engineering Pvt.Ltd-Chakan')
            ->where('is_locked', true)
            ->exists();

        $created = 0;
        if (!$exists) {
            $pipeline = SalesPipeline::create([
                'party_name' => 'Davi Engineering Pvt.Ltd-Chakan',
                'salesperson_id' => self::SALESPERSON_ID,
                'ccare_id' => self::CCARE_ID,
                'new_biz_id' => null,
                'total_business_potential' => 0,
                'type' => 'Customer',
                'product' => 'Rust-X',
                'address' => null,
                'is_locked' => true,
                'tenant_id' => null,
            ]);
            SalesPipelineMonth::create([
                'sales_pipeline_id' => $pipeline->id,
                'month_year' => '2026-07',
                'sale_amount' => 0.592,
                'tenant_id' => null,
            ]);
            $created = 1;
        }

        $this->command->info("FixNavinJoshiJulyReconciliationSeeder: filled {$filled} blanks, created {$created} new customer.");
    }
}
