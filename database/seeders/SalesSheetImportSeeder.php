<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Generic importer for the pre-mapped sales/pipeline JSON payloads produced by
 * the extract_*.py scripts (rep names already resolved to user ids).
 *
 *   is_locked = 1 -> /sales-report page     (index())
 *   is_locked = 0 -> /sales-pipeline page   (actualPipelineIndex())
 *
 * Usage:
 *   IMPORT_JSON=trishna_import.json php artisan db:seed \
 *       --class=SalesSheetImportSeeder --force
 *
 * getenv() is used rather than env() so this keeps working with a cached config.
 *
 * Idempotent: skips any (party_name, salesperson_id, is_locked) triple that
 * already exists, so re-running cannot double-insert.
 *
 * Rollback: created ids are written to storage/logs/import-<file>.txt, then
 *   DELETE FROM sales_pipeline_months WHERE sales_pipeline_id IN (<ids>);
 *   DELETE FROM sales_pipelines       WHERE id IN (<ids>);
 */
class SalesSheetImportSeeder extends Seeder
{
    public function run(): void
    {
        $file = getenv('IMPORT_JSON') ?: '';
        if ($file === '') {
            $this->command->error('Set IMPORT_JSON=<name>.json before running this seeder.');
            return;
        }

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

        $created = 0; $skipped = 0; $months = 0; $ids = [];

        DB::transaction(function () use ($rows, &$created, &$skipped, &$months, &$ids) {
            foreach ($rows as $r) {
                // product + stage are part of the key so that one company with
                // several distinct opportunities (e.g. Zomato Hyperpure at
                // NEGOTATION / ORDER / QUOTATION for different products) keeps
                // all its rows, while true duplicates on the sale sheets --
                // where both are null -- still collapse to one.
                $exists = DB::table('sales_pipelines')
                    ->where('party_name', $r['party_name'])
                    ->where('is_locked', $r['is_locked'])
                    ->when($r['salesperson_id'] === null,
                        fn($q) => $q->whereNull('salesperson_id'),
                        fn($q) => $q->where('salesperson_id', $r['salesperson_id']))
                    ->when(($r['product'] ?? null) === null,
                        fn($q) => $q->whereNull('product'),
                        fn($q) => $q->where('product', $r['product']))
                    ->when(($r['status_stage'] ?? null) === null,
                        fn($q) => $q->whereNull('status_stage'),
                        fn($q) => $q->where('status_stage', $r['status_stage']))
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) { $skipped++; continue; }

                $id = DB::table('sales_pipelines')->insertGetId([
                    'party_name'               => $r['party_name'],
                    'salesperson_id'           => $r['salesperson_id'],
                    'ccare_id'                 => $r['ccare_id'],
                    'new_biz_id'               => $r['new_biz_id'],
                    'total_business_potential' => $r['total_business_potential'] ?? 0,
                    'status_stage'             => $r['status_stage'] ?? null,
                    'status_remarks'           => $r['status_remarks'] ?? null,
                    'product'                  => $r['product'] ?? null,
                    'address'                  => $r['address'] ?? null,
                    'type'                     => $r['type'] ?? null,
                    'is_locked'                => $r['is_locked'],
                    'tenant_id'                => null,   // matches every existing row
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]);
                $created++; $ids[] = $id;

                foreach (($r['months'] ?? []) as $monthKey => $fields) {
                    DB::table('sales_pipeline_months')->insert([
                        'sales_pipeline_id' => $id,
                        'month_year'        => $monthKey,
                        'forecast_amount'   => $fields['forecast_amount'] ?? 0,
                        'sale_amount'       => $fields['sale_amount'] ?? 0,
                        'pending_amount'    => $fields['pending_amount'] ?? 0,
                        'tenant_id'         => null,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                    $months++;
                }
            }
        });

        if ($ids) {
            $log = storage_path('logs/import-' . pathinfo($file, PATHINFO_FILENAME) . '.txt');
            file_put_contents($log, implode(',', $ids));
            $this->command->info("Created ids written to {$log}");
        }

        $this->command->info("Pipelines created: {$created}  skipped(existing): {$skipped}  month rows: {$months}");
    }
}
