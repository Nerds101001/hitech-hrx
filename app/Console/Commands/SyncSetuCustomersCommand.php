<?php

namespace App\Console\Commands;

use App\Models\SetuCustomer;
use App\Support\NameMatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes the local setu_customers cache from a single unfiltered Setu
 * pull (no Salesperson filter — the per-salesperson filter needs a Tally
 * ledger name mapping this install doesn't have yet; see
 * SetuMappingReportCommand). Setu has no per-customer lookup endpoint, so
 * this cache is what the mapping UI's search/preview reads from instead of
 * hitting Setu live on every keystroke.
 *
 * Rupees-per-Lakh conversion matches SetuReportService::RUPEES_PER_LAKH.
 */
class SyncSetuCustomersCommand extends Command
{
    protected $signature = 'hrx:sync-setu-customers {--months=3 : How many trailing calendar months to cache per customer}';
    protected $description = 'Pull the full Setu (Tally) customer ledger and cache it locally for the mapping UI';

    private const RUPEES_PER_LAKH = 100000;

    public function handle(): int
    {
        // The unfiltered ledger is tens of MB of JSON and decodes into far more than the 512M CLI default; at that size PHP
        // dies with exit code 255 and prints nothing, so the sync used to fail without saying why.
        @ini_set('memory_limit', '2048M');

        $baseUrl = rtrim((string) config('services.setu.base_url', ''), '/');
        $token = config('services.setu.access_token');
        $reportId = (int) config('services.setu.report_id', 2899);

        if (!$token) {
            $this->error('SETU_ACCESS_TOKEN is not configured.');
            return Command::FAILURE;
        }

        $this->info('Fetching the full Setu ledger (unfiltered) — this pulls tens of MB, give it a minute...');
        $response = Http::withToken($token)->timeout(120)->post("{$baseUrl}/setu", [
            'report_id' => $reportId,
            'from_date' => now()->startOfMonth()->toDateString(),
            'to_date' => now()->toDateString(),
            'setu_report_type' => 'my_report',
            'global_filter' => ['pos' => [], 'neg' => []],
        ]);

        if (!$response->successful()) {
            $this->error("Setu request failed: HTTP {$response->status()}");
            return Command::FAILURE;
        }

        $json = json_decode($response->body(), true);
        unset($response);                     // the raw body is no longer needed once decoded
        $rawData = $json['data']['data'] ?? null;
        if ($rawData === null) {
            $this->error('Unexpected Setu response shape.');
            return Command::FAILURE;
        }
        $this->info('Raw ledger entries: ' . count($rawData));

        $monthCount = max(1, (int) $this->option('months'));
        $wantedMonths = [];
        for ($i = 0; $i < $monthCount; $i++) {
            $wantedMonths[] = now()->copy()->subMonths($i)->format('Y-m');
        }

        $now = now();
        $cached = 0;
        foreach ($rawData as $name => $txnTypes) {
            $sales = $txnTypes['Sales'] ?? null;
            if (!$sales) {
                continue;
            }
            // Sales is keyed by Setu's fiscal-year label, then FY-relative
            // month (4..12,1..3) — flatten to calendar "YYYY-MM" so it can be
            // compared against $wantedMonths directly.
            $recentMonths = [];
            $total = 0.0;
            foreach ($sales as $fyLabel => $months) {
                if (!preg_match('/^FY-(\d{4})-(\d{4})$/', $fyLabel, $m)) {
                    continue;
                }
                $startYear = (int) $m[1];
                foreach ($months as $monthNum => $figures) {
                    $monthNum = (int) $monthNum;
                    $calendarYear = $monthNum >= 4 ? $startYear : $startYear + 1;
                    $monthYear = sprintf('%04d-%02d', $calendarYear, $monthNum);
                    $amount = round((float) ($figures['tptl'] ?? 0) / self::RUPEES_PER_LAKH, 3);
                    $total += $amount;
                    if (in_array($monthYear, $wantedMonths, true) && $amount != 0.0) {
                        $recentMonths[$monthYear] = $amount;
                    }
                }
            }
            if ($total <= 0) {
                continue; // not a real sales customer — a supplier/expense ledger etc.
            }
            $norm = NameMatcher::normalize($name);
            if ($norm === '') {
                continue;
            }
            ksort($recentMonths);

            SetuCustomer::updateOrCreate(
                ['normalized_name' => $norm],
                [
                    'name' => $name,
                    'recent_months' => $recentMonths,
                    'recent_total' => round(array_sum($recentMonths), 3),
                    'last_synced_at' => $now,
                ]
            );
            $cached++;
        }

        $this->info("Cached {$cached} Setu customer(s) with real sales. (peak memory " . round(memory_get_peak_usage(true) / 1048576) . ' MB)');
        return Command::SUCCESS;
    }
}
