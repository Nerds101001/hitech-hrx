<?php

namespace Database\Seeders;

use App\Models\SalespersonTarget;
use Illuminate\Database\Seeder;

/**
 * Loads salesperson_targets from the pre-mapped JSON produced by
 * extract_targets.py (HiTech_FY2627_Targets_Workbook.xlsx).
 *
 * Units: HRX stores LAKHS. The workbook's monthly tabs are already in lakhs;
 * its 'Annual Targets' tab is in crore and was multiplied by 100 during
 * extraction. Quarterly keys are summed from their three months (the workbook
 * has no quarterly tab).
 *
 * Keys per salesperson (17): 2026-ANN, 2026-Q1..Q4, 2026-04..2027-03.
 *
 * Usage:
 *   IMPORT_JSON=targets_import.json php artisan db:seed \
 *       --class=SalesTargetsWorkbookSeeder --force
 *
 * Idempotent: updateOrCreate keyed on (salesperson_id, month_year).
 */
class SalesTargetsWorkbookSeeder extends Seeder
{
    public function run(): void
    {
        $file = getenv('IMPORT_JSON') ?: 'targets_import.json';
        $path = database_path('data/' . basename($file));

        if (!file_exists($path)) {
            $this->command->error("Data file not found: {$path}");
            return;
        }

        $rows = json_decode(file_get_contents($path), true);
        if (!is_array($rows)) {
            $this->command->error('Could not parse JSON payload.');
            return;
        }

        $created = 0; $updated = 0;

        foreach ($rows as $r) {
            $existing = SalespersonTarget::where('salesperson_id', $r['salesperson_id'])
                ->where('month_year', $r['month_year'])->first();

            SalespersonTarget::updateOrCreate(
                [
                    'salesperson_id' => $r['salesperson_id'],
                    'month_year'     => $r['month_year'],
                ],
                [
                    'super_kingo_target' => $r['super_kingo_target'],
                    'kingo_target'       => $r['kingo_target'],
                    'bingo_target'       => $r['bingo_target'],
                ]
            );

            $existing ? $updated++ : $created++;
        }

        $this->command->info("Targets created: {$created}  updated: {$updated}  total: " . count($rows));
    }
}
