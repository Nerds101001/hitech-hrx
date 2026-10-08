<?php

namespace App\Console\Commands;

use App\Services\TallyGateway;
use App\Support\NameMatcher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs on the PC where Tally is open (this one). Pulls Tally's bill-wise outstanding (the Bills collection) plus
 * the ledger master, keeps only bills that belong to a real customer (same filter as hrx:tally-push-parties),
 * attaches each bill's ledger group, and pushes the lot as a full-table replace to /api/tally-push/sync-outstanding.
 *
 * This is what powers the Sales Sources "Outstanding" and "Finance" (Total Due / ageing) screens on the live
 * server -- data the daily voucher push can't carry, because bill-wise receivables are a report Tally computes,
 * not something stored on a voucher. A snapshot, not transactional: run it occasionally (daily/weekly), like the
 * party sync. Read-only on Tally.
 */
class TallyOutstandingPushCommand extends Command
{
    protected $signature = 'hrx:tally-push-outstanding
                            {--gateway-url= : Override services.tally.gateway_url}
                            {--gateway-company= : Override services.tally.gateway_company}
                            {--target-url= : Override tally_push.outbound.target_url}
                            {--dry-run : Pull and map only; push nothing}';

    protected $description = 'Pull Tally\'s bill-wise outstanding (customer receivables) and push it to /api/tally-push/sync-outstanding';

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
        $targetUrl = str_replace('/tally-push/sync', '/tally-push/sync-outstanding', $targetBase);

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

        // 1. Ledger master: a bill's PARENT is a ledger name, so this gives each bill its GROUP (so the page can
        //    show "Sundry Debtors") and, with tally_invoices, lets us keep only real customers.
        $this->line("Pulling the ledger master from {$gateway->baseUrl()}...");
        try {
            $ledgers = $gateway->ledgerMaster();
        } catch (\Throwable $e) {
            $this->error('Ledger pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $groupByLedger = [];
        foreach ($ledgers as $l) {
            $groupByLedger[$l['name']] = $l['group'];
        }
        $this->line(count($ledgers) . ' ledgers.');

        // The group tree, so a ledger's immediate sub-group (VADODARA, HYDERABAAD, ...) resolves to its reporting
        // bucket. Walk up to the reserved "Sundry Debtors" / "Sundry Creditors" group; Finance filters on exactly
        // that name, and this company files customers in many sub-groups beneath it.
        try {
            $groupParent = $gateway->groupMaster();
        } catch (\Throwable $e) {
            $this->error('Group pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $resolve = function (?string $immediate) use ($groupParent): array {
            $cur = $immediate;
            $seen = [];
            $top = $immediate;
            while (is_string($cur) && $cur !== '' && !isset($seen[$cur])) {
                if ($cur === 'Sundry Debtors') {
                    return ['Sundry Debtors', false];       // asset-nature receivable
                }
                if ($cur === 'Sundry Creditors') {
                    return ['Sundry Creditors', true];       // liability-nature payable
                }
                $seen[$cur] = true;
                $top = $cur;
                $parent = trim($groupParent[$cur] ?? '');
                if ($parent === '' || strcasecmp($parent, 'Primary') === 0) {
                    break;
                }
                $cur = $parent;
            }
            // Neither Sundry group in the chain: keep the top-of-chain group, classify by its known nature.
            $liabilities = ['Current Liabilities', 'Loans (Liability)', 'Capital Account', 'Suspense A/c', 'Branch / Divisions'];
            return [$top, in_array($top, $liabilities, true)];
        };

        $realCustomers = DB::table('tally_invoices')->where('is_branch_transfer', 0)
            ->whereNotNull('normalized_name')->distinct()->pluck('normalized_name')->flip();

        // 2. Bills (the heavy one -- the whole company's bill-wise outstanding in a single export).
        $this->line('Pulling bill-wise outstanding...');
        $t = microtime(true);
        try {
            $bills = $gateway->billsOutstanding();
        } catch (\Throwable $e) {
            $this->error('Bills pull failed: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->line(sprintf('%d open bills in %.1fs.', count($bills), microtime(true) - $t));

        // 3. Map to the tally_outstanding shape, scoped to real customers. Tally reports a receivable as a Dr
        //    (negative) balance; the screens read a debtor who owes us as POSITIVE, so the sign is flipped (an
        //    advance/credit balance then becomes negative, exactly the file import's "(-) opposite side" rule).
        $rows = [];
        $skipped = 0;
        foreach ($bills as $b) {
            $norm = NameMatcher::normalize($b['party_name']);
            if ($norm === '' || !$realCustomers->has($norm)) {
                $skipped++;
                continue;
            }
            $credit = null;
            if ($b['bill_date'] && $b['due_date']) {
                $d = Carbon::parse($b['bill_date'])->diffInDays(Carbon::parse($b['due_date']), false);
                $credit = ($d >= 0 && $d <= 65535) ? $d : null;
            }
            [$group, $isLiability] = $resolve($groupByLedger[$b['party_name']] ?? null);
            // Tally reports a Dr balance as negative. A debtor (asset) who owes us should read POSITIVE, so flip;
            // a creditor's Cr balance is already positive and means "we owe them", so keep it.
            $amount = $isLiability ? $b['closing_balance'] : -$b['closing_balance'];
            $rows[] = [
                'bill_ref' => $b['bill_ref'],
                'bill_date' => $b['bill_date'],
                'party_name' => $b['party_name'],
                'normalized_name' => $norm,
                'ledger_group' => $group,
                'amount' => round($amount, 2),
                'credit_days' => $credit,
            ];
        }

        $byGroup = [];
        foreach ($rows as $r) {
            $g = $r['ledger_group'] ?: '(no group)';
            $byGroup[$g]['c'] = ($byGroup[$g]['c'] ?? 0) + 1;
            $byGroup[$g]['amt'] = ($byGroup[$g]['amt'] ?? 0) + $r['amount'];
        }
        arsort($byGroup);
        $this->line(sprintf('%d customer bills kept (%d non-customer bills skipped). By ledger group:', count($rows), $skipped));
        foreach ($byGroup as $g => $v) {
            $this->line(sprintf('   %-26s %6d  %s', Str::limit($g, 26), $v['c'], number_format($v['amt'], 0)));
        }

        if ($dry) {
            $this->line('Dry run: nothing pushed.');
            return self::SUCCESS;
        }
        if ($rows === []) {
            $this->warn('Nothing to push.');
            return self::SUCCESS;
        }

        $body = json_encode(['outstanding' => $rows], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->line(sprintf('Pushing %s of outstanding...', $this->humanBytes(strlen($body))));
        $apiKey = (string) config('tally_push.outbound.api_key');
        $secret = (string) config('tally_push.outbound.secret');
        if ($apiKey === '' || $secret === '') {
            $this->error('TALLY_PUSH_API_KEY / TALLY_PUSH_SECRET not set.');
            return self::FAILURE;
        }

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders(TallyPushCommand::signedHeaders($apiKey, $secret, $body))
                ->connectTimeout(10)->timeout((int) config('tally_push.outbound.timeout', 120))
                ->post($targetUrl);
        } catch (\Throwable $e) {
            $this->error('Push failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('Push failed: HTTP ' . $response->status() . ' ' . Str::limit($response->body(), 300));
            return self::FAILURE;
        }
        $this->info('Pushed ' . ($response->json('count') ?? count($rows)) . ' outstanding bills.');
        return self::SUCCESS;
    }

    private function humanBytes(int $n): string
    {
        return $n >= 1048576 ? round($n / 1048576, 1) . ' MB' : round($n / 1024, 1) . ' KB';
    }
}
