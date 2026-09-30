<?php

namespace App\Console\Commands;

use App\Services\TallyGateway;
use App\Support\NameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs on the PC where Tally is open (this one). ONE request pulls Tally's whole ledger master (credit limit,
 * GSTIN, PAN, contact person/mobile) -- proven ~4-5s regardless of company size, since it's a plain Collection
 * export, nothing filtered or scanned per-voucher. Filtered here (not in Tally, since a CHILDOF filter was
 * tried and found not to restrict correctly) to just the customers already seen in tally_invoices, then pushed
 * as a full-table replace to /api/tally-push/sync-parties.
 *
 * A master sync, not a transactional one: run it occasionally (weekly is plenty -- credit limits don't change
 * daily), separately from hrx:tally-push's daily voucher pulls.
 */
class TallyPartyPushCommand extends Command
{
    protected $signature = 'hrx:tally-push-parties
                            {--gateway-url= : Override services.tally.gateway_url}
                            {--gateway-company= : Override services.tally.gateway_company}
                            {--target-url= : Override tally_push.outbound.target_url}
                            {--dry-run : Pull and map only; push nothing}';

    protected $description = 'Pull Tally\'s ledger master (credit limit, GSTIN, PAN, contact) and push it to /api/tally-push/sync-parties';

    public function handle(): int
    {
        $targetBase = (string) ($this->option('target-url') ?: config('tally_push.outbound.target_url'));
        $dry = (bool) $this->option('dry-run');
        if (!$dry && $targetBase === '') {
            $this->error('TALLY_PUSH_TARGET_URL is not set.');
            return self::FAILURE;
        }
        // sync-parties sits next to sync on the same endpoint path.
        $targetUrl = str_replace('/tally-push/sync', '/tally-push/sync-parties', $targetBase);

        $gateway = new TallyGateway(
            (string) ($this->option('gateway-url') ?: config('services.tally.gateway_url')),
            (string) ($this->option('gateway-company') ?: config('services.tally.gateway_company')),
            (int) config('services.tally.gateway_timeout', 30),
        );
        try {
            $gateway->assertCompany();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line("Pulling the ledger master from {$gateway->baseUrl()}...");
        $t = microtime(true);
        try {
            $ledgers = $gateway->ledgerMaster();
        } catch (\Throwable $e) {
            $this->error('Pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->line(sprintf('%d ledgers in %.1fs.', count($ledgers), microtime(true) - $t));

        // Only ledgers that have actually invoiced us -- everything else (bank/expense/tax heads, one-off
        // parties, the company's own branches) is real Tally data but not something a customer screen needs.
        $realCustomerNames = DB::table('tally_invoices')->where('is_branch_transfer', 0)
            ->whereNotNull('normalized_name')->distinct()->pluck('normalized_name')->flip();

        $parties = [];
        foreach ($ledgers as $l) {
            $norm = NameMatcher::normalize($l['name']);
            if ($norm === '' || !$realCustomerNames->has($norm)) {
                continue;
            }
            // A normalized name can collide across two distinct Tally ledgers (e.g. two branches of the same
            // group); keep the one with the most identifying data filled in rather than an arbitrary last-wins.
            $score = (int) ($l['credit_limit'] !== null) + (int) ($l['gstin'] !== null) + (int) ($l['contact_person'] !== null);
            if (isset($parties[$norm]) && $parties[$norm]['_score'] >= $score) {
                continue;
            }
            $parties[$norm] = [
                'normalized_name' => $norm, 'party_name' => $l['name'], 'ledger_group' => $l['group'],
                'credit_limit' => $l['credit_limit'], 'gstin' => $l['gstin'], 'pan' => $l['pan'],
                'contact_person' => $l['contact_person'], 'contact_mobile' => $l['contact_mobile'], '_score' => $score,
            ];
        }
        foreach ($parties as &$p) {
            unset($p['_score']);
        }
        unset($p);
        $parties = array_values($parties);

        $withCredit = count(array_filter($parties, fn ($p) => $p['credit_limit'] !== null));
        $withGstin = count(array_filter($parties, fn ($p) => $p['gstin'] !== null));
        $this->line(sprintf('%d match a real customer (of %d we\'ve ever invoiced): %d with a credit limit set, %d with a GSTIN.',
            count($parties), $realCustomerNames->count(), $withCredit, $withGstin));

        if ($dry) {
            $this->line('Dry run: nothing pushed.');
            return self::SUCCESS;
        }
        if ($parties === []) {
            $this->warn('Nothing to push.');
            return self::SUCCESS;
        }

        $body = json_encode(['parties' => $parties], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $nonce = Str::random(32);
        $secret = (string) config('tally_push.outbound.secret');
        $apiKey = (string) config('tally_push.outbound.api_key');
        if ($apiKey === '' || $secret === '') {
            $this->error('TALLY_PUSH_API_KEY / TALLY_PUSH_SECRET not set.');
            return self::FAILURE;
        }
        $signature = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey, 'X-HRX-Timestamp' => $timestamp,
                    'X-HRX-Nonce' => $nonce, 'X-HRX-Signature' => 'sha256=' . $signature,
                ])
                ->connectTimeout(10)->timeout((int) config('tally_push.outbound.timeout', 60))
                ->post($targetUrl);
        } catch (\Throwable $e) {
            $this->error('Push failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('Push failed: HTTP ' . $response->status() . ' ' . Str::limit($response->body(), 300));
            return self::FAILURE;
        }
        $this->info('Pushed ' . ($response->json('count') ?? count($parties)) . ' parties.');
        return self::SUCCESS;
    }
}
