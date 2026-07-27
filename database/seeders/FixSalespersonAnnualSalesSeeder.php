<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reconciled against "old annual sale.xlsx" (41 salespeople, FY22-23 through
 * FY25-26). 39 of 41 already matched exactly across all 4 years. Only two
 * people had no row at all in salesperson_annual_sales: Atul Bhalerao and
 * Chandra Sekhar Sharma (the same person missing from the Kingo Bingo
 * targets table too -- looks like his records were never backfilled).
 */
class FixSalespersonAnnualSalesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // user_id, fy_22_23, fy_23_24, fy_24_25, fy_25_26
            [396, 337, 339, 282, 355], // Atul Bhalerao
            [560, 781, 874, 1026, 1074], // Chandra Sekhar Sharma
        ];

        foreach ($rows as [$userId, $f2223, $f2324, $f2425, $f2526]) {
            DB::table('salesperson_annual_sales')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'fy_22_23' => $f2223,
                    'fy_23_24' => $f2324,
                    'fy_24_25' => $f2425,
                    'fy_25_26' => $f2526,
                    'tenant_id' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        $this->command->info('FixSalespersonAnnualSalesSeeder: wrote 2 rows (Atul Bhalerao, Chandra Sekhar Sharma).');
    }
}
