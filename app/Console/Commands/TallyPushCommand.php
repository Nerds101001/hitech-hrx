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
                            {--company=hitech : Which configured company to pull (config/services.php tally.companies) -- tags every row pushed}
                            {--gateway-url= : Override services.tally.gateway_url (for testing against the sandbox)}
                            {--gateway-company= : Override the company name resolved from --company (services.tally.companies.<code>.name)}
                            {--target-url= : Override tally_push.outbound.target_url (e.g. to push to localhost while testing)}
                            {--emit-totals : Print a machine-readable "TALLY_TOTALS {json}" line at the end (used by hrx:tally-jobs-poll)}
                            {--dry-run : Pull and map only; push nothing, write nothing}';

    protected $description = 'Pull sales invoices and sale orders from Tally\'s XML gateway and push them to /api/tally-push/sync, one day at a time';

    /** More than this many days in one run is refused: it is one request per day, and Tally is single-threaded. */
    private const MAX_DAYS = 31;

    /**
     * Signed financial data only ever travels over TLS: https:// anywhere, or plain http:// to this very machine
     * (the local test setup). A mistyped http://hrx.hitechgroup.in/... would otherwise send the bearer key and the
     * whole day's invoices in clear text (or be silently redirected by the host, dropping the body).
     */
    public static function targetIsSafe(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        return $scheme === 'https' || ($scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true));
    }

    /**
     * The Tally company name to read for a given --company code: --gateway-company wins when given (manual
     * override, e.g. testing against the sandbox under a code that doesn't match it), else
     * services.tally.companies.<code>.name. 'hitech' additionally falls back to write_gateway_company so every
     * push command still works unmodified for callers (today's scheduled tasks) that don't pass --company at
     * all -- see config/services.php tally.companies' own doc comment.
     */
    public static function resolveCompanyName(string $code, ?string $override): ?string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }
        $configured = (string) config("services.tally.companies.{$code}.name", '');
        if ($configured !== '') {
            return $configured;
        }
        if ($code === 'hitech') {
            $fallback = (string) config('services.tally.write_gateway_company', '');
            return $fallback !== '' ? $fallback : null;
        }
        return null;
    }

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
        if (!$dry && !self::targetIsSafe($targetUrl)) {
            $this->error('Refusing to send: the target must be https:// (plain http is only allowed to this machine itself). Got: ' . $targetUrl);
            return self::FAILURE;
        }

        $companyCode = (string) $this->option('company');
        $companyName = self::resolveCompanyName($companyCode, $this->option('gateway-company'));
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
        $this->line("Gateway: {$gateway->baseUrl()}   company: {$gateway->expectedCompany()} [{$companyCode}]" . ($dry ? '   (dry run: nothing pushed)' : "   target: {$targetUrl}"));

        $totals = [
            'invoices' => 0, 'invoice_lines' => 0, 'sale_lines' => 0, 'sale_orders' => 0, 'deliveries' => 0, 'receipts' => 0,
            'purchases' => 0, 'purchase_lines' => 0, 'purchase_orders' => 0, 'payments' => 0,
        ];
        $daysDone = 0;
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
            if ($payload !== []) {
                $payload['company'] = $companyCode;
            }

            if ($dry) {
                $this->line(sprintf('%s  %5.1fs  vouchers=%-4d inv=%-3d lines=%-4d orders=%-4d deliv=%-4d rcpt=%-3d | purch=%-3d p.lines=%-4d p.orders=%-3d pay=%-3d  (dry run)',
                    $day->format('Y-m-d D'), $secs, count($vouchers), $counts['invoices'], $counts['sale_lines'], $counts['sale_orders'], $counts['deliveries'], $counts['receipts'],
                    $counts['purchases'], $counts['purchase_lines'], $counts['purchase_orders'], $counts['payments']));
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
                $this->info(sprintf('%s  %5.1fs  inv=%-3d lines=%-4d orders=%-4d deliv=%-4d rcpt=%-3d | purch=%-3d p.lines=%-4d p.orders=%-3d pay=%-3d  -> %s',
                    $day->format('Y-m-d D'), $secs, $counts['invoices'], $counts['sale_lines'], $counts['sale_orders'], $counts['deliveries'], $counts['receipts'],
                    $counts['purchases'], $counts['purchase_lines'], $counts['purchase_orders'], $counts['payments'], $message));
            }

            $daysDone++;

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
            "Done. Sales: %d invoices, %d invoice lines, %d sale lines, %d order lines, %d deliveries, %d receipts.\n"
            . '      Purchases: %d bills, %d bill lines, %d order lines, %d payments.',
            $totals['invoices'], $totals['invoice_lines'], $totals['sale_lines'], $totals['sale_orders'], $totals['deliveries'], $totals['receipts'],
            $totals['purchases'], $totals['purchase_lines'], $totals['purchase_orders'], $totals['payments']
        ));

        // One machine-readable line for hrx:tally-jobs-poll to parse back into the sync job's counts.
        if ($this->option('emit-totals')) {
            $this->line('TALLY_TOTALS ' . json_encode($totals + ['days_done' => $daysDone]));
        }
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

    /** @return array{invoices: array, invoice_lines: array, sale_lines: array, sale_orders: array, deliveries: array, receipts: array, purchases: array, purchase_lines: array, purchase_orders: array, payments: array} */
    private function mapDay(Carbon $day, array $vouchers): array
    {
        $out = [
            'invoices' => [], 'invoice_lines' => [], 'sale_lines' => [], 'sale_orders' => [], 'deliveries' => [], 'receipts' => [],
            'purchases' => [], 'purchase_lines' => [], 'purchase_orders' => [], 'payments' => [],
        ];
        $date = $day->format('Y-m-d');

        foreach ($vouchers as $v) {
            if ($v['cancelled']) {
                continue;
            }
            // Voucher type names in this company carry a suffix (e.g. "Sales GST - CHN", "Purchase Order - PG",
            // "Payment - HR"), so classify on the PREFIX. Order matters: "Purchase Order" must be tested before
            // "Purchase", and "Receipt Note" (goods received) excluded before "Receipt" (money received).
            $type = (string) $v['type'];
            $isSalesOrder = (bool) preg_match('/^Sales\s*Order/i', $type);
            $isSalesBill = !$isSalesOrder && (bool) preg_match('/^Sales\b/i', $type);
            $isPurchaseOrder = (bool) preg_match('/^Purchase\s*Order/i', $type);
            $isPurchaseBill = !$isPurchaseOrder && (bool) preg_match('/^Purchase\b/i', $type);
            $isDeliveryNote = (bool) preg_match('/^Delivery\s*Note/i', $type);
            $isReceipt = (bool) preg_match('/^Receipt\b/i', $type) && !preg_match('/^Receipt\s*Note/i', $type);
            $isPayment = (bool) preg_match('/^Payment\b/i', $type);
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
                    '_guid' => (string) ($v['guid'] ?? ''),   // only to tell same-numbered receipts apart; stripped below
                ], fn ($val) => $val !== null);
                continue;
            }
            if ($isPayment) {
                $number = (string) $v['number'];
                $party = (string) $v['party'];
                // A payment pays money out. The bank/cash leg is the non-party ledger (the biggest one, for a
                // multi-line expense payment). amount is the party's own line when there's a party, else the bank leg.
                $bankLedger = null;
                $bankAmt = 0.0;
                foreach ($v['ledgers'] ?? [] as $entry) {
                    $name = trim((string) $entry['name']);
                    $amt = abs((float) ($entry['amount'] ?? 0));
                    if ($name !== '' && $name !== trim($party) && $amt >= $bankAmt) {
                        $bankLedger = $name;
                        $bankAmt = $amt;
                    }
                }
                $amount = abs((float) ($v['total'] ?? 0)) ?: $bankAmt;
                $out['payments'][] = array_filter([
                    'payment_key' => "{$date}|{$type}|{$number}",
                    'payment_date' => $date, 'payment_no' => $number, 'voucher_type' => $type,
                    'party_name' => $party ?: null, 'normalized_name' => NameMatcher::normalize($party) ?: null,
                    'amount' => $amount, 'bank_ledger' => $bankLedger,
                    'reference' => $v['reference'] ?? null,
                    '_guid' => (string) ($v['guid'] ?? ''),   // disambiguates same-numbered payments; stripped below
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
            if ($isPurchaseOrder) {
                $number = (string) $v['number'];
                $party = (string) $v['party'];
                $normalized = NameMatcher::normalize($party) ?: null;
                // Keyed by the voucher GUID: a supplier's PO number isn't unique across suppliers/days, so keying by
                // number alone would let a later push delete an earlier, unrelated order with the same number.
                $key = (string) ($v['guid'] ?: "{$date}|{$party}|{$number}");
                foreach ($v['lines'] as $line) {
                    $out['purchase_orders'][] = [
                        'order_key' => $key, 'order_date' => $date, 'order_no' => $number,
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                        'item_name' => preg_replace('/\s+/', ' ', trim((string) $line['item'])),
                        'quantity' => $line['qty'], 'unit' => $line['unit'], 'amount' => abs((float) ($line['amount'] ?? 0)),
                    ];
                }
                continue;
            }
            if ($isPurchaseBill) {
                $number = (string) $v['number'];
                $series = TallyFileReader::series($number);
                $party = (string) $v['party'];
                $normalized = NameMatcher::normalize($party) ?: null;
                // Keyed by the voucher GUID -- the "purchase number" is the SUPPLIER's bill number and repeats across
                // suppliers and days, so it can't key the row or join the lines to their header.
                $key = (string) ($v['guid'] ?: "{$date}|{$party}|{$number}");
                $total = abs((float) ($v['total'] ?? array_sum(array_column($v['lines'], 'amount'))));
                $out['purchases'][] = [
                    'purchase_key' => $key, 'purchase_no' => $number, 'series' => $series, 'purchase_date' => $date,
                    'party_name' => $party ?: null, 'normalized_name' => $normalized, 'amount' => $total,
                    'extra' => array_filter(['party_gstin' => $v['party_gstin'], 'place_of_supply' => $v['place_of_supply'], 'reference' => $v['reference']]),
                ];
                foreach ($v['lines'] as $line) {
                    $item = preg_replace('/\s+/', ' ', trim((string) $line['item']));
                    $hsn = $line['hsn'];
                    $group = ProductGroup::detect($item, $hsn);
                    $rate = $this->gstRate($line['gst']);
                    // Purchase inventory amounts come through negative (goods in / supplier credited) -- show the
                    // value a person reads, positive, like the header total.
                    $taxable = abs((float) ($line['amount'] ?? 0));
                    $out['purchase_lines'][] = [
                        'purchase_key' => $key, 'purchase_no' => $number, 'series' => $series, 'purchase_date' => $date,
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                        'item_name' => $item, 'product_group' => $group, 'hsn' => $hsn, 'gst_rate' => $rate,
                        'quantity' => $line['qty'], 'unit' => $line['unit'], 'rate' => $line['rate'], 'rate_unit' => $line['rate_unit'],
                        'taxable_amount' => $taxable, 'tax_amount' => $rate !== null ? round($taxable * $rate / 100, 2) : null,
                    ];
                }
                continue;
            }
            if (!$isSalesOrder && !$isSalesBill) {
                continue;   // journals, credit/debit notes, stock journals -- not what this push carries
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

        $out['receipts'] = $this->uniqueKeys($out['receipts'], 'receipt_key');
        $out['payments'] = $this->uniqueKeys($out['payments'], 'payment_key');

        return $out;
    }

    /**
     * Tally does not stop two different Receipt vouchers having the same type and number on one day (e.g. a second
     * payment-gateway receipt keyed in under the same number). The receipt key is date|type|number, so the second one
     * collided with the first, the server refused the whole day, and every sync that touched that day failed.
     *
     * The first voucher (lowest GUID, which never changes) keeps the plain key, so rows already stored under it are
     * replaced rather than duplicated; each further one gets "#<end of its GUID>". Both stay stable between pushes.
     *
     * @param  array<int,array<string,mixed>> $receipts
     * @return array<int,array<string,mixed>>
     */
    private function uniqueKeys(array $rows, string $keyField): array
    {
        $groups = [];
        foreach ($rows as $i => $r) {
            $groups[$r[$keyField]][] = $i;
        }
        foreach ($groups as $key => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }
            usort($indexes, fn ($a, $b) => strcmp((string) ($rows[$a]['_guid'] ?? ''), (string) ($rows[$b]['_guid'] ?? '')));
            foreach (array_slice($indexes, 1) as $n => $i) {
                $tail = substr(preg_replace('/[^A-Za-z0-9]/', '', (string) ($rows[$i]['_guid'] ?? '')), -12);
                $rows[$i][$keyField] = $key . '#' . ($tail !== '' ? $tail : $n + 2);
            }
        }
        foreach ($rows as &$r) {
            unset($r['_guid']);
        }
        unset($r);

        return $rows;
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

    /**
     * The signed headers every call to the push API carries: bearer key + HMAC-SHA256 over timestamp.nonce.body,
     * a fresh timestamp and single-use nonce each time. Shared with hrx:tally-jobs-poll's claim/report calls so
     * the two can never drift out of step with what VerifyTallyPushSignature expects.
     *
     * @return array<string,string>
     */
    public static function signedHeaders(string $apiKey, string $secret, string $body): array
    {
        $timestamp = (string) time();
        $nonce = Str::random(32);
        return [
            'Authorization' => 'Bearer ' . $apiKey,
            'X-HRX-Timestamp' => $timestamp,
            'X-HRX-Nonce' => $nonce,
            'X-HRX-Signature' => 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret),
        ];
    }

    /** @return array{0: bool, 1: string} */
    private function push(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $secret = (string) config('tally_push.outbound.secret');
        $apiKey = (string) config('tally_push.outbound.api_key');
        if ($apiKey === '' || $secret === '') {
            return [false, 'TALLY_PUSH_API_KEY / TALLY_PUSH_SECRET not set'];
        }

        try {
            $response = Http::withBody($body, 'application/json')
                ->withHeaders(self::signedHeaders($apiKey, $secret, $body))
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
