<?php

namespace App\Console\Commands;

use App\Services\TallyGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs on the PC where Tally is open. Pulls Tally's whole stock-item master (one Collection export) and pushes it
 * as a full-table replace to /api/tally-push/sync-stock -> tally_stock_items. A snapshot, like the party /
 * outstanding syncs: run it occasionally. Read-only on Tally.
 */
class TallyStockPushCommand extends Command
{
    protected $signature = 'hrx:tally-push-stock
                            {--company=hitech : Which configured company to pull (config/services.php tally.companies)}
                            {--gateway-url= : Override services.tally.gateway_url}
                            {--gateway-company= : Override the company name resolved from --company}
                            {--target-url= : Override tally_push.outbound.target_url}
                            {--dry-run : Pull and map only; push nothing}';

    protected $description = 'Pull Tally\'s stock-item master (groups, categories, balances, GST) and push it to /api/tally-push/sync-stock';

    public function handle(): int
    {
        $targetBase = (string) ($this->option('target-url') ?: config('tally_push.outbound.target_url'));
        $dry = (bool) $this->option('dry-run');
        if (!$dry && $targetBase === '') {
            $this->error('TALLY_PUSH_TARGET_URL is not set.');
            return self::FAILURE;
        }
        if (!$dry && !TallyPushCommand::targetIsSafe($targetBase)) {
            $this->error('Refusing to send: the target must be https:// (plain http is only allowed to this machine itself). Got: ' . $targetBase);
            return self::FAILURE;
        }
        $targetUrl = str_replace('/tally-push/sync', '/tally-push/sync-stock', $targetBase);

        $companyCode = (string) $this->option('company');
        $companyName = TallyPushCommand::resolveCompanyName($companyCode, $this->option('gateway-company'));
        if ($companyName === null) {
            $this->error("Unknown --company \"{$companyCode}\": not in config/services.php tally.companies and no --gateway-company override given.");
            return self::FAILURE;
        }

        $gateway = new TallyGateway(
            (string) ($this->option('gateway-url') ?: config('services.tally.gateway_url')),
            $companyName,
            (int) config('services.tally.gateway_timeout', 30),
        );
        try {
            $gateway->assertCompany();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line("Pulling the stock-item master from {$gateway->baseUrl()} [{$companyCode}]...");
        $t = microtime(true);
        try {
            $items = $gateway->stockItems();
        } catch (\Throwable $e) {
            $this->error('Pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->line(sprintf('%d stock items in %.1fs.', count($items), microtime(true) - $t));

        $rows = [];
        foreach ($items as $i) {
            $rows[] = [
                'name' => $i['name'],
                'stock_group' => $i['group'] ?? null,
                'category' => $i['category'] ?? null,
                'base_unit' => $i['unit'] ?? null,
                'opening_qty' => $i['opening_qty'] ?? null,
                'opening_value' => $i['opening_value'] ?? null,
                'closing_qty' => $i['closing_qty'] ?? null,
                'closing_unit' => $i['closing_unit'] ?? null,
                'closing_value' => $i['closing_value'] ?? null,
                'closing_rate' => $i['closing_rate'] ?? null,
                'closing_rate_unit' => $i['closing_rate_unit'] ?? null,
                'gst_rate' => $this->gstRate($i['gst'] ?? null),
            ];
        }

        $withStock = count(array_filter($rows, fn ($r) => ($r['closing_qty'] ?? 0) != 0));
        $this->line(sprintf('%d items (%d with stock in hand).', count($rows), $withStock));

        if ($dry) {
            $this->line('Dry run: nothing pushed.');
            return self::SUCCESS;
        }
        if ($rows === []) {
            $this->warn('Nothing to push.');
            return self::SUCCESS;
        }

        [$ok, $msg] = $this->push($targetUrl, ['company' => $companyCode, 'stock_items' => $rows]);
        if (!$ok) {
            $this->error('Push failed: ' . $msg);
            return self::FAILURE;
        }
        $this->info($msg);
        return self::SUCCESS;
    }

    /** @param array{rates?:array<string,float>}|null $gst */
    private function gstRate(?array $gst): ?float
    {
        $rates = $gst['rates'] ?? [];
        if ($rates === []) {
            return null;
        }
        $igst = $rates['IGST'] ?? 0.0;
        if ($igst > 0) {
            return $igst;
        }
        $combined = ($rates['CGST'] ?? 0.0) + ($rates['SGST/UTGST'] ?? 0.0);
        return $combined > 0 ? $combined : null;
    }

    /** @return array{0:bool,1:string} */
    private function push(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $apiKey = (string) config('tally_push.outbound.api_key');
        $secret = (string) config('tally_push.outbound.secret');
        if ($apiKey === '' || $secret === '') {
            return [false, 'TALLY_PUSH_API_KEY / TALLY_PUSH_SECRET not set.'];
        }
        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders(TallyPushCommand::signedHeaders($apiKey, $secret, $body))
                ->connectTimeout(10)->timeout((int) config('tally_push.outbound.timeout', 120))
                ->post($url);
        } catch (\Throwable $e) {
            return [false, 'connection error: ' . $e->getMessage()];
        }
        if (!$response->successful()) {
            return [false, 'HTTP ' . $response->status() . ' ' . Str::limit($response->body(), 300)];
        }
        return [true, 'Pushed ' . ($response->json('count') ?? count($payload['stock_items'] ?? [])) . ' stock items.'];
    }
}
