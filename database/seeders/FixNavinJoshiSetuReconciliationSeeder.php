<?php

namespace Database\Seeders;

use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use Illuminate\Database\Seeder;

/**
 * Reconciled against the Setu API (insights.effortlessanalytics.com) for
 * Navin Joshi, Apr-Jul 2026. Only the unambiguous fixes: filling genuine
 * blanks on 2 existing customers, and creating 7 customers confirmed absent
 * after manual review (automated name-matching produced false positives on
 * short/generic company names, e.g. matching "Suzlon Energy" to "R & R
 * Energy" -- every match here was verified by hand, not by score alone).
 * Two cases where Setu disagrees with an EXISTING non-zero value (Tata
 * Cummins, Rashtriya Metal) are deliberately left untouched pending user
 * confirmation -- overwriting real data is a different decision than
 * filling a blank.
 */
class FixNavinJoshiSetuReconciliationSeeder extends Seeder
{
    private const SALESPERSON_ID = 375; // Navin Chandra Joshi
    private const CCARE_ID = 253; // Shallu Sharma

    public function run(): void
    {
        // --- Fill genuine blanks on existing customers ---
        $fills = [
            ['party' => 'SONALIKA INDUSTRIES', 'month' => '2026-07', 'amount' => 0.738],
            ['party' => 'PRAJ GENX LIMITED', 'month' => '2026-05', 'amount' => -0.067],
        ];
        $filled = 0;
        foreach ($fills as $f) {
            $pipeline = SalesPipeline::withoutGlobalScopes()
                ->where('salesperson_id', self::SALESPERSON_ID)
                ->where('party_name', $f['party'])
                ->where('is_locked', true)
                ->first();
            if (!$pipeline) {
                $this->command->warn("Skipped fill, not found: {$f['party']}");
                continue;
            }
            SalesPipelineMonth::updateOrCreate(
                ['sales_pipeline_id' => $pipeline->id, 'month_year' => $f['month']],
                ['sale_amount' => $f['amount'], 'tenant_id' => null]
            );
            $filled++;
        }

        // --- Create genuinely new customers ---
        $newCustomers = [
            ['party' => 'Sr Associates', 'april' => 1.07, 'may' => null, 'june' => null, 'july' => null],
            ['party' => 'Praush Enterprises', 'april' => null, 'may' => null, 'june' => null, 'july' => 0.23],
            ['party' => 'A.N.J. Metal Recycling Private Limited', 'april' => null, 'may' => null, 'june' => null, 'july' => 0.16],
            ['party' => 'Ntpc Khargone Super Thermal Power Project', 'april' => null, 'may' => null, 'june' => null, 'july' => 0.18],
            ['party' => 'Classic Electrodes (India) Limited', 'april' => null, 'may' => null, 'june' => 3.29, 'july' => null],
            ['party' => 'Mgm Forgings Bharat Private Limited', 'april' => null, 'may' => null, 'june' => null, 'july' => 0.20],
            ['party' => 'S And S Infra Logistics Pvt.Ltd.', 'april' => null, 'may' => null, 'june' => null, 'july' => 0.16],
        ];

        $created = 0;
        foreach ($newCustomers as $row) {
            $exists = SalesPipeline::withoutGlobalScopes()
                ->where('salesperson_id', self::SALESPERSON_ID)
                ->where('party_name', $row['party'])
                ->where('is_locked', true)
                ->exists();
            if ($exists) {
                continue;
            }

            $pipeline = SalesPipeline::create([
                'party_name' => $row['party'],
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

            foreach (['2026-04' => $row['april'], '2026-05' => $row['may'], '2026-06' => $row['june'], '2026-07' => $row['july']] as $monthYear => $saleAmount) {
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

        $this->command->info("FixNavinJoshiSetuReconciliationSeeder: filled {$filled} blanks, created {$created} new customers.");
    }
}
