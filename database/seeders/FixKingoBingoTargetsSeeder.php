<?php

namespace Database\Seeders;

use App\Models\SalespersonTarget;
use Illuminate\Database\Seeder;

/**
 * Reconciles salesperson_targets against HiTech_FY2627_Targets_Workbook.xlsx
 * (prepared 15 Jul 2026). Of 1416 (manager, month, level) values checked,
 * only two gaps: Chandra Sekhar Sharma had no target rows at all for FY26-27,
 * and Ajinkya Pathare (Jul'26) / Vikas Kamlakar (May-Jul'26) had stale/zero
 * values instead of the workbook's figures. Everything else already matched.
 */
class FixKingoBingoTargetsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Chandra Sekhar Sharma (id 560) -- entirely missing, all 12 months
            [560, '2026-04', 110, 126.5, 135.9],
            [560, '2026-05', 115.5, 132.83, 142.7],
            [560, '2026-06', 121.28, 139.47, 149.8],
            [560, '2026-07', 132.5, 140.5, 150.9],
            [560, '2026-08', 134.2, 142.3, 152.8],
            [560, '2026-09', 135.9, 144.1, 154.8],
            [560, '2026-10', 137.6, 145.9, 156.7],
            [560, '2026-11', 139.3, 147.7, 158.6],
            [560, '2026-12', 141, 149.5, 160.6],
            [560, '2027-01', 142.7, 151.3, 162.5],
            [560, '2027-02', 144.3, 153.1, 164.4],
            [560, '2027-03', 145.7, 154.8, 166.3],
            // Ajinkya Pathare (id 484) -- Jul'26 was stuck at 0
            [484, '2026-07', 6.7, 7.0, 7.5],
            // Vikas Kamlakar (id 415) -- May/Jun/Jul'26 had stale flat values
            [415, '2026-05', 165.9, 190.79, 205.0],
            [415, '2026-06', 174.2, 200.32, 215.2],
            [415, '2026-07', 175.2, 201.3, 216.3],
        ];

        $updated = 0;
        foreach ($rows as [$salespersonId, $monthYear, $bingo, $kingo, $superKingo]) {
            SalespersonTarget::withoutGlobalScopes()->updateOrCreate(
                ['salesperson_id' => $salespersonId, 'month_year' => $monthYear],
                ['bingo_target' => $bingo, 'kingo_target' => $kingo, 'super_kingo_target' => $superKingo, 'tenant_id' => null]
            );
            $updated++;
        }

        $this->command->info("FixKingoBingoTargetsSeeder: wrote {$updated} (salesperson, month) rows.");
    }
}
