<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs on the PC where Tally is open (the office PC), on a short schedule. The live server can't reach Tally, so
 * a backfill requested from the Tally Push admin page is only RECORDED there as a job; this command claims the
 * oldest waiting one over the signed API, runs hrx:tally-push for its date range, and reports the result back so
 * the page can show the progress.
 *
 * It drains the whole queue in one invocation (so "whole year so far" -- seven months, seven jobs -- all run from
 * a single scheduled tick), capped by --max-jobs as a safety stop. The claim/report calls are signed exactly like
 * the push itself (TallyPushCommand::signedHeaders), against the same credential and allowlist.
 */
class TallyJobsPollCommand extends Command
{
    protected $signature = 'hrx:tally-jobs-poll
                            {--target-url= : Override tally_push.outbound.target_url (the .../api/tally-push/sync endpoint)}
                            {--gateway-url= : Passed through to hrx:tally-push}
                            {--gateway-company= : Passed through to hrx:tally-push}
                            {--max-jobs=20 : Stop after running this many jobs in one invocation}';

    protected $description = 'Claim backfill jobs queued from the Tally Push admin page and run hrx:tally-push for each';

    public function handle(): int
    {
        $syncUrl = (string) ($this->option('target-url') ?: config('tally_push.outbound.target_url'));
        if ($syncUrl === '') {
            $this->error('TALLY_PUSH_TARGET_URL is not set (config/tally_push.php outbound.target_url). Nothing to poll.');
            return self::FAILURE;
        }
        if (!TallyPushCommand::targetIsSafe($syncUrl)) {
            $this->error('Refusing to talk to an insecure target (must be https, or http only to this machine): ' . $syncUrl);
            return self::FAILURE;
        }

        $apiKey = (string) config('tally_push.outbound.api_key');
        $secret = (string) config('tally_push.outbound.secret');
        if ($apiKey === '' || $secret === '') {
            $this->error('TALLY_PUSH_API_KEY / TALLY_PUSH_SECRET not set. The office PC can\'t authenticate to the live server.');
            return self::FAILURE;
        }

        // The job endpoints sit next to /sync: .../api/tally-push/sync -> .../api/tally-push/jobs/...
        $base = preg_replace('#/sync$#', '', $syncUrl);
        $claimUrl = $base . '/jobs/claim';

        $max = max(1, (int) $this->option('max-jobs'));
        $ran = 0;

        for ($i = 0; $i < $max; $i++) {
            $claim = $this->signedPost($claimUrl, '', $apiKey, $secret);
            if ($claim === null) {
                return $ran > 0 ? self::SUCCESS : self::FAILURE;   // a transport/auth error was already printed
            }
            $job = $claim['job'] ?? null;
            if (!$job) {
                if ($ran === 0) {
                    $this->line('No jobs waiting.');
                }
                break;
            }

            $ran++;
            $this->runJob($base, $job, $apiKey, $secret);
        }

        if ($ran > 0) {
            $this->info("Ran {$ran} job(s).");
        }
        return self::SUCCESS;
    }

    /** Claim -> run the matching artisan sync -> report done/failed with what it pushed. */
    private function runJob(string $base, array $job, string $apiKey, string $secret): void
    {
        $id = (int) $job['id'];
        $kind = (string) ($job['kind'] ?? 'backfill');
        $label = (string) ($job['label'] ?? $kind);
        $updateUrl = $base . '/jobs/' . $id;

        // Gateway overrides, passed through to whichever command runs.
        $gw = [];
        if ($this->option('gateway-url')) {
            $gw['--gateway-url'] = $this->option('gateway-url');
        }
        if ($this->option('gateway-company')) {
            $gw['--gateway-company'] = $this->option('gateway-company');
        }

        if ($kind === 'outstanding') {
            $command = 'hrx:tally-push-outstanding';
            $args = $gw;
            $this->line("Job #{$id} {$label}: outstanding refresh");
        } elseif ($kind === 'parties') {
            $command = 'hrx:tally-push-parties';
            $args = $gw;
            $this->line("Job #{$id} {$label}: customer-ledger refresh");
        } else {
            $command = 'hrx:tally-push';
            $from = (string) $job['from'];
            $to = (string) $job['to'];
            $args = $gw + ['--from' => $from, '--to' => $to, '--emit-totals' => true];
            $this->line("Job #{$id} {$label}: {$from} -> {$to}");
        }

        $this->signedPost($updateUrl, json_encode(['status' => 'running']), $apiKey, $secret);

        $output = null;
        try {
            $exit = Artisan::call($command, $args);
        } catch (\Throwable $e) {
            $exit = 1;
            $output = 'exception: ' . $e->getMessage();
        }
        $output = $output ?? Artisan::output();
        $this->line(trim($output));

        $totals = $this->parseTotals($output);

        if ($exit === 0) {
            $this->signedPost($updateUrl, json_encode(array_merge([
                'status' => 'done',
                'message' => $this->doneMessage($kind, $label, $totals, $output),
            ], $totals)), $apiKey, $secret);
        } else {
            // Keep the tail of the output -- the command prints the reason it stopped on its last lines.
            $message = Str::limit(trim(preg_replace('/\s+/', ' ', (string) $output)), 1900, ' ...');
            $this->signedPost($updateUrl, json_encode(array_merge([
                'status' => 'failed',
                'message' => $message !== '' ? $message : "{$command} exited non-zero.",
            ], $totals)), $apiKey, $secret);
        }
    }

    /** A human one-liner for a finished job. Backfill has structured totals; the snapshots print "Pushed N ...". */
    private function doneMessage(string $kind, string $label, array $totals, string $output): string
    {
        if ($kind === 'backfill') {
            return sprintf('Synced %s: %d invoices, %d orders, %d deliveries, %d receipts.',
                $label, $totals['invoices'] ?? 0, $totals['sale_orders'] ?? 0, $totals['deliveries'] ?? 0, $totals['receipts'] ?? 0);
        }
        // "Pushed 10739 outstanding bills." / "Pushed 1939 parties."
        return preg_match('/Pushed[^.\n]*/', $output, $m) ? trim($m[0]) . '.' : 'Done.';
    }

    /** Pull the "TALLY_TOTALS {json}" line out of hrx:tally-push's output. @return array<string,int> */
    private function parseTotals(string $output): array
    {
        if (preg_match('/TALLY_TOTALS\s+(\{.*\})/', $output, $m)) {
            $parsed = json_decode($m[1], true);
            if (is_array($parsed)) {
                return array_map('intval', $parsed);
            }
        }
        return [];
    }

    /**
     * A signed POST to a job endpoint. Returns the decoded JSON on success, or null on a transport / non-2xx error
     * (after printing it). Body is signed byte-for-byte, so it is sent exactly as given here.
     *
     * @return array<string,mixed>|null
     */
    private function signedPost(string $url, string $body, string $apiKey, string $secret): ?array
    {
        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders(TallyPushCommand::signedHeaders($apiKey, $secret, $body))
                ->connectTimeout(10)
                ->timeout(30)
                ->post($url);
        } catch (\Throwable $e) {
            $this->error('  connection error talking to the live server: ' . $e->getMessage());
            return null;
        }

        if (!$response->successful()) {
            $this->error('  live server refused: HTTP ' . $response->status() . ' ' . Str::limit($response->body(), 200));
            return null;
        }

        return (array) $response->json();
    }
}
