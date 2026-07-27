<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Illuminate\Database\Seeder;

/**
 * Final 3 items closing the July gap: overwrite Hical Technologies and
 * Nelson Global (Pune) with Setu's figures (Setu has proven reliable across
 * every other check today), and add Suzlon Energy "Unit V" as a distinct
 * plant from the existing "Pondicherry" record.
 */
class FixNavinJoshiJulyFinalSeeder extends Seeder
{
    private const SALESPERSON_ID = 375;
    private const CCARE_ID = 253; // Shallu Sharma

    public function run(): void
    {
        // Overwrite with Setu's figures
        $overwrites = [
            ['id' => 184, 'amount' => 0.0],   // Hical Technologies
            ['id' => 7, 'amount' => 5.931],   // Nelson Global Products (Pune)
        ];
        foreach ($overwrites as $o) {
            $pipeline = SalesPipeline::withoutGlobalScopes()->find($o['id']);
            if (!$pipeline || $pipeline->salesperson_id != self::SALESPERSON_ID) {
                $this->command->warn("Skipped, id mismatch: {$o['id']}");
                continue;
            }
            SalesPipelineMonth::updateOrCreate(
                ['sales_pipeline_id' => $pipeline->id, 'month_year' => '2026-07'],
                ['sale_amount' => $o['amount'], 'tenant_id' => null]
            );
        }

        // New customer: Suzlon Energy, Unit V (distinct from Pondicherry)
        $exists = SalesPipeline::withoutGlobalScopes()
            ->where('salesperson_id', self::SALESPERSON_ID)
            ->where('party_name', 'Suzlon Energy Limited (Unit V)')
            ->where('is_locked', true)
            ->exists();

        if (!$exists) {
            $pipeline = SalesPipeline::create([
                'party_name' => 'Suzlon Energy Limited (Unit V)',
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
                'sale_amount' => 1.416,
                'tenant_id' => null,
            ]);
        }

        $this->command->info('FixNavinJoshiJulyFinalSeeder: done.');
    }
}
