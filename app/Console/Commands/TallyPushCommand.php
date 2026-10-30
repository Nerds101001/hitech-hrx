<?php

namespace App\Console\Commands;

use App\Services\TallyGateway;
use App\Support\NameMatcher;
use App\Support\ProductGroup;
use App\Support\TallyFileReader;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        $companyCode = (string) $this->option('company');
        // Auto mode = the scheduled task's own call, with no explicit day/range -- the only mode that is
        // allowed to consult/advance tally_push_state and send failure emails. A manual --date/--from/--to
        // run (backfill-tally.ps1, testing, a one-off re-run) never touches that state or sends mail: it is
        // deliberate and already being watched by whoever typed it.
        $isAuto = !$this->option('date') && !$this->option('from') && !$this->option('to');

        [$start, $end] = $this->resolveRange($companyCode, $isAuto);
        if ($start === null) {
            return self::FAILURE;
        }

        $targetUrl = (string) ($this->option('target-url') ?: config('tally_push.outbound.target_url'));
        $dry = (bool) $this->option('dry-run');
        $isAuto = $isAuto && !$dry; // a dry run never advances or fails the tracked state -- it pushes nothing
        if (!$dry && $targetUrl === '') {
            $this->error('TALLY_PUSH_TARGET_URL is not set (config/tally_push.php outbound.target_url). Nothing to push to.');
            return self::FAILURE;
        }
        if (!$dry && !self::targetIsSafe($targetUrl)) {
            $this->error('Refusing to send: the target must be https:// (plain http is only allowed to this machine itself). Got: ' . $targetUrl);
            return self::FAILURE;
        }

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
            if ($isAuto) {
                $this->recordAutoFailure($companyCode, $e->getMessage());
            }
            return self::FAILURE;
        }
        $this->line("Gateway: {$gateway->baseUrl()}   company: {$gateway->expectedCompany()} [{$companyCode}]" . ($dry ? '   (dry run: nothing pushed)' : "   target: {$targetUrl}"));

        $totals = [
            'invoices' => 0, 'invoice_lines' => 0, 'sale_lines' => 0, 'sale_orders' => 0, 'deliveries' => 0, 'receipts' => 0,
            'purchases' => 0, 'purchase_lines' => 0, 'purchase_orders' => 0, 'payments' => 0, 'credit_debit_note_lines' => 0,
        ];
        $daysDone = 0;
        $lastCompletedDay = null;
        for ($day = $start->copy(); $day->lte($end); $day = $day->copy()->addDay()) {
            $t = microtime(true);
            try {
                $vouchers = $gateway->dayBook($day);
            } catch (\Throwable $e) {
                $this->error("{$day->format('Y-m-d')}: FAILED -- {$e->getMessage()} -- stopping here.");
                if ($isAuto) {
                    $this->recordAutoSuccessThrough($companyCode, $lastCompletedDay);
                    $this->recordAutoFailure($companyCode, "{$day->format('Y-m-d')}: {$e->getMessage()}");
                }
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
                    if ($isAuto) {
                        $this->recordAutoSuccessThrough($companyCode, $lastCompletedDay);
                        $this->recordAutoFailure($companyCode, "{$day->format('Y-m-d')}: push failed -- {$message}");
                    }
                    return self::FAILURE;
                }
                foreach ($counts as $k => $n) {
                    $totals[$k] += $n;
                }
                $this->info(sprintf('%s  %5.1fs  inv=%-3d lines=%-4d orders=%-4d deliv=%-4d rcpt=%-3d | purch=%-3d p.lines=%-4d p.orders=%-3d pay=%-3d  -> %s',
                    $day->format('Y-m-d D'), $secs, $counts['invoices'], $counts['sale_lines'], $counts['sale_orders'], $counts['deliveries'], $counts['receipts'],
                    $counts['purchases'], $counts['purchase_lines'], $counts['purchase_orders'], $counts['payments'], $message));
            }

            $lastCompletedDay = $day->copy();
            $daysDone++;

            if ($secs > 45 && $day->lt($end)) {
                $this->warn('That took over 45s -- stopping here to avoid straining Tally. Resume later with --from the next day.');
                break;
            }
            if ($day->lt($end)) {
                usleep(400_000);
            }
        }

        if ($isAuto) {
            // Every day up to $lastCompletedDay genuinely finished (pushed, or confirmed nothing to push) --
            // record it so the NEXT scheduled run resumes after it instead of re-trying or, worse, silently
            // never going further back than "today" the way the old unconditional Carbon::today() did.
            $this->recordAutoSuccessThrough($companyCode, $lastCompletedDay);
            $this->clearAutoFailure($companyCode);
        }

        $this->newLine();
        $this->line($dry ? 'Dry run complete. Nothing was pushed.' : sprintf(
            "Done. Sales: %d invoices, %d invoice lines, %d sale lines, %d order lines, %d deliveries, %d receipts.\n"
            . '      Purchases: %d bills, %d bill lines, %d order lines, %d payments. Credit/debit note lines: %d.',
            $totals['invoices'], $totals['invoice_lines'], $totals['sale_lines'], $totals['sale_orders'], $totals['deliveries'], $totals['receipts'],
            $totals['purchases'], $totals['purchase_lines'], $totals['purchase_orders'], $totals['payments'], $totals['credit_debit_note_lines']
        ));

        // One machine-readable line for hrx:tally-jobs-poll to parse back into the sync job's counts.
        if ($this->option('emit-totals')) {
            $this->line('TALLY_TOTALS ' . json_encode($totals + ['days_done' => $daysDone]));
        }
        return self::SUCCESS;
    }

    /**
     * In AUTO mode (the scheduled task's own call, no --date/--from/--to), this no longer just means "today":
     * it starts the day after tally_push_state.last_success_date for this company, so a gap left by a day
     * that failed cleanly (company not loaded, Tally unreachable, ...) gets picked up automatically by the
     * next run instead of staying a silent hole until someone notices and runs a manual backfill. $end is
     * always today regardless -- today's own vouchers keep arriving all day, so every run re-pulls it, same
     * as before. A gap wider than MAX_DAYS is capped to the most recent MAX_DAYS days; the state this run
     * records still only advances to wherever it actually got to, so a very old gap closes incrementally,
     * one scheduled run at a time, rather than needing one run to do it all.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function resolveRange(string $companyCode, bool $isAuto): array
    {
        if ($this->option('from')) {
            $start = Carbon::createFromFormat('!Y-m-d', $this->option('from'));
            $end = Carbon::createFromFormat('!Y-m-d', $this->option('to') ?: $this->option('from'));
        } elseif ($this->option('date')) {
            $start = $end = Carbon::createFromFormat('!Y-m-d', $this->option('date'));
        } else {
            $today = Carbon::today();
            $lastSuccess = $isAuto
                ? DB::table('tally_push_state')->where('company', $companyCode)->value('last_success_date')
                : null;
            $start = $lastSuccess ? Carbon::parse($lastSuccess)->addDay() : $today->copy();
            if ($start->gt($today)) {
                $start = $today->copy();
            }
            $end = $today;
        }
        if (!$start || !$end || $end->lt($start)) {
            $this->error('--date, or --from/--to, must be valid dates (YYYY-MM-DD) with --to on or after --from.');
            return [null, null];
        }
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            $start = $end->copy()->subDays(self::MAX_DAYS - 1);
        }
        return [$start, $end];
    }

    /** Advances (never rewinds) tally_push_state.last_success_date for this company and clears any failure streak. */
    private function recordAutoSuccessThrough(string $companyCode, ?Carbon $day): void
    {
        if ($day === null) {
            return;
        }
        $now = now();
        $dayStr = $day->format('Y-m-d');
        DB::table('tally_push_state')->upsert([
            'company' => $companyCode,
            'last_success_date' => $dayStr,
            'last_attempt_at' => $now,
            'last_success_at' => $now,
            'consecutive_failures' => 0,
            'last_failure_message' => null,
            'updated_at' => $now,
            'created_at' => $now,
        ], ['company'], [
            // last_success_date only ever moves forward: a later partial run (e.g. the 45s bail-out) must
            // never un-advance what an earlier, fuller run already recorded for today.
            'last_success_date' => DB::raw("CASE WHEN last_success_date IS NULL OR last_success_date < '{$dayStr}' THEN '{$dayStr}' ELSE last_success_date END"),
            'last_attempt_at' => $now,
            'last_success_at' => $now,
            'consecutive_failures' => 0,
            'last_failure_message' => null,
            'updated_at' => $now,
        ]);
    }

    private function clearAutoFailure(string $companyCode): void
    {
        DB::table('tally_push_state')->where('company', $companyCode)->update([
            'consecutive_failures' => 0, 'last_failure_message' => null, 'updated_at' => now(),
        ]);
    }

    /**
     * Records the failure and, unless we already emailed about this exact streak within the last 24h,
     * sends ai@rustx.com a notice -- same "gmail" transport and the-driver-must-actually-be-configured check
     * deployment/office-pc/notify-tally.php already uses, just invoked in-process instead of shelling out.
     */
    private function recordAutoFailure(string $companyCode, string $message): void
    {
        $now = now();
        $row = DB::table('tally_push_state')->where('company', $companyCode)->first();
        $consecutive = ((int) ($row->consecutive_failures ?? 0)) + 1;
        $lastNotified = $row->last_notified_at ?? null;
        $shouldNotify = $lastNotified === null || Carbon::parse($lastNotified)->lt($now->copy()->subHours(24));

        DB::table('tally_push_state')->upsert([
            'company' => $companyCode,
            'last_attempt_at' => $now,
            'consecutive_failures' => $consecutive,
            'last_failure_message' => $message,
            'last_notified_at' => $shouldNotify ? $now : $lastNotified,
            'updated_at' => $now,
            'created_at' => $now,
        ], ['company'], ['last_attempt_at', 'consecutive_failures', 'last_failure_message', 'last_notified_at', 'updated_at']);

        if ($shouldNotify) {
            $this->sendFailureEmail($companyCode, $message, $consecutive);
        }
    }

    private function sendFailureEmail(string $companyCode, string $message, int $consecutiveFailures): void
    {
        $gmail = (string) env('MAIL_GMAIL_USERNAME') !== '' && (string) env('MAIL_GMAIL_PASSWORD') !== '';
        $mailer = $gmail ? 'gmail' : (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("NOT emailing ai@rustx.com: mail driver is '{$mailer}' (no MAIL_GMAIL_USERNAME/PASSWORD set).");
            return;
        }

        $subject = "HRX Tally sync failing for \"{$companyCode}\"";
        $body = "hrx:tally-push --company={$companyCode} has failed " . ($consecutiveFailures > 1 ? "{$consecutiveFailures} runs in a row" : 'on its latest run') . ":\n\n"
            . "{$message}\n\n"
            . "This company's data will stay behind until the next scheduled run succeeds. If this is \"not loaded in Tally\","
            . " open TallyPrime on this PC and load that company.\n\n"
            . '-- Sent automatically by HRX on ' . gethostname() . ' at ' . now()->format('d M Y, h:i A') . ".\n";

        try {
            Mail::mailer($mailer)->raw($body, function ($m) use ($gmail, $subject) {
                $m->to('ai@rustx.com')->subject($subject);
                if ($gmail) {
                    $m->from((string) env('MAIL_GMAIL_USERNAME'), 'HRX Tally sync');
                }
            });
            $this->info('Failure notice emailed to ai@rustx.com.');
        } catch (\Throwable $e) {
            $this->warn('Could not email ai@rustx.com about this failure: ' . $e->getMessage());
        }
    }

    /** @return array{invoices: array, invoice_lines: array, sale_lines: array, sale_orders: array, deliveries: array, receipts: array, purchases: array, purchase_lines: array, purchase_orders: array, payments: array, credit_debit_note_lines: array} */
    private function mapDay(Carbon $day, array $vouchers): array
    {
        $out = [
            'invoices' => [], 'invoice_lines' => [], 'sale_lines' => [], 'sale_orders' => [], 'deliveries' => [], 'receipts' => [],
            'purchases' => [], 'purchase_lines' => [], 'purchase_orders' => [], 'payments' => [], 'credit_debit_note_lines' => [],
        ];
        $date = $day->format('Y-m-d');

        foreach ($vouchers as $v) {
            if ($v['cancelled']) {
                continue;
            }
            // Voucher type names in this company carry a suffix (e.g. "Sales GST - CHN", "Purchase Order - PG",
            // "Payment - HR"), so classify on the PREFIX for everything except Sales itself. Order matters:
            // "Purchase Order" must be tested before "Purchase", and "Receipt Note" (goods received) excluded
            // before "Receipt" (money received).
            //
            // Sales is matched by WORD anywhere in the type, not just the prefix -- some marketplace vouchers
            // (confirmed live: "amazon Sales-EV-GJ", "amazon Sales-EV-MH") don't start with "Sales" at all and
            // were being silently dropped entirely (not pushed as anything), found while reconciling a month's
            // total against Setu and coming up short by more than credit notes or missing ledgers accounted for.
            $type = (string) $v['type'];
            $isSalesOrder = (bool) preg_match('/\bSales\s*Order\b/i', $type);
            $isSalesBill = !$isSalesOrder && (bool) preg_match('/\bSales\b/i', $type);
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
            // Credit/Debit Note voucher types in this company carry a "(Sale)"/"(Pur)" suffix (e.g. "Debit Note
            // (Pur)-RMG", "Credit Note (Sale)-PB") identifying which side of the ledger it adjusts; fall back to
            // "sale" when absent rather than dropping the voucher, since most of this company's are sale-side.
            if (preg_match('/credit\s*note|debit\s*note/i', $type)) {
                $number = (string) $v['number'];
                $series = TallyFileReader::series($number);
                $party = (string) $v['party'];
                $normalized = NameMatcher::normalize($party) ?: null;
                $key = (string) ($v['guid'] ?: "{$date}|{$type}|{$number}");
                $noteType = (preg_match('/credit\s*note/i', $type) ? 'credit' : 'debit')
                    . '_' . (preg_match('/\(\s*pur/i', $type) ? 'purchase' : 'sale');
                foreach ($v['lines'] as $line) {
                    $item = preg_replace('/\s+/', ' ', trim((string) $line['item']));
                    $hsn = $line['hsn'];
                    $group = ProductGroup::detect($item, $hsn);
                    $rate = $this->gstRate($line['gst']);
                    $amount = abs((float) ($line['amount'] ?? 0));
                    $out['credit_debit_note_lines'][] = array_filter([
                        'note_key' => $key, 'note_type' => $noteType, 'note_no' => $number, 'series' => $series, 'note_date' => $date,
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                        'item_name' => $item, 'product_group' => $group, 'hsn' => $hsn, 'gst_rate' => $rate,
                        'quantity' => $line['qty'], 'unit' => $line['unit'], 'rate' => $line['rate'],
                        // 'amount' is the taxable portion, same convention as sale_lines/purchase_lines -- GST
                        // computed the identical way those do, so netting this against a tax-inclusive Sales/
                        // Purchase total doesn't silently under-subtract by the note's own GST.
                        'amount' => $amount, 'tax_amount' => $rate !== null ? round($amount * $rate / 100, 2) : null,
                        'reference' => $v['reference'] ?? null, 'order_no' => $line['order_no'] ?? ($v['order_no'] ?? null),
                    ], fn ($val) => $val !== null);
                }
                continue;
            }
            if (!$isSalesOrder && !$isSalesBill) {
                continue;   // journals, stock journals -- not what this push carries
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
                    // The sales order this invoice bills against, when Tally recorded one (REFERENCE field) --
                    // lets the Work Orders report compute an order-to-billing lead time without re-deriving it.
                    'order_no' => $v['order_no'] ?? null,
                    // Despatch Details (transporter, bilty no, despatch from/date, delivery date, terms,
                    // POD) -- see TallyGateway::parseVouchers()'s own doc comment for where each of these
                    // actually lives in Tally. Filtered on !== null specifically (not the default
                    // array_filter, which would also drop 'pod' => false -- a real "POD: No" answer, not
                    // an absent one).
                    'extra' => array_filter([
                        'party_gstin' => $v['party_gstin'], 'place_of_supply' => $v['place_of_supply'], 'reference' => $v['reference'],
                        'transporter_name' => $v['transport']['name'] ?? null,
                        'transporter_mobile' => $v['despatch']['transporter_mobile'] ?? null,
                        'bilty_no' => $v['despatch']['bilty_no'] ?? null,
                        'despatch_from' => $v['despatch']['despatch_from'] ?? null,
                        'despatch_date' => $v['despatch']['despatch_date'] ?? null,
                        'delivery_date' => $v['despatch']['delivery_date'] ?? null,
                        'terms' => $v['despatch']['terms'] ?? null,
                        'pod' => $v['despatch']['pod'] ?? null,
                    ], fn ($val) => $val !== null),
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
                    $item = preg_replace('/\s+/', ' ', trim((string) $line['item']));
                    $out['sale_orders'][] = [
                        'order_date' => $date, 'order_no' => $number,
                        'party_name' => $party ?: null, 'normalized_name' => $normalized,
                        'item_name' => $item, 'spec' => $line['spec'], 'hsn' => $line['hsn'],
                        'quantity' => $line['qty'], 'unit' => $line['unit'], 'rate' => $line['rate'], 'amount' => $line['amount'] ?? 0,
                        'pack_cases' => $line['pack_cases'], 'pack_gross_qty' => $line['pack_gross_qty'],
                        'pack_net_wt_qty' => $line['pack_net_wt_qty'], 'pack_qty_number' => $line['pack_qty_number'],
                        'financial_approval' => $v['financial_approval'] ?? null, 'remarks' => $v['remarks'] ?? null,
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
