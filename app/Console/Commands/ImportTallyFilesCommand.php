<?php

namespace App\Console\Commands;

use App\Models\TallyImport;
use App\Services\TallyAnalytics;
use App\Support\NameMatcher;
use App\Support\ProductGroup;
use App\Support\TallyFileReader;
use App\Support\TallyTotals;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads Tally's daily text exports into HRX so they can be browsed and
 * compared against the manual Sales Report and Setu (Sales Sources pages).
 *
 * Each export is a complete snapshot of its date window, so a dataset is
 * replaced wholesale -- but only when the file looks right. A missing file, or
 * one where too many lines fail to parse (the usual sign that Tally's export
 * layout changed and the positional columns have shifted), is REJECTED and the
 * last good data for that dataset is left exactly as it was.
 */
class ImportTallyFilesCommand extends Command
{
    protected $signature = 'hrx:import-tally
                            {--dir= : Folder holding the Tally exports (default: services.tally.export_dir)}
                            {--dry-run : Parse and validate only; write nothing}
                            {--force-overwrite-push-data : Replace tally_invoices/tally_invoice_lines/tally_sale_lines/tally_sale_orders even though hrx:tally-push has written to them}';

    /**
     * Tables also written by the newer API push pipeline (hrx:tally-push / TallyPushController), which upserts
     * or replaces by key rather than by whole table. A plain full-table delete+reinsert here would silently
     * wipe everything that pipeline has added -- see --force-overwrite-push-data below.
     */
    private const SHARED_WITH_PUSH = ['tally_invoices', 'tally_invoice_lines', 'tally_sale_lines', 'tally_sale_orders'];

    protected $description = 'Load the Tally daily exports (invoices, items, GST detail, outstanding, sale orders) for browsing and analytics';

    /** dataset => [file, expected column count, target table]. The key doubles as the map…() method name. */
    private const DATASETS = [
        'invoices'    => ['Abc.txt', 16, 'tally_invoices'],
        'lines'       => ['Invoice Details.txt', 8, 'tally_invoice_lines'],
        'saleLines'   => ['SaleReportCRM.txt', 15, 'tally_sale_lines'],
        'outstanding' => ['OutstandingDet.txt', 8, 'tally_outstanding'],
        'orders'      => ['PartyWiseSaleOrder.txt', 8, 'tally_sale_orders'],
    ];

    /** A dataset is only replaced when at least this share of its lines parsed cleanly. */
    private const MIN_GOOD_RATIO = 0.98;

    public function handle(): int
    {
        $dir = rtrim((string) ($this->option('dir') ?: config('services.tally.export_dir')), '/\\');
        $dry = (bool) $this->option('dry-run');

        if ($dir === '' || !is_dir($dir)) {
            $this->error("Tally export folder not found: {$dir}");
            return Command::FAILURE;
        }

        $import = $dry ? null : TallyImport::create(['status' => 'running', 'source_dir' => $dir, 'started_at' => now()]);
        $summary = [];
        $good = 0;

        foreach (self::DATASETS as $key => [$file, $columns, $table]) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                $summary[$key] = ['file' => $file, 'status' => 'missing'];
                $this->warn("{$file}: not found — left unchanged");
                continue;
            }

            $read = TallyFileReader::read($path, $columns);
            [$records, $invalid, $duplicates, $min, $max] = $this->{'map' . ucfirst($key)}($read['rows']);

            $accepted = $read['total'] > 0
                && count($records) > 0
                && (count($records) / $read['total']) >= self::MIN_GOOD_RATIO;

            $summary[$key] = [
                'file' => $file,
                'status' => $accepted ? 'ok' : 'rejected',
                'lines' => $read['total'],
                'imported' => count($records),
                'repaired_wrapped_lines' => $read['glued'],
                'malformed_lines' => $read['malformed'],
                'invalid_values' => $invalid,
                'duplicates' => $duplicates,
                'from' => $min,
                'to' => $max,
                'file_modified' => date('Y-m-d H:i', filemtime($path)),
            ];
            if ($read['samples']) {
                $summary[$key]['malformed_samples'] = $read['samples'];
            }

            if (!$accepted) {
                $this->error("{$file}: REJECTED ({$read['malformed']} malformed, {$invalid} invalid of {$read['total']} lines) — previous data kept. Has Tally's export layout changed?");
                continue;
            }

            if (in_array($table, self::SHARED_WITH_PUSH, true) && !$this->option('force-overwrite-push-data') && $this->pushHasWrittenTo($table)) {
                $summary[$key]['status'] = 'skipped_push_data';
                $this->error("{$file}: SKIPPED — hrx:tally-push has written to {$table} and a plain replace would erase that. Re-run with --force-overwrite-push-data to overwrite it anyway.");
                continue;
            }

            if (!$dry) {
                DB::transaction(function () use ($table, $records) {
                    DB::table($table)->delete();
                    foreach (array_chunk($records, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                });
            }
            $good++;
            $this->info(sprintf('%-24s %6d rows  %s → %s%s', $file, count($records), $min, $max, $dry ? '  (dry run)' : ''));
        }

        $status = $good === count(self::DATASETS) ? 'ok' : ($good > 0 ? 'partial' : 'failed');
        if (!$dry && $good > 0) {
            TallyTotals::stamp();
        }
        $import?->update(['status' => $status, 'summary' => $summary, 'finished_at' => now()]);
        $this->line("Result: {$status}");

        if (!$dry && $good > 0) {
            try {
                TallyAnalytics::warm();                      // first person to open Sales Sources shouldn't be the one who waits
                $this->line('Analytics cache warmed.');
            } catch (\Throwable $e) {
                $this->warn('Imported, but warming the analytics cache failed: ' . $e->getMessage());
            }
        }

        return $status === 'failed' ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Deliberately coarse: true the moment ANY hrx:tally-push run has ever succeeded, regardless of which
     * table this particular file targets. Distinguishing "push touched THIS table" from "push touched some
     * other table" would need a per-row source column that doesn't exist -- safer to protect all four shared
     * tables together than to risk a false negative on one of them.
     */
    private function pushHasWrittenTo(string $table): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('tally_push_runs')
            && DB::table('tally_push_runs')->where('status', 'ok')->exists();
    }

    private static function str(?string $v, int $max = 255): ?string
    {
        $v = trim((string) $v);
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    private static function norm(?string $name): ?string
    {
        $n = NameMatcher::normalize((string) $name);
        return $n === '' ? null : $n;
    }

    /** Abc.txt: [0 salesperson, 1 date, 2 invoice no, 3 amount, 4 party code, 5 party, 6 LR no, 7 transporter, 8 phone, 9 destination, 10-13 unlabelled, 14 PO, 15 company] */
    private function mapInvoices(array $rows): array
    {
        $out = []; $invalid = 0; $dupes = 0; $min = null; $max = null;
        foreach ($rows as $r) {
            $date = TallyFileReader::date($r[1]);
            $amount = TallyFileReader::amount($r[3]);
            if ($date === null || $amount === null || $r[2] === '') { $invalid++; continue; }
            if (isset($out[$r[2]])) { $dupes++; }
            $out[$r[2]] = [
                'invoice_no' => self::str($r[2], 60),
                'series' => TallyFileReader::series($r[2]),
                'invoice_date' => $date,
                'party_code' => self::str($r[4], 20),
                'party_name' => self::str($r[5]),
                'normalized_name' => self::norm($r[5]),
                'salesperson' => self::str($r[0], 120),
                'amount' => $amount,
                'lr_no' => self::str($r[6], 60),
                'transporter' => self::str($r[7]),
                'transporter_phone' => self::str($r[8], 60),
                'destination' => self::str($r[9], 120),
                'po_number' => self::str($r[14], 120),
                'is_branch_transfer' => strcasecmp($r[0], 'Branch Transfer') === 0 ? 1 : 0,
                'extra' => json_encode(['f10' => $r[10], 'f11' => $r[11], 'flag' => $r[12], 'f13' => $r[13]]),
                'created_at' => now(), 'updated_at' => now(),
            ];
            $min = $min === null || $date < $min ? $date : $min;
            $max = $max === null || $date > $max ? $date : $max;
        }
        return [array_values($out), $invalid, $dupes, $min, $max];
    }

    /** Invoice Details.txt: [0 invoice no, 1 date, 2 item, 3 qty+unit, 4 salesperson, 5 party code, 6 party, 7 company] */
    private function mapLines(array $rows): array
    {
        $out = []; $invalid = 0; $min = null; $max = null;
        foreach ($rows as $r) {
            $date = TallyFileReader::date($r[1]);
            if ($date === null || $r[0] === '') { $invalid++; continue; }
            [$qty, $unit] = TallyFileReader::quantity($r[3]);
            $out[] = [
                'invoice_no' => self::str($r[0], 60), 'series' => TallyFileReader::series($r[0]), 'invoice_date' => $date,
                'item_name' => self::str($r[2], 500), 'product_group' => ProductGroup::detect($r[2]),
                'quantity' => $qty, 'unit' => $unit,
                'salesperson' => self::str($r[4], 120), 'party_code' => self::str($r[5], 20), 'party_name' => self::str($r[6]),
                'is_branch_transfer' => strcasecmp(trim($r[4]), 'Branch Transfer') === 0 ? 1 : 0,
            ];
            $min = $min === null || $date < $min ? $date : $min;
            $max = $max === null || $date > $max ? $date : $max;
        }
        return [$out, $invalid, 0, $min, $max];
    }

    /**
     * SaleReportCRM.txt: [0 party code, 1 party, 2 city, 3 date, 4 invoice no, 5 item, 6 HSN, 7 GST %,
     * 8 qty+unit, 9 rate/unit, 10 taxable value, 11 tax, 12 due date, 13 credit terms, 14 salesperson].
     * Covers only the invoices Tally's CRM report includes -- not every invoice in Abc.txt.
     */
    private function mapSaleLines(array $rows): array
    {
        $out = []; $invalid = 0; $min = null; $max = null;
        foreach ($rows as $r) {
            $date = TallyFileReader::date($r[3]);
            $taxable = TallyFileReader::amount($r[10]);
            if ($date === null || $taxable === null || $r[4] === '') { $invalid++; continue; }
            [$qty, $unit] = TallyFileReader::quantity($r[8]);
            [$rate, $rateUnit] = TallyFileReader::rate($r[9]);
            $out[] = [
                'invoice_no' => self::str($r[4], 60), 'series' => TallyFileReader::series($r[4]), 'invoice_date' => $date,
                'party_code' => self::str($r[0], 20), 'party_name' => self::str($r[1]), 'normalized_name' => self::norm($r[1]),
                'city' => self::str($r[2], 120),
                'item_name' => self::str($r[5], 500) ?? '',
                'product_group' => ProductGroup::detect($r[5], $r[6]),
                'hsn' => self::str($r[6], 16),
                'gst_rate' => is_numeric($r[7]) ? (float) $r[7] : null,
                'quantity' => $qty, 'unit' => $unit, 'rate' => $rate, 'rate_unit' => $rateUnit,
                'taxable_amount' => $taxable, 'tax_amount' => TallyFileReader::amount($r[11]),
                'due_date' => TallyFileReader::date($r[12]),
                'credit_terms' => self::str($r[13], 40),
                'salesperson' => self::str($r[14], 120),
                'is_branch_transfer' => strcasecmp(trim($r[14]), 'Branch Transfer') === 0 ? 1 : 0,
            ];
            $min = $min === null || $date < $min ? $date : $min;
            $max = $max === null || $date > $max ? $date : $max;
        }
        return [$out, $invalid, 0, $min, $max];
    }

    /** OutstandingDet.txt: [0 bill ref, 1 bill date, 2 party code, 3 party, 4 ledger group, 5 amount, 6 credit period, 7 company] */
    private function mapOutstanding(array $rows): array
    {
        $out = []; $invalid = 0; $min = null; $max = null;
        foreach ($rows as $r) {
            $date = TallyFileReader::date($r[1]);
            $amount = TallyFileReader::amount($r[5]);
            if ($date === null || $amount === null) { $invalid++; continue; }
            $out[] = [
                'bill_ref' => self::str($r[0], 60), 'bill_date' => $date,
                'party_code' => self::str($r[2], 20), 'party_name' => self::str($r[3]), 'normalized_name' => self::norm($r[3]),
                'ledger_group' => self::str($r[4], 120), 'amount' => $amount,
                'credit_days' => TallyFileReader::creditDays($r[6]),
            ];
            $min = $min === null || $date < $min ? $date : $min;
            $max = $max === null || $date > $max ? $date : $max;
        }
        return [$out, $invalid, 0, $min, $max];
    }

    /** PartyWiseSaleOrder.txt: [0 date, 1 order no, 2 status, 3 party code, 4 party, 5 qty+unit, 6 amount, 7 company] */
    private function mapOrders(array $rows): array
    {
        $out = []; $invalid = 0; $min = null; $max = null;
        foreach ($rows as $r) {
            $date = TallyFileReader::date($r[0]);
            $amount = TallyFileReader::amount($r[6]);
            if ($date === null || $amount === null) { $invalid++; continue; }
            [$qty, $unit] = TallyFileReader::quantity($r[5]);
            $out[] = [
                'order_date' => $date, 'order_no' => self::str($r[1], 60), 'status' => self::str($r[2], 40),
                'party_code' => self::str($r[3], 20), 'party_name' => self::str($r[4]), 'normalized_name' => self::norm($r[4]),
                'quantity' => $qty, 'unit' => $unit, 'amount' => $amount,
            ];
            $min = $min === null || $date < $min ? $date : $min;
            $max = $max === null || $date > $max ? $date : $max;
        }
        return [$out, $invalid, 0, $min, $max];
    }
}
