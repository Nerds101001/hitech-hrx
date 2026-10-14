<?php

namespace App\Console\Commands;

use App\Services\TallyGateway;
use App\Support\NameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs on the PC where Tally is open. Pulls Tally's WHOLE ledger master (every account, not just customers the way
 * hrx:tally-push-parties is scoped) plus the group tree, resolves each ledger's primary group, and pushes the lot
 * as a full-table replace to /api/tally-push/sync-ledgers -> tally_ledgers. A snapshot, like the other masters.
 */
class TallyLedgerPushCommand extends Command
{
    protected $signature = 'hrx:tally-push-ledgers
                            {--company=hitech : Which configured company to pull (config/services.php tally.companies)}
                            {--gateway-url= : Override services.tally.gateway_url}
                            {--gateway-company= : Override the company name resolved from --company}
                            {--target-url= : Override tally_push.outbound.target_url}
                            {--dry-run : Pull and map only; push nothing}';

    protected $description = 'Pull Tally\'s full ledger master (every account: group, balance, GSTIN/PAN) and push it to /api/tally-push/sync-ledgers';

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
        $targetUrl = str_replace('/tally-push/sync', '/tally-push/sync-ledgers', $targetBase);

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

        $this->line("Pulling the ledger master from {$gateway->baseUrl()} [{$companyCode}]...");
        $t = microtime(true);
        try {
            $ledgers = $gateway->ledgerMaster();
            $groupParent = $gateway->groupMaster();
        } catch (\Throwable $e) {
            $this->error('Pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->line(sprintf('%d ledgers in %.1fs.', count($ledgers), microtime(true) - $t));

        $rows = [];
        foreach ($ledgers as $l) {
            [$primary] = TallyGateway::primaryGroupOf($l['group'] ?? null, $groupParent);
            $rows[] = [
                'name' => $l['name'],
                'normalized_name' => NameMatcher::normalize($l['name']) ?: null,
                'ledger_group' => $l['group'] ?? null,
                'primary_group' => $primary,
                'closing_balance' => $l['closing_balance'] ?? null,
                'credit_limit' => $l['credit_limit'] ?? null,
                'gstin' => $l['gstin'] ?? null,
                'pan' => $l['pan'] ?? null,
                'contact_person' => $l['contact_person'] ?? null,
                'contact_mobile' => $l['contact_mobile'] ?? null,
                'state' => $l['state'] ?? null,
                'country' => $l['country'] ?? null,
                'pincode' => $l['pincode'] ?? null,
                'mailing_name' => $l['mailing_name'] ?? null,
                'email' => $l['email'] ?? null,
                'address' => $l['address'] ?? null,
            ];
        }

        $byPrimary = [];
        foreach ($rows as $r) {
            $byPrimary[$r['primary_group'] ?: '(none)'] = ($byPrimary[$r['primary_group'] ?: '(none)'] ?? 0) + 1;
        }
        arsort($byPrimary);
        $this->line(count($rows) . ' ledgers. Top groups: ' . implode(', ', array_map(
            fn ($g, $n) => "{$g} ({$n})",
            array_slice(array_keys($byPrimary), 0, 5),
            array_slice(array_values($byPrimary), 0, 5)
        )));

        if ($dry) {
            $this->line('Dry run: nothing pushed.');
            return self::SUCCESS;
        }
        if ($rows === []) {
            $this->warn('Nothing to push.');
            return self::SUCCESS;
        }

        [$ok, $msg] = $this->push($targetUrl, ['company' => $companyCode, 'ledgers' => $rows]);
        if (!$ok) {
            $this->error('Push failed: ' . $msg);
            return self::FAILURE;
        }
        $this->info($msg);
        return self::SUCCESS;
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
        return [true, 'Pushed ' . ($response->json('count') ?? count($payload['ledgers'] ?? [])) . ' ledgers.'];
    }
}
