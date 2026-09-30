<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\TallyTotals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives what the local hrx:tally-push command pulls from Tally's own XML gateway and lands it in the SAME
 * tally_* tables the daily-file import (ImportTallyFilesCommand) already populates -- this is a second,
 * independent way to fill them, not a separate dataset. The two can run side by side: whichever last touched
 * a given invoice/order number wins for that row.
 *
 * Write strategy, chosen to fit tables that were designed as full-snapshot-replace targets (see the tally_*
 * migrations' own doc comment) onto an INCREMENTAL feed instead, without a schema change:
 *   - tally_invoices has a genuine unique key (invoice_no), so it's a real upsert.
 *   - tally_invoice_lines / tally_sale_lines have no unique key; for every invoice_no PRESENT IN THIS PUSH,
 *     its existing lines are deleted and replaced with what this push carries -- scoped to just those invoice
 *     numbers, so a partial (e.g. one day's) push never touches any other day's lines.
 *   - tally_sale_orders: same delete-and-replace pattern, scoped by order_no.
 *   - tally_deliveries / tally_receipts: same pattern, scoped by delivery_no / receipt_key (tally_receipts has
 *     no natural unique number -- receipt_key combines date+voucher_type+receipt_no since the bank/branch
 *     series lives only in the voucher type, e.g. "Receipt - CH" vs "Receipt-LDH", never in the number itself).
 * Every section (invoices / invoice_lines / sale_lines / sale_orders / deliveries / receipts) is optional and
 * independent, so a push that only carries today's invoices leaves everything else untouched.
 */
class TallyPushController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $run = DB::table('tally_push_runs')->insertGetId([
            'started_at' => now(), 'status' => 'running', 'source_ip' => $request->ip(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Every field this controller reads (see invoiceRow()/invoiceLineRow()/saleLineRow()/saleOrderRow() below)
        // MUST have a rule here, even just 'nullable' -- Validator::validated() (what $request->validate() returns)
        // silently drops any key that has no rule at all, rather than passing it through unchecked. A field
        // that looked present in the request but arrived as null downstream almost certainly means it's missing
        // from this list, not that the puller failed to send it.
        try {
            $data = $request->validate([
                'invoices' => ['sometimes', 'array'],
                'invoices.*.invoice_no' => ['required_with:invoices', 'string', 'max:60'],
                'invoices.*.series' => ['nullable', 'string', 'max:20'],
                'invoices.*.invoice_date' => ['required_with:invoices', 'date'],
                'invoices.*.party_code' => ['nullable', 'string', 'max:20'],
                'invoices.*.party_name' => ['nullable', 'string'],
                'invoices.*.normalized_name' => ['nullable', 'string'],
                'invoices.*.salesperson' => ['nullable', 'string', 'max:120'],
                'invoices.*.amount' => ['nullable', 'numeric'],
                'invoices.*.lr_no' => ['nullable', 'string', 'max:60'],
                'invoices.*.transporter' => ['nullable', 'string'],
                'invoices.*.transporter_phone' => ['nullable', 'string', 'max:60'],
                'invoices.*.destination' => ['nullable', 'string', 'max:120'],
                'invoices.*.po_number' => ['nullable', 'string', 'max:120'],
                'invoices.*.is_branch_transfer' => ['nullable', 'boolean'],
                'invoices.*.extra' => ['nullable', 'array'],

                'invoice_lines' => ['sometimes', 'array'],
                'invoice_lines.*.invoice_no' => ['required_with:invoice_lines', 'string', 'max:60'],
                'invoice_lines.*.invoice_date' => ['nullable', 'date'],
                'invoice_lines.*.item_name' => ['nullable', 'string', 'max:500'],
                'invoice_lines.*.product_group' => ['nullable', 'string', 'max:60'],
                'invoice_lines.*.quantity' => ['nullable', 'numeric'],
                'invoice_lines.*.unit' => ['nullable', 'string', 'max:20'],
                'invoice_lines.*.series' => ['nullable', 'string', 'max:20'],
                'invoice_lines.*.party_code' => ['nullable', 'string', 'max:20'],
                'invoice_lines.*.party_name' => ['nullable', 'string'],
                'invoice_lines.*.normalized_name' => ['nullable', 'string'],
                'invoice_lines.*.salesperson' => ['nullable', 'string', 'max:120'],
                'invoice_lines.*.is_branch_transfer' => ['nullable', 'boolean'],

                'sale_lines' => ['sometimes', 'array'],
                'sale_lines.*.invoice_no' => ['required_with:sale_lines', 'string', 'max:60'],
                'sale_lines.*.series' => ['nullable', 'string', 'max:20'],
                'sale_lines.*.invoice_date' => ['required_with:sale_lines', 'date'],
                'sale_lines.*.party_code' => ['nullable', 'string', 'max:20'],
                'sale_lines.*.party_name' => ['nullable', 'string'],
                'sale_lines.*.normalized_name' => ['nullable', 'string'],
                'sale_lines.*.city' => ['nullable', 'string', 'max:120'],
                'sale_lines.*.item_name' => ['nullable', 'string', 'max:500'],
                'sale_lines.*.product_group' => ['nullable', 'string', 'max:60'],
                'sale_lines.*.hsn' => ['nullable', 'string', 'max:16'],
                'sale_lines.*.gst_rate' => ['nullable', 'numeric'],
                'sale_lines.*.quantity' => ['nullable', 'numeric'],
                'sale_lines.*.unit' => ['nullable', 'string', 'max:20'],
                'sale_lines.*.rate' => ['nullable', 'numeric'],
                'sale_lines.*.rate_unit' => ['nullable', 'string', 'max:20'],
                'sale_lines.*.taxable_amount' => ['required_with:sale_lines', 'numeric'],
                'sale_lines.*.tax_amount' => ['nullable', 'numeric'],
                'sale_lines.*.due_date' => ['nullable', 'date'],
                'sale_lines.*.credit_terms' => ['nullable', 'string', 'max:40'],
                'sale_lines.*.salesperson' => ['nullable', 'string', 'max:120'],
                'sale_lines.*.is_branch_transfer' => ['nullable', 'boolean'],

                'sale_orders' => ['sometimes', 'array'],
                'sale_orders.*.order_no' => ['required_with:sale_orders', 'string', 'max:60'],
                'sale_orders.*.order_date' => ['required_with:sale_orders', 'date'],
                'sale_orders.*.status' => ['nullable', 'string', 'max:40'],
                'sale_orders.*.party_code' => ['nullable', 'string', 'max:20'],
                'sale_orders.*.party_name' => ['nullable', 'string'],
                'sale_orders.*.normalized_name' => ['nullable', 'string'],
                'sale_orders.*.quantity' => ['nullable', 'numeric'],
                'sale_orders.*.unit' => ['nullable', 'string', 'max:20'],
                'sale_orders.*.amount' => ['nullable', 'numeric'],

                'deliveries' => ['sometimes', 'array'],
                'deliveries.*.delivery_no' => ['required_with:deliveries', 'string', 'max:60'],
                'deliveries.*.delivery_date' => ['required_with:deliveries', 'date'],
                'deliveries.*.order_no' => ['nullable', 'string', 'max:60'],
                'deliveries.*.party_name' => ['nullable', 'string'],
                'deliveries.*.normalized_name' => ['nullable', 'string'],
                'deliveries.*.item_name' => ['nullable', 'string', 'max:500'],
                'deliveries.*.quantity' => ['nullable', 'numeric'],
                'deliveries.*.unit' => ['nullable', 'string', 'max:20'],
                'deliveries.*.transporter_name' => ['nullable', 'string', 'max:255'],
                'deliveries.*.vehicle_number' => ['nullable', 'string', 'max:40'],
                'deliveries.*.transport_mode' => ['nullable', 'string', 'max:40'],
                'deliveries.*.document_number' => ['nullable', 'string', 'max:60'],
                'deliveries.*.document_date' => ['nullable', 'date'],
                'deliveries.*.distance_km' => ['nullable', 'numeric'],
                'deliveries.*.consignee_name' => ['nullable', 'string'],
                'deliveries.*.consignee_address' => ['nullable', 'string'],
                'deliveries.*.consignee_gstin' => ['nullable', 'string', 'max:20'],

                'receipts' => ['sometimes', 'array'],
                'receipts.*.receipt_key' => ['required_with:receipts', 'string', 'max:150'],
                'receipts.*.receipt_date' => ['required_with:receipts', 'date'],
                'receipts.*.receipt_no' => ['required_with:receipts', 'string', 'max:60'],
                'receipts.*.voucher_type' => ['nullable', 'string', 'max:60'],
                'receipts.*.party_name' => ['nullable', 'string'],
                'receipts.*.normalized_name' => ['nullable', 'string'],
                'receipts.*.amount' => ['nullable', 'numeric'],
                'receipts.*.bank_ledger' => ['nullable', 'string'],
                'receipts.*.reference' => ['nullable', 'string', 'max:120'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::table('tally_push_runs')->where('id', $run)->update([
                'status' => 'rejected', 'error' => json_encode($e->errors()), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            throw $e;
        }

        $counts = [];

        try {
            DB::transaction(function () use ($data, &$counts) {
                // Tally's XML gateway doesn't carry the short customer "party code" the daily-file import puts
                // on tally_invoices/tally_sale_orders/tally_outstanding (checked: not the ledger's MasterId,
                // AlterId, Alias, nor anywhere on the voucher itself -- it comes from whatever custom report
                // produces the file exports, not a plain Tally master field). That code is a real join/group
                // key for TallyAnalytics (party_code groups invoices, joins tally_outstanding, drives the
                // per-customer drill-down page), so a pushed row that leaves it null would silently vanish
                // from or mis-group in those views -- not just look cosmetically incomplete. Fix: look up the
                // code the file import already recorded for the same normalized customer name, and use that
                // when the puller didn't send one itself.
                $codeLookup = $this->resolvePartyCodes(array_merge(
                    array_column($data['invoices'] ?? [], 'normalized_name'),
                    array_column($data['sale_orders'] ?? [], 'normalized_name'),
                    array_column($data['invoice_lines'] ?? [], 'normalized_name'),
                    array_column($data['sale_lines'] ?? [], 'normalized_name'),
                    array_column($data['receipts'] ?? [], 'normalized_name'),
                ));

                $codeByInvoiceNo = [];
                if (!empty($data['invoices'])) {
                    $now = now();
                    $rows = [];
                    foreach ($data['invoices'] as $r) {
                        $code = $r['party_code'] ?? $codeLookup[$r['normalized_name'] ?? ''] ?? null;
                        $codeByInvoiceNo[$r['invoice_no']] = $code;
                        $rows[] = $this->invoiceRow(array_merge($r, ['party_code' => $code]), $now);
                    }
                    DB::table('tally_invoices')->upsert(
                        $rows,
                        ['invoice_no'],
                        ['series', 'invoice_date', 'party_code', 'party_name', 'normalized_name', 'salesperson', 'amount',
                            'lr_no', 'transporter', 'transporter_phone', 'destination', 'po_number', 'is_branch_transfer', 'extra', 'updated_at']
                    );
                    $counts['invoices'] = count($rows);
                }

                // Falls back through three tiers: the invoice this line belongs to (when pushed in the same
                // request), then a code already sent on the line itself, then a lookup by the line's OWN
                // normalized name -- so invoice_lines/sale_lines still resolve a code even when pushed without
                // an accompanying invoices section, matching what invoices/sale_orders already do.
                if (!empty($data['invoice_lines'])) {
                    $counts['invoice_lines'] = $this->replaceByKey('tally_invoice_lines', 'invoice_no', $data['invoice_lines'],
                        fn ($r) => $this->invoiceLineRow(array_merge($r, ['party_code' =>
                            $codeByInvoiceNo[$r['invoice_no']] ?? $r['party_code'] ?? $codeLookup[$r['normalized_name'] ?? ''] ?? null])));
                }

                if (!empty($data['sale_lines'])) {
                    $counts['sale_lines'] = $this->replaceByKey('tally_sale_lines', 'invoice_no', $data['sale_lines'],
                        fn ($r) => $this->saleLineRow(array_merge($r, ['party_code' =>
                            $codeByInvoiceNo[$r['invoice_no']] ?? $r['party_code'] ?? $codeLookup[$r['normalized_name'] ?? ''] ?? null])));
                }

                if (!empty($data['sale_orders'])) {
                    $counts['sale_orders'] = $this->replaceByKey('tally_sale_orders', 'order_no', $data['sale_orders'],
                        fn ($r) => $this->saleOrderRow(array_merge($r, ['party_code' => $r['party_code'] ?? $codeLookup[$r['normalized_name'] ?? ''] ?? null])));
                }

                if (!empty($data['deliveries'])) {
                    $counts['deliveries'] = $this->replaceByKey('tally_deliveries', 'delivery_no', $data['deliveries'],
                        fn ($r) => $this->deliveryRow($r));
                }

                if (!empty($data['receipts'])) {
                    $counts['receipts'] = $this->replaceByKey('tally_receipts', 'receipt_key', $data['receipts'],
                        fn ($r) => $this->receiptRow(array_merge($r, ['party_code' => $codeLookup[$r['normalized_name'] ?? ''] ?? null])));
                }
            });
        } catch (\Throwable $e) {
            // Everything up to here is still inside the write transaction, so a failure genuinely means nothing
            // was committed -- the message below is accurate for this catch specifically.
            Log::error('[tally_push] sync failed', ['run_id' => $run, 'message' => $e->getMessage()]);
            DB::table('tally_push_runs')->where('id', $run)->update([
                'status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['ok' => false, 'run_id' => $run, 'error' => 'Sync failed; nothing was committed.'], 500);
        }

        // The write transaction above has already committed by this point. A failure from here on is reported
        // as "partial" with an honest message, never as "nothing was committed" -- that would be false.
        try {
            if (array_intersect(['invoices', 'invoice_lines', 'sale_lines'], array_keys($counts))) {
                TallyTotals::stamp();
            }
            DB::table('tally_push_runs')->where('id', $run)->update([
                'status' => 'ok', 'counts' => json_encode($counts), 'finished_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json(['ok' => true, 'run_id' => $run, 'counts' => $counts]);
        } catch (\Throwable $e) {
            Log::error('[tally_push] sync saved but totals stamp failed', ['run_id' => $run, 'message' => $e->getMessage()]);
            DB::table('tally_push_runs')->where('id', $run)->update([
                'status' => 'partial', 'error' => 'Saved, but recomputing invoice totals failed: ' . $e->getMessage(),
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json([
                'ok' => true, 'run_id' => $run, 'counts' => $counts,
                'warning' => 'Data was saved, but recomputing invoice totals failed. It will self-correct on the next successful push.',
            ], 200);
        }
    }

    /**
     * The most recent non-null party_code the daily-file import already recorded for each normalized customer
     * name, for the names given. A customer never seen by that import (brand new, or Setu/manual-only) simply
     * won't be in the result -- callers fall back to whatever the puller sent (usually null), which is an
     * honest gap, not a wrong guess.
     */
    private function resolvePartyCodes(array $normalizedNames): array
    {
        $normalizedNames = array_values(array_unique(array_filter($normalizedNames, fn ($n) => is_string($n) && $n !== '')));
        if ($normalizedNames === []) {
            return [];
        }
        return DB::table('tally_invoices')
            ->select('normalized_name', 'party_code', 'invoice_date')
            ->whereIn('normalized_name', $normalizedNames)
            ->whereNotNull('party_code')
            ->where('party_code', '!=', '')
            ->orderByDesc('invoice_date')
            ->get()
            ->groupBy('normalized_name')
            ->map(fn ($rows) => $rows->first()->party_code)
            ->all();
    }

    /**
     * Deletes every existing row in $table whose $keyColumn is one of the keys present in $rows, then inserts
     * $rows fresh -- an upsert-by-replace for tables with no DB-level unique constraint on $keyColumn. Scoped
     * to exactly the keys in this push, so a partial push never touches rows for a key it didn't mention.
     */
    private function replaceByKey(string $table, string $keyColumn, array $rows, \Closure $mapRow): int
    {
        $mapped = array_map($mapRow, $rows);
        $keys = array_values(array_unique(array_column($mapped, $keyColumn)));
        DB::table($table)->whereIn($keyColumn, $keys)->delete();
        foreach (array_chunk($mapped, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
        return count($mapped);
    }

    private function invoiceRow(array $r, \Illuminate\Support\Carbon $now): array
    {
        return [
            'invoice_no' => $r['invoice_no'],
            'series' => $r['series'] ?? null,
            'invoice_date' => $r['invoice_date'],
            'party_code' => $r['party_code'] ?? null,
            'party_name' => $r['party_name'] ?? null,
            'normalized_name' => $r['normalized_name'] ?? null,
            'salesperson' => $r['salesperson'] ?? null,
            'amount' => $r['amount'] ?? 0,
            'lr_no' => $r['lr_no'] ?? null,
            'transporter' => $r['transporter'] ?? null,
            'transporter_phone' => $r['transporter_phone'] ?? null,
            'destination' => $r['destination'] ?? null,
            'po_number' => $r['po_number'] ?? null,
            'is_branch_transfer' => (bool) ($r['is_branch_transfer'] ?? false),
            'extra' => isset($r['extra']) ? json_encode($r['extra']) : null,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }

    private function invoiceLineRow(array $r): array
    {
        return [
            'invoice_no' => $r['invoice_no'],
            'invoice_date' => $r['invoice_date'] ?? null,
            'item_name' => $r['item_name'] ?? null,
            'product_group' => $r['product_group'] ?? null,
            'quantity' => $r['quantity'] ?? null,
            'unit' => $r['unit'] ?? null,
            'series' => $r['series'] ?? null,
            'party_code' => $r['party_code'] ?? null,
            'party_name' => $r['party_name'] ?? null,
            'salesperson' => $r['salesperson'] ?? null,
            'is_branch_transfer' => (bool) ($r['is_branch_transfer'] ?? false),
        ];
    }

    private function saleLineRow(array $r): array
    {
        return [
            'invoice_no' => $r['invoice_no'],
            'series' => $r['series'] ?? null,
            'invoice_date' => $r['invoice_date'] ?? null,
            'party_code' => $r['party_code'] ?? null,
            'party_name' => $r['party_name'] ?? null,
            'normalized_name' => $r['normalized_name'] ?? null,
            'city' => $r['city'] ?? null,
            'item_name' => $r['item_name'] ?? '',
            'product_group' => $r['product_group'] ?? null,
            'hsn' => $r['hsn'] ?? null,
            'gst_rate' => $r['gst_rate'] ?? null,
            'quantity' => $r['quantity'] ?? null,
            'unit' => $r['unit'] ?? null,
            'rate' => $r['rate'] ?? null,
            'rate_unit' => $r['rate_unit'] ?? null,
            'taxable_amount' => $r['taxable_amount'],
            'tax_amount' => $r['tax_amount'] ?? null,
            'due_date' => $r['due_date'] ?? null,
            'credit_terms' => $r['credit_terms'] ?? null,
            'salesperson' => $r['salesperson'] ?? null,
            'is_branch_transfer' => (bool) ($r['is_branch_transfer'] ?? false),
        ];
    }

    private function saleOrderRow(array $r): array
    {
        return [
            'order_date' => $r['order_date'],
            'order_no' => $r['order_no'],
            'status' => $r['status'] ?? null,
            'party_code' => $r['party_code'] ?? null,
            'party_name' => $r['party_name'] ?? null,
            'normalized_name' => $r['normalized_name'] ?? null,
            'quantity' => $r['quantity'] ?? null,
            'unit' => $r['unit'] ?? null,
            'amount' => $r['amount'] ?? 0,
        ];
    }

    private function deliveryRow(array $r): array
    {
        return [
            'delivery_date' => $r['delivery_date'],
            'delivery_no' => $r['delivery_no'],
            'order_no' => $r['order_no'] ?? null,
            'party_name' => $r['party_name'] ?? null,
            'normalized_name' => $r['normalized_name'] ?? null,
            'item_name' => $r['item_name'] ?? null,
            'quantity' => $r['quantity'] ?? null,
            'unit' => $r['unit'] ?? null,
            'transporter_name' => $r['transporter_name'] ?? null,
            'vehicle_number' => $r['vehicle_number'] ?? null,
            'transport_mode' => $r['transport_mode'] ?? null,
            'document_number' => $r['document_number'] ?? null,
            'document_date' => $r['document_date'] ?? null,
            'distance_km' => $r['distance_km'] ?? null,
            'consignee_name' => $r['consignee_name'] ?? null,
            'consignee_address' => $r['consignee_address'] ?? null,
            'consignee_gstin' => $r['consignee_gstin'] ?? null,
        ];
    }

    private function receiptRow(array $r): array
    {
        return [
            'receipt_key' => $r['receipt_key'],
            'receipt_date' => $r['receipt_date'],
            'receipt_no' => $r['receipt_no'],
            'voucher_type' => $r['voucher_type'] ?? null,
            'party_name' => $r['party_name'] ?? null,
            'normalized_name' => $r['normalized_name'] ?? null,
            'party_code' => $r['party_code'] ?? null,
            'amount' => $r['amount'] ?? 0,
            'bank_ledger' => $r['bank_ledger'] ?? null,
            'reference' => $r['reference'] ?? null,
        ];
    }

    /**
     * A ledger-master sync (credit limit, GSTIN, PAN, contact), separate from sync() above: it's a small,
     * COMPLETE list every time (Tally's whole ledger master, filtered to known customers by the puller), not
     * an incremental per-day feed, so it's a genuine full-table replace -- guarded by a minimum row count so a
     * truncated/empty pull can never wipe a good table.
     */
    public function syncParties(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parties' => ['required', 'array', 'min:1'],
            'parties.*.normalized_name' => ['required', 'string'],
            'parties.*.party_name' => ['nullable', 'string'],
            'parties.*.ledger_group' => ['nullable', 'string'],
            'parties.*.credit_limit' => ['nullable', 'numeric'],
            'parties.*.gstin' => ['nullable', 'string', 'max:20'],
            'parties.*.pan' => ['nullable', 'string', 'max:20'],
            'parties.*.contact_person' => ['nullable', 'string'],
            'parties.*.contact_mobile' => ['nullable', 'string'],
        ]);

        // Guard: refuse a suspiciously small replacement (a partial/failed pull) from wiping a good table.
        $current = DB::table('tally_parties')->count();
        if ($current > 50 && count($data['parties']) < $current * 0.5) {
            return response()->json([
                'ok' => false,
                'error' => "Refusing: this push has only " . count($data['parties']) . " parties, well under the {$current} already on file. Looks like a partial pull, not a genuine full refresh.",
            ], 422);
        }

        $now = now();
        $rows = array_map(fn ($r) => [
            'normalized_name' => $r['normalized_name'],
            'party_name' => $r['party_name'] ?? null,
            'ledger_group' => $r['ledger_group'] ?? null,
            'credit_limit' => $r['credit_limit'] ?? null,
            'gstin' => $r['gstin'] ?? null,
            'pan' => $r['pan'] ?? null,
            'contact_person' => $r['contact_person'] ?? null,
            'contact_mobile' => $r['contact_mobile'] ?? null,
            'synced_at' => $now,
        ], $data['parties']);

        DB::transaction(function () use ($rows) {
            DB::table('tally_parties')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('tally_parties')->insert($chunk);
            }
        });

        return response()->json(['ok' => true, 'count' => count($rows)]);
    }
}
