<?php

namespace App\Console\Commands;

use App\Services\TallyGateway;
use App\Support\NameMatcher;
use App\Support\ProductGroup;
use App\Support\TallyFileReader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Runs ONLY on the PC where Tally itself is open (this one, currently). Pulls each day's Day Book from Tally's
 * XML gateway via App\Services\TallyGateway::dayBook() -- never the filtered Voucher collection
 * (TallyGateway::salesVouchers()), which is proven to time out and strain the real company -- and pushes it,
 * one day at a time, to this app's own /api/tally-push/sync endpoint (locally while testing, or the live
 * server once config/tally_push.php's outbound.target_url points there).
 *
 * One day is pushed as soon as it's pulled, rather than gathering a whole range and sending it in one giant
 * request: a mid-run failure then only leaves the remaining days to redo, not everything already sent, and no
 * single request risks the body-size cap.
 *
 * Known gaps versus the daily-FILE import (ImportTallyFilesCommand), left as such rather than guessed at:
 *   - salesperson, is_branch_transfer: Tally's XML gateway voucher doesn't carry a sales-attribution field the
 *     way the file export's own column does; left null/false here.
 *   - party_code, city, due_date/credit_terms on sale_lines: not present on a plain voucher pull either.
 *   - tally_sale_orders.status (Ok / Make and Hold / Despatch and Hold / ...): Tally computes this as a report,
 *     not a stored voucher field -- confirmed by inspecting a live Sales Order voucher's raw XML field-by-field.
 * What this path adds that the file import cannot: HSN + GST rate on essentially every invoice line (the file
 * export's SaleReportCRM.txt only covers roughly a quarter of invoices), the customer's GSTIN and place of
 * supply (neither in any file export), and Receipt vouchers -- actual customer payments (tally_receipts),
 * which the file import never carried at all.
 */
class TallyPushCommand extends Command
{
    protected $signature = 'hrx:tally-push
                            {--date= : One day to pull and push, YYYY-MM-DD (default: today)}
                            {--from= : First day of a range instead of --date}
                            {--to= : Last day of a range (default: same as --from)}
                            {--gateway-url= : Override services.tally.gateway_url (for testing against the sandbox)}
                            {--gateway-company= : Override services.tally.gateway_company}
                            {--target-url= : Override tally_push.outbound.target_url (e.g. to push to localhost while testing)}
                            {--dry-run : Pull and map only; push nothing, write nothing}';

    protected $description = 'Pull sales invoices and sale orders from Tally\'s XML gateway and push them to /api/tally-push/sync, one day at a time';

    /** More than this many days in one run is refused: it is one request per day, and Tally is single-threaded. */
    private const MAX_DAYS = 31;

    public function handle(): int
    {
        [$start, $end] = $this->resolveRange();
        if ($start === null) {
            return self::FAILURE;
        }

        $targetUrl = (string) ($this->option('target-url') ?: config('tally_push.outbound.target_url'));
        $dry = (bool) $this->option('dry-run');
        if (!$dry && $targetUrl === '') {
            $this->error('TALLY_PUSH_TARGET_URL is not set (config/tally_push.php outbound.target_url). Nothing to push to.');
            return self::FAILURE;
        }

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
        $this->line("Gateway: {$gateway->baseUrl()}   company: {$gateway->expectedCompany()}" . ($dry ? '   (dry run: nothing pushed)' : "   target: {$targetUrl}"));

        $totals = ['invoices' => 0, 'invoice_lines' => 0, 'sale_lines' => 0, 'sale_orders' => 0, 'deliveries' => 0, 'receipts' => 0];
        for ($day = $start->copy(); $day->lte($end); $day = $day->copy()->addDay()) {
            $t = microtime(true);
            try {
                $vouchers = $gateway->dayBook($day);
            } catch (\Throwable $e) {
                $this->error("{$day->format('Y-m-d')}: FAILED -- {$e->getMessage()} -- stopping here.");
                return self::FAILURE;
            }
            $secs = microtime(true) - $t;

            $payload = $this->mapDay($day, $vouchers);
            $counts = array_map('count', $payload);
            $payload = array_filter($payload);

            if ($dry) {
                $this->line(sprintf('%s  %5.1fs  vouchers=%-4d invoices=%-3d invoice_lines=%-4d sale_lines=%-4d sale_orders=%-4d deliveries=%-4d receipts=%-3d  (dry run)',
                    $day->format('Y-m-d D'), $secs, count($vouchers), $counts['invoices'], $counts['invoice_lines'], $counts['sale_lines'], $counts['sale_orders'], $counts['deliveries'], $counts['receipts']));
            } elseif ($payload === []) {
                $this->line(sprintf('%s  %5.1fs  nothing to push', $day->format('Y-m-d D'), $secs));
            } else {
                [$ok, $message] = $this->push($targetUrl, $payload);
                if (!$ok) {
                    $this->error("{$day->format('Y-m-d')}: push failed -- {$message} -- stopping here. Earlier days already pushed are unaffected.");
                    return self::FAILURE;
                }
                foreach ($counts as $k => $n) {
                    $totals[$k] += $n;
                }
                $this->info(sprintf('%s  %5.1fs  invoices=%-3d invoice_lines=%-4d sale_lines=%-4d sale_orders=%-4d deliveries=%-4d receipts=%-3d  -> %s',
                    $day->format('Y-m-d D'), $secs, $counts['invoices'], $counts['invoice_lines'], $counts['sale_lines'], $counts['sale_orders'], $counts['deliveries'], $counts['receipts'], $message));
            }

            if ($secs > 45 && $day->lt($end)) {
                $this->warn('That took over 45s -- stopping here to avoid straining Tally. Resume later with --from the next day.');
                break;
            }
            if ($day->lt($end)) {
                usleep(400_000);
            }
        }

        $this->newLine();
        $this->line($dry ? 'Dry run complete. Nothing was pushed.' : sprintf(
            'Done. Pushed %d invoice(s), %d invoice line(s), %d sale line(s), %d sale order line(s), %d delivery line(s), %d receipt(s).',
            $totals['invoices'], $totals['invoice_lines'], $totals['sale_lines'], $totals['sale_orders'], $totals['deliveries'], $totals['receipts']
        ));
        return self::SUCCESS;
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function resolveRange(): array
    {
        if ($this->option('from')) {
            $start = Carbon::createFromFormat('!Y-m-d', $this->option('from'));
            $end = Carbon::createFromFormat('!Y-m-d', $this->option('to') ?: $this->option('from'));
        } else {
            $d = $this->option('date') ? Carbon::createFromFormat('!Y-m-d', $this->option('date')) : Carbon::today();
            $start = $end = $d;
        }
        if (!$start || !$end || $end->lt($start)) {
            $this->error('--date, or --from/--to, must be valid dates (YYYY-MM-DD) with --to on or after --from.');
            return [null, null];
        }
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            $this->error('Refusing more than ' . self::MAX_DAYS . ' days in one run: it makes one request per day.');
            return [null, null];
        }
        return [$start, $end];
    }

    /** @return array{invoices: array, invoice_lines: array, sale_lines: array, sale_orders: array, deliveries: array, receipts: array} */
    private function mapDay(Carbon $day, array $vouchers): array
    {
        $out = ['invoices' => [], 'invoice_lines' => [], 'sale_lines' => [], 'sale_orders' => [], 'deliveries' => [], 'receipts' => []];
        $date = $day->format('Y-m-d');

        foreach ($vouchers as $v) {
            if ($v['cancelled']) {
                continue;
            }
            $type = (string) $v['type'];
            $isSalesOrder = (bool) preg_match('/^Sales\s*Order/i', $type);
            $isSalesBill = !$isSalesOrder && (bool) preg_match('/^Sales\b/i', $type);
            $isDeliveryNote = !$isSalesOrder && !$isSalesBill && (bool) preg_match('/^Delivery\s*Note/i', $type);
            // "Receipt Note" (goods received) is a distinct voucher type from "Receipt" (money received) --
            // excluded explicitly, not just by missing the dash/space, since both start with the word "Receipt".
            $isReceipt = !$isSalesOrder && !$isSalesBill && !$isDeliveryNote
                && (bool) preg_match('/^Receipt\b/i', $type) && !preg_match('/^Receipt\s*Note/i', $type);
            if ($isReceipt) {
                $number = (string) $v['number'];
                $party = (string) $v['party'];
                // A real Receipt voucher's ledger entries are exactly the party (positive) and the bank/cash
                // account it landed in (negative) -- never inventory lines, so $v['lines'] is always empty here.
                // The party leg's name matches $v['party'] exactly (both come from the same PARTYLEDGERNAME),
                // so picking "the other one" needs no fuzzy matching.
                $bankLedger = null;
                foreach ($v['ledgers'] ?? [] as $entry) {
                    $name = trim((string) $entry['name']);
                    if ($name !== '' && $name !== trim($party)) {
                        $bankLedger = $name;
                        break;
                    }
                }
                $out['receipts'][] = array_filter([
                    'receipt_key' => "{$date}|{$type}|{$number}",
                    'receipt_date' => $date, 'receipt_no' => $number, 'voucher_type' => $type,
                    'party_name' => $party ?: null, 'normalized_name' => NameMatcher::normalize($party) ?: null,
                    'amount' => abs((float) ($v['total'] ?? 0)), 'bank_ledger' => $bankLedger,
                    'reference' => $v['reference'] ?? null,
                ], fn ($val) => $val !== null);
                continue;
            }
            if ($isDeliveryNote) {
                $number = (string) $v['number'];
                $transport = $v['transport'];
                $consignee = $v['consignee'];
                foreach ($v['lines'] as $line) {
                    $out['deliveries'][] = array_filter([
                        'delivery_date' => $date, 'delivery_no' => $number, 'order_no' => $line['order_no'],
                        'party_name' => (string) $v['party'] ?: null, 'normalized_name' => NameMatcher::normalize((string) $v['party']) ?: null,
                        'item_name' => preg_replace('/\s+/', ' ', trim((string) $line['item'])), 'quantity' => $line['qty'], 'unit' => $line['unit'],
                        'transporter_name' => $transport['name'] ?? null, 'vehicle_number' => $transport['vehicle_number'] ?? null,
                        'transport_mode' => $transport['mode'] ?? null, 'document_number' => $transport['document_number'] ?? null,
                        'document_date' => $transport['document_date'] ?? null, 'distance_km' => $transport['distance'] ?? null,
                        'consignee_name' => $consignee['name'] ?? null, 'consignee_address' => $consignee['address'] ?? null, 'consignee_gstin' => $consignee['gstin'] ?? null,
                    ], fn ($v) => $v !== null);
                }
                continue;
            }
            if (!$isSalesOrder && !$isSalesBill) {
                continue;   // journals, purchase orders, etc. -- not what this push carries
            }

            $number = (string) $v['number'];
            $series = TallyFileReader::series($number);
            $party = (string) $v['party'];
            $normalized = NameMatcher::normalize($party) ?: null;
            $total = $v['total'] ?? array_sum(array_column($v['lines'], 'amount'));

            if ($isSalesBill) {
                $out['invoices'][] = [
                    'invoice_no' => $number, 'series' => $series, 'invoice_date' => $date,
                    'party_name' => $party ?: null, 'normalized_name' => $normalized, 'amount' => $total,
                    'extra' => array_filter(['party_gstin' => $v['party_gstin'], 'place_of_supply' => $v['place_of_supply'], 'reference' => $v['reference']]),
                ];
                foreach ($v['lines'] as $line) {
                    $item = preg_replace('/\s+/', ' ', trim((string) $line['item']));
                    $hsn = $line['hsn'];
                    $group = ProductGroup::detect($item, $hsn);
                    $out['invoice_lines'][] = [
                        'invoice_no' => $number, 'series' => $series, 'invoice_date' => $date,
                        'item_name' => $item, 'product_group' => $group, 'quantity' => $line['qty'], 'unit' => $line['unit'],
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                    ];
                    $rate = $this->gstRate($line['gst']);
                    $taxable = $line['amount'] ?? 0;
                    $out['sale_lines'][] = [
                        'invoice_no' => $number, 'series' => $series, 'invoice_date' => $date,
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                        'item_name' => $item, 'product_group' => $group, 'hsn' => $hsn, 'gst_rate' => $rate,
                        'quantity' => $line['qty'], 'unit' => $line['unit'], 'rate' => $line['rate'], 'rate_unit' => $line['rate_unit'],
                        'taxable_amount' => $taxable, 'tax_amount' => $rate !== null ? round($taxable * $rate / 100, 2) : null,
                    ];
                }
            } else {
                foreach ($v['lines'] as $line) {
                    $out['sale_orders'][] = [
                        'order_date' => $date, 'order_no' => $number,
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                        'quantity' => $line['qty'], 'unit' => $line['unit'], 'amount' => $line['amount'] ?? 0,
                    ];
                }
            }
        }

        return $out;
    }

    /** @param array<string,float> $gst head => % (CGST, SGST/UTGST, IGST, Cess, State Cess) */
    private function gstRate(array $gst): ?float
    {
        if ($gst === []) {
            return null;
        }
        $igst = $gst['IGST'] ?? 0.0;
        if ($igst > 0) {
            return $igst;
        }
        return ($gst['CGST'] ?? 0.0) + ($gst['SGST/UTGST'] ?? 0.0);
    }

    /** @return array{0: bool, 1: string} */
    private function push(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $nonce = Str::random(32);
        $secret = (string) config('tally_push.outbound.secret');
        $apiKey = (string) config('tally_push.outbound.api_key');
        if ($apiKey === '' || $secret === '') {
            return [false, 'TALLY_PUSH_API_KEY / TALLY_PUSH_SECRET not set'];
        }
        $signature = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'X-HRX-Timestamp' => $timestamp,
                    'X-HRX-Nonce' => $nonce,
                    'X-HRX-Signature' => 'sha256=' . $signature,
                ])
                ->connectTimeout(10)
                ->timeout((int) config('tally_push.outbound.timeout', 60))
                ->post($url);
        } catch (\Throwable $e) {
            return [false, 'connection error: ' . $e->getMessage()];
        }

        if (!$response->successful()) {
            return [false, 'HTTP ' . $response->status() . ' ' . Str::limit($response->body(), 200)];
        }
        $json = $response->json();
        $counts = $json['counts'] ?? [];
        return [true, 'server saved: ' . json_encode($counts)];
    }
}
