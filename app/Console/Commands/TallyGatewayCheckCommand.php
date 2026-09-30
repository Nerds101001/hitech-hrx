<?php

namespace App\Console\Commands;

use App\Exceptions\TallyGatewayException;
use App\Services\TallyGateway;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Proves the Tally gateway integration end to end WITHOUT using the data: it reaches Tally, confirms the
 * configured company is the loaded one, and (with --from) pulls a few days of sales vouchers and prints what
 * was understood. It writes nothing -- not to the database, not to disk -- so it is safe against a sandbox
 * or against the real books.
 */
class TallyGatewayCheckCommand extends Command
{
    protected $signature = 'hrx:tally-gateway-check
                            {--url= : Gateway address (default: services.tally.gateway_url)}
                            {--company= : The one company allowed to be read (default: services.tally.gateway_company)}
                            {--items : List the inventory (every stock item, with stock in hand and GST rates)}
                            {--from= : First day to pull, YYYY-MM-DD (omit to only test the connection)}
                            {--to= : Last day to pull (default: same as --from)}';

    protected $description = 'Read-only check of the Tally XML gateway: connection, company guard, and a parsed sample of sales vouchers. Writes nothing.';

    /** More than this many days in one check is refused: it is one request per day, and Tally is single-threaded. */
    private const MAX_DAYS = 31;

    public function handle(): int
    {
        $gateway = new TallyGateway(
            (string) ($this->option('url') ?: config('services.tally.gateway_url')),
            (string) ($this->option('company') ?: config('services.tally.gateway_company')),
            (int) config('services.tally.gateway_timeout', 30),
        );
        $this->line("Gateway: <info>{$gateway->baseUrl()}</info>   allowed company: <info>" . ($gateway->expectedCompany() ?: '(none configured)') . '</info>');

        try {
            $t = microtime(true);
            $loaded = $gateway->companies();
            $this->line(sprintf('1. Reached Tally in %.0f ms. Companies loaded: %s', (microtime(true) - $t) * 1000, $loaded ? '"' . implode('", "', $loaded) . '"' : 'none'));

            $gateway->assertCompany();
            $this->line('2. Company guard: <info>OK</info> — the allowed company is the one loaded.');

            if ($this->option('items')) {
                $t = microtime(true);
                $items = $gateway->stockItems();
                $this->line(sprintf('3. Inventory read in %.0f ms: %d stock item(s).', (microtime(true) - $t) * 1000, count($items)));
                $this->listItems($items);
                $this->newLine();
                $this->line('Nothing was written: no database rows, no files.');
                return self::SUCCESS;
            }

            $from = $this->option('from');
            if (!$from) {
                $this->line('3. Data pull skipped (no --from). Nothing was written.');
                return self::SUCCESS;
            }
            $start = Carbon::createFromFormat('!Y-m-d', $from);
            $end = Carbon::createFromFormat('!Y-m-d', $this->option('to') ?: $from);
            if (!$start || !$end || $end->lt($start)) {
                $this->error('--from/--to must be valid dates (YYYY-MM-DD) with --to on or after --from.');
                return self::FAILURE;
            }
            if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
                $this->error('Refusing more than ' . self::MAX_DAYS . ' days in one check: it makes one request per day.');
                return self::FAILURE;
            }

            $this->line('3. Pulling the Day Book, one day per request (the same safe method hrx:tally-push uses -- never the filtered Voucher collection, which is proven to strain a real company):');
            $result = $gateway->dayBookRange($start, $end, function ($day, $new, $secs) {
                $this->line(sprintf('     %s  %3d voucher(s)  %5.0f ms', $day->format('D d M Y'), $new, $secs * 1000));
            });
            $vouchers = $result['vouchers'];
            if ($result['stoppedAt'] !== null) {
                $this->warn("Stopped early at {$result['stoppedAt']} (a day took too long) -- summary below covers only what was pulled before that.");
            }
        } catch (TallyGatewayException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->summarise($vouchers);
        $this->newLine();
        $this->line('Nothing was written: no database rows, no files.');
        return self::SUCCESS;
    }

    private function listItems(array $items): void
    {
        usort($items, fn($a, $b) => strcasecmp((string) $a['group'], (string) $b['group']) ?: strcasecmp($a['name'], $b['name']));
        $fmt = fn($n) => $n === null ? '—' : rtrim(rtrim(number_format($n, 3, '.', ','), '0'), '.');
        $this->table(['#', 'Item', 'Group', 'Unit', 'In stock', 'Value (₹)', 'Rate (₹)', 'GST %'], array_map(fn($i, $n) => [
            $n + 1, $i['name'], $i['group'] ?? '—', $i['unit'] ?? '—',
            $i['closing_qty'] === null ? '—' : $fmt($i['closing_qty']) . ' ' . $i['closing_unit'],
            $i['closing_value'] === null || $i['closing_value'] == 0 ? '—' : number_format($i['closing_value'], 2),
            $i['closing_rate'] === null ? '—' : number_format($i['closing_rate'], 2),
            $i['gst'] && $i['gst']['rates'] ? implode(' ', array_map(fn($h, $r) => "{$h} {$r}", array_keys($i['gst']['rates']), $i['gst']['rates'])) : '—',
        ], $items, array_keys($items)));

        $withStock = array_filter($items, fn($i) => $i['closing_qty'] !== null && $i['closing_qty'] != 0);
        $negative = array_filter($items, fn($i) => $i['closing_qty'] !== null && $i['closing_qty'] < 0);
        $this->line(sprintf('%d item(s); %d with stock movement (%d of those show NEGATIVE stock: sold more than was received in Tally); stock value %s.',
            count($items), count($withStock), count($negative), number_format(array_sum(array_map(fn($i) => $i['closing_value'] ?? 0, $items)), 2)));
    }

    private function summarise(array $vouchers): void
    {
        $withItems = array_values(array_filter($vouchers, fn($v) => $v['lines']));
        $lines = array_sum(array_map(fn($v) => count($v['lines']), $vouchers));
        $noTotal = count(array_filter($vouchers, fn($v) => $v['total'] === null));
        $this->newLine();
        $this->line(sprintf('Understood %d voucher(s): %d with item lines (%d lines in all), %d cancelled, %d without a readable party total.',
            count($vouchers), count($withItems), $lines, count(array_filter($vouchers, fn($v) => $v['cancelled'])), $noTotal));

        $withGst = count(array_filter($vouchers, fn($v) => array_filter($v['lines'], fn($l) => array_sum($l['gst']) > 0)));
        $this->line("GST: {$withGst} voucher(s) have a non-zero GST rate on at least one item line.");

        $sample = $withItems[0] ?? null;
        if (!$sample) {
            return;
        }
        $this->newLine();
        $this->line(sprintf('Sample: %s %s dated %s for "%s" — total %s', $sample['type'], $sample['number'], $sample['date'], $sample['party'], $sample['total'] === null ? '?' : number_format($sample['total'], 2)));
        $this->table(['Item', 'Qty', 'Unit', 'Rate', 'Per', 'Amount', 'HSN', 'GST %'], array_map(fn($l) => [
            $l['item'], $l['qty'], $l['unit'], $l['rate'], $l['rate_unit'], $l['amount'], $l['hsn'] ?? '—',
            $l['gst'] ? implode(' ', array_map(fn($h, $r) => "{$h} {$r}", array_keys($l['gst']), $l['gst'])) : '—',
        ], $sample['lines']));
    }
}
