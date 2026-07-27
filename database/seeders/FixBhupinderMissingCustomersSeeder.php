<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Illuminate\Database\Seeder;

/**
 * 5 customers in "Copy of hrx sale report and pipeline format (2).xlsx" ->
 * sale-BHUPINDER SIR were missing from sales_pipelines, accounting for the
 * June gap (DB 22.98 vs file 24.79). All existing Bhupinder rows use
 * ccare_id=258 (Poonam Sharma) uniformly -- matched here for consistency.
 */
class FixBhupinderMissingCustomersSeeder extends Seeder
{
    private const SALESPERSON_ID = 376; // Bhupinder Rawat
    private const CCARE_ID = 258; // Poonam Sharma

    public function run(): void
    {
        $rows = [
            ['party' => 'SUNRISE', 'potential' => 1, 'city' => 'PUNJAB', 'type' => 'Customer', 'product' => 'Rust-X', 'april' => null, 'may' => null, 'june' => 0.13],
            ['party' => 'ESSEN FORGE', 'potential' => 2, 'city' => 'PUNJAB', 'type' => 'Customer', 'product' => 'Dr. Bio', 'april' => 1.25, 'may' => null, 'june' => 1.25],
            ['party' => 'TROPHY GALLERY', 'potential' => 1, 'city' => 'PUNJAB', 'type' => 'Customer', 'product' => 'Fillezy', 'april' => null, 'may' => null, 'june' => 0.43],
            ['party' => 'JYOTI AUTOS', 'potential' => 0, 'city' => null, 'type' => 'Customer', 'product' => 'Rust-X', 'april' => null, 'may' => null, 'june' => null],
            ['party' => 'KAPSON INDIA', 'potential' => 0, 'city' => 'PUNJAB', 'type' => 'Customer', 'product' => 'Rust-X', 'april' => null, 'may' => null, 'june' => null],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $exists = SalesPipeline::withoutGlobalScopes()
                ->where('salesperson_id', self::SALESPERSON_ID)
                ->where('party_name', $row['party'])
                ->where('is_locked', true)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            $pipeline = SalesPipeline::create([
                'party_name' => $row['party'],
                'salesperson_id' => self::SALESPERSON_ID,
                'ccare_id' => self::CCARE_ID,
                'new_biz_id' => null,
                'total_business_potential' => $row['potential'],
                'type' => $row['type'],
                'product' => $row['product'],
                'address' => $row['city'],
                'is_locked' => true,
                'tenant_id' => null,
            ]);

            foreach (['2026-04' => $row['april'], '2026-05' => $row['may'], '2026-06' => $row['june']] as $monthYear => $saleAmount) {
                if ($saleAmount === null) {
                    continue;
                }
                SalesPipelineMonth::create([
                    'sales_pipeline_id' => $pipeline->id,
                    'month_year' => $monthYear,
                    'sale_amount' => $saleAmount,
                    'tenant_id' => null,
                ]);
            }

            $created++;
        }

        $this->command->info("FixBhupinderMissingCustomersSeeder: created {$created}, skipped {$skipped}.");
    }
}
