<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesPipeline;
use App\Models\SalesPipelineMonth;
use App\Models\TallyInvoice;
use App\Support\NameMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The verify/map UI for linking a canonical Customer to a Tally party —
 * badges on the Sales Report grid, driven by this controller. Mirrors
 * CustomerSetuMappingController exactly, but the source of truth is the
 * app's OWN local tally_invoices (populated by the already-live Tally push
 * jobs — see project notes "Tally push automation"), not an external API.
 * A match is only ever SUGGESTED automatically; it becomes 'verified' only
 * through mapTally(), a human action.
 */
class CustomerTallyMappingController extends Controller
{
    /** Named accounts allowed to verify/map/sync a Tally match -- see canVerify()'s docblock. */
    private const VERIFIER_EMAILS = ['ai@rustx.com', 'newbiz10@drbio.in']; // ai@rustx.com, Geet Kaur

    /**
     * Deliberately narrower than CustomerSetuMappingController::canVerify() (which this used to mirror) --
     * first narrowed to "CCare's job" (any isCcareAgent()), then per a further explicit request (2026-10-28)
     * narrowed again to two specific named accounts: ai@rustx.com and Geet Kaur. Geet's real account
     * (newbiz10@drbio.in) is in the New Biz department, not Customer Care, so isCcareAgent() would have
     * excluded her entirely -- an identity check is what was actually asked for, not a department one.
     * admin/hr keep override access, same as every other access rule in this codebase (SalesScope,
     * SalesAccessGuard, etc.) -- not a request to lock the app's own administrators out of their own data.
     * Either named account still only sees/edits customers in THEIR OWN sales book -- see
     * customerVisibleToCurrentUser()/scopedCustomerQuery(), unchanged by this, already enforces that via
     * SalesScope the same way the main Sales Report grid does.
     */
    private static function canVerify($user): bool
    {
        if (!$user) {
            return false;
        }
        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR'])
            || in_array(strtolower((string) $user->email), array_map('strtolower', self::VERIFIER_EMAILS), true);
    }

    private const FUZZY_FLOOR = 80.0;

    /** How many trailing months to show/sum in the "recent" figures, same window shape as Setu's cache. */
    private const RECENT_MONTHS = 6;

    /**
     * Security boundary for this controller: search()/salesPool() deliberately stay company-wide
     * (the whole point is finding a match among ALL Tally parties, same as SalesScope::canClaimCustomer's
     * documented "search the whole customer database" carve-out) -- but every endpoint that reads or
     * writes a SPECIFIC customer's mapping/sales figures must first confirm that customer is actually
     * one this user is allowed to see, via the same SalesScope ownership rule the main Sales Report grid
     * already enforces (admin/hr/sales_ops.view_all unrestricted; everyone else, own book only).
     * Found missing entirely during a security audit (2026-10-28) -- preview()/mapTally()/
     * syncCurrentMonth(Preview)/bulkConfirmSuggested() previously let ANY sales-floor user view or
     * write sale_amount for ANY customer company-wide, not just their own.
     */
    private function scopedCustomerQuery()
    {
        $user = Auth::user();
        $query = Customer::query();
        if ($user && ($user->hasRole(['admin', 'Admin', 'hr', 'HR']) || $user->can('sales_ops.view_all'))) {
            return $query;
        }

        $ids = $user ? \App\Support\SalesScope::allowedUserIds($user) : [0];
        if ($ids === null) {
            return $query;
        }

        return $query->whereHas('salesPipelineRows', function ($q) use ($ids) {
            $q->where(function ($qq) use ($ids) {
                $qq->whereIn('salesperson_id', $ids)
                    ->orWhereIn('ccare_id', $ids)
                    ->orWhereIn('new_biz_id', $ids)
                    ->orWhereIn('new_biz_2_id', $ids)
                    ->orWhereIn('former_new_biz_id', $ids)
                    ->orWhereExists(function ($s) use ($ids) {
                        $s->selectRaw('1')->from('sales_pipeline_extra_reps as er')
                            ->whereColumn('er.sales_pipeline_id', 'sales_pipelines.id')
                            ->whereIn('er.user_id', $ids);
                    });
            });
        });
    }

    private function customerVisibleToCurrentUser(Customer $customer): bool
    {
        return $this->scopedCustomerQuery()->whereKey($customer->id)->exists();
    }

    /**
     * Every distinct Tally party that has actual invoiced sales, each with
     * its own recent_months/recent_total — built fresh from tally_invoices
     * rather than a separate cache table, since the data already lives
     * locally (no external API round-trip to cache against).
     */
    private function salesPool(): array
    {
        $since = now()->subMonths(self::RECENT_MONTHS - 1)->startOfMonth();

        $rows = TallyInvoice::customerSales()
            ->whereNotNull('normalized_name')
            ->where('invoice_date', '>=', $since)
            ->selectRaw("normalized_name, party_name, DATE_FORMAT(invoice_date, '%Y-%m') AS month_key, SUM(amount) AS total")
            ->groupBy('normalized_name', 'party_name', 'month_key')
            ->get();

        $pool = [];
        foreach ($rows as $row) {
            $key = $row->normalized_name;
            if (!isset($pool[$key])) {
                $pool[$key] = ['name' => $row->party_name, 'normalized_name' => $key, 'months' => []];
            }
            // Several party_name spellings can share one normalized_name — keep the longest (usually the fullest/most official one).
            if (mb_strlen($row->party_name ?? '') > mb_strlen($pool[$key]['name'] ?? '')) {
                $pool[$key]['name'] = $row->party_name;
            }
            $pool[$key]['months'][$row->month_key] = (float) ($pool[$key]['months'][$row->month_key] ?? 0) + round($row->total / 100000, 3);
        }

        return array_map(function ($p) {
            return [
                'label' => $p['name'], 'normalized_name' => $p['normalized_name'], 'name' => $p['name'],
                'recent_months' => $p['months'], 'recent_total' => round(array_sum($p['months']), 3),
            ];
        }, array_values($pool));
    }

    /**
     * Up to 5 nearest Tally parties by name, best first — shown regardless of
     * the customer's current status, so a reviewer can reconsider an
     * existing match. The currently mapped one (if any) is always included
     * and flagged, even if its score has since drifted below the floor.
     */
    private function nearestMatches(Customer $customer, array $pool): array
    {
        $matches = collect(NameMatcher::bestMatches($customer->canonical_name, $pool, self::FUZZY_FLOOR, 5));

        $currentName = $customer->tally_matched_name;
        if ($currentName && !$matches->contains('normalized_name', $currentName)) {
            $current = collect($pool)->firstWhere('normalized_name', $currentName);
            if ($current) {
                $matches->push($current + ['score' => null]);
            }
        }

        return $matches->map(fn ($m) => array_diff_key($m, ['label' => null]) + ['is_current' => $m['normalized_name'] === $currentName])
            ->sortByDesc('is_current')->values()->all();
    }

    /** What the badge's popup shows on open. */
    public function preview(Customer $customer)
    {
        if (!$this->customerVisibleToCurrentUser($customer)) {
            return Error::response('You do not have access to this customer.', 403);
        }

        return Success::response([
            'customer' => [
                'id' => $customer->id,
                'canonical_name' => $customer->canonical_name,
                'status' => $customer->tally_match_status,
                'locked' => $customer->tally_match_status === Customer::STATUS_VERIFIED,
                'verified_at' => optional($customer->tally_verified_at)->format('d M Y, h:i A'),
                'verified_by' => optional($customer->tallyVerifiedBy)->getFullName(),
            ],
            'candidates' => $this->nearestMatches($customer, $this->salesPool()),
        ]);
    }

    /** Search local Tally parties by name — backs the "search all Tally customers" box. */
    public function search(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return Success::response(['results' => []]);
        }

        $pool = collect($this->salesPool());
        $results = $pool->filter(fn ($p) => mb_stripos($p['name'] ?? '', $term) !== false)
            ->sortByDesc('recent_total')->take(15)->values()
            ->map(fn ($p) => array_diff_key($p, ['label' => null]));

        return Success::response(['results' => $results]);
    }

    /** Human confirms (or picks) the Tally party this Customer maps to. */
    public function mapTally(Request $request, Customer $customer)
    {
        if (!self::canVerify(Auth::user())) {
            return Error::response('You are not allowed to verify customer mappings.');
        }
        if (!$this->customerVisibleToCurrentUser($customer)) {
            return Error::response('You do not have access to this customer.', 403);
        }

        if ($customer->tally_match_status === Customer::STATUS_VERIFIED) {
            return Error::response('This customer is already mapped — a mapped match cannot be changed.');
        }

        $request->validate(['tally_normalized_name' => 'required|string']);
        $normalizedName = $request->input('tally_normalized_name');

        $party = collect($this->salesPool())->firstWhere('normalized_name', $normalizedName);
        if (!$party) {
            return Error::response('Could not find that Tally party anymore — it may have no recent sales.');
        }

        // One Tally party maps to exactly one Customer -- never two, and never again once verified (per
        // explicit request 2026-10-29: a verified mapping is permanent, not just "first writer wins").
        // GET_LOCK() serializes every mapTally() call for this exact normalized name across concurrent
        // requests/processes -- a plain lockForUpdate() on the conflict SELECT only locks rows that
        // already exist, so it does nothing the FIRST time two people confirm the same brand-new party
        // in the same instant (nothing to lock yet, both checks pass, both writes land). The named lock
        // closes that gap: whoever gets it first runs the whole check-then-write as one critical section,
        // so the second request's check always sees the first request's write.
        $lockKey = 'tally_map:' . $normalizedName;
        $got = DB::selectOne('SELECT GET_LOCK(?, 5) AS got', [$lockKey])->got;
        if (!$got) {
            return Error::response('Another mapping is in progress for this Tally party — please try again in a moment.');
        }

        try {
            $result = DB::transaction(function () use ($normalizedName, $customer, $party) {
                $conflict = Customer::where('tally_matched_name', $normalizedName)
                    ->where('tally_match_status', Customer::STATUS_VERIFIED)
                    ->where('id', '!=', $customer->id)
                    ->lockForUpdate()
                    ->first();
                if ($conflict) {
                    return ['conflict' => $conflict];
                }

                // Tally becomes the name of record on verify -- adopting its exact spelling is what "verified"
                // means, same convention as CustomerSetuMappingController::mapSetu(). canonical_name is the
                // identity-level name; sales_pipelines.party_name is the SEPARATE per-row field the Sales
                // Report grid's "Party Name" column actually edits (deliberately free text otherwise, per
                // Customer's own docblock, so several spellings can share one identity) -- renamed here too,
                // on every linked row, per explicit request (2026-10-28) that verifying should rename AND
                // lock what's shown in that column, not just the identity record underneath it. The grid
                // itself (index.blade.php) makes the field readonly once tally_match_status is VERIFIED.
                //
                // Company-identity fields (GSTIN/PAN/contact/email/address/mailing name) are copied down
                // from tally_ledgers here too, per explicit request (2026-10-29): once mapped, the customer
                // record should reflect Tally's own company details, not stay blank. Bills/payments/invoices
                // are NOT copied/duplicated -- they're already looked up live by normalized_name everywhere
                // (TallyInvoice, tally_outstanding, tally_payments, ...), so once tally_matched_name is set
                // they resolve automatically; there's nothing to backfill there.
                $ledger = DB::table('tally_ledgers')->where('normalized_name', $normalizedName)->first();
                $officialName = ($ledger->mailing_name ?? null) ?: $party['name'];
                $customer->update([
                    'canonical_name' => $officialName,
                    'tally_match_status' => Customer::STATUS_VERIFIED,
                    'tally_matched_name' => $normalizedName,
                    'tally_mailing_name' => $ledger->mailing_name ?? null,
                    'tally_gstin' => $ledger->gstin ?? null,
                    'tally_pan' => $ledger->pan ?? null,
                    'tally_contact_person' => $ledger->contact_person ?? null,
                    'tally_contact_mobile' => $ledger->contact_mobile ?? null,
                    'tally_email' => $ledger->email ?? null,
                    'tally_address' => $ledger->address ?? null,
                    'tally_verified_at' => now(),
                    'tally_verified_by_id' => Auth::id(),
                ]);
                $customer->salesPipelineRows()->update(['party_name' => $officialName]);

                return ['conflict' => null];
            });
        } finally {
            DB::statement('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }

        if ($result['conflict']) {
            $conflict = $result['conflict'];
            return Error::response("This Tally party is already mapped to Customer #{$conflict->id} (\"{$conflict->canonical_name}\"). One Tally party can only map to one customer.");
        }

        $filled = $this->fillCurrentMonth($customer, $party, overwrite: false);

        $message = "Mapped as \"{$customer->canonical_name}\".";
        if ($filled['count'] > 0) {
            $message .= " Also filled in {$filled['monthLabel']}'s figure (₹" . number_format($filled['amount'] - $filled['unattributed'], 2) . "L) on {$filled['count']} row(s).";
        }
        $note = $this->multiRowNote($filled);
        if ($note !== null) {
            $message .= ' ' . $note;
        }

        return Success::response([
            'customer' => ['id' => $customer->id, 'status' => $customer->tally_match_status],
            'message' => $message,
        ]);
    }

    private function currentMonthKey(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Writes this month's Tally figure into the sales_pipeline row(s) linked
     * to $customer. $overwrite=false (auto-sync-on-verify) only ever touches
     * a blank/zero current-month cell. $overwrite=true (the "Sync current
     * month" button, after a human sees the before/after) writes regardless.
     *
     * Tally gives one COMPANY-WIDE total, not a per-brand split. One linked
     * row is unambiguous. Several (e.g. one row per brand/New Biz rep for
     * the same company -- see Customer #708 "BOROSIL LIMITED", split
     * Fillezy/Dr.Bio) used to get the FULL figure written into every row,
     * double(/triple/...)-counting it in any roll-up across rows -- found
     * and fixed per explicit report (2026-10-29) that Borosil's October
     * figure was appearing twice. Fixed by attributing each brand's actual
     * Tally sales (from tally_sale_lines.product_group, item-level data) to
     * the one row that brand unambiguously belongs to -- see
     * attributeByBrand(). Per explicit follow-up request (2026-10-29): only
     * when that attribution is unambiguous; a product_group that doesn't
     * match any linked row's brand (Borosil's Oct data falls under
     * "Compostable & SC bags", not Fillezy or Dr.Bio -- neither row) is left
     * unfilled rather than guessed, and reported back as 'unattributed'.
     *
     * @return array{count:int, amount:float, monthLabel:string, skippedMultiRow:bool, unattributed:float, unattributedRows:int}
     */
    private function fillCurrentMonth(Customer $customer, array $party, bool $overwrite): array
    {
        $monthKey = $this->currentMonthKey();
        $monthLabel = now()->format('F Y');
        $amount = (float) ($party['recent_months'][$monthKey] ?? 0);

        $base = ['count' => 0, 'amount' => $amount, 'monthLabel' => $monthLabel, 'skippedMultiRow' => false, 'unattributed' => 0.0, 'unattributedRows' => 0];
        if ($amount <= 0) {
            return $base + ['amount' => 0.0];
        }

        $rows = $customer->salesPipelineRows;
        if ($rows->count() > 1) {
            return $this->fillMultiRowByBrand($rows, $customer->tally_matched_name, $monthKey, $monthLabel, $amount, $overwrite);
        }

        $count = 0;
        foreach ($rows as $pipeline) {
            if ($this->writeMonthAmount($pipeline, $monthKey, $amount, $overwrite)) {
                $count++;
            }
        }

        return $base + ['count' => $count];
    }

    /** Human-readable note about a multi-row fillCurrentMonth() result, or null if there's nothing to flag. */
    private function multiRowNote(array $filled): ?string
    {
        if ($filled['count'] === 0 && $filled['skippedMultiRow']) {
            return "This company has more than one Sales Report row — Tally only gives one company-wide total (₹" . number_format($filled['amount'], 2) . "L for {$filled['monthLabel']}), and it couldn't be matched to a specific row's brand, so it was NOT auto-filled to avoid double-counting. Enter each row's share manually.";
        }
        if ($filled['unattributed'] > 0.005) {
            return "₹" . number_format($filled['unattributed'], 2) . "L of this company's {$filled['monthLabel']} Tally sales couldn't be matched to a specific row's brand (" . $filled['unattributedRows'] . " row(s) left as-is) — enter that share manually if it belongs to this company.";
        }
        return null;
    }

    /** Writes $amount to one pipeline's $monthKey cell, respecting $overwrite's rules. Returns whether it wrote. */
    private function writeMonthAmount(SalesPipeline $pipeline, string $monthKey, float $amount, bool $overwrite): bool
    {
        $existing = $pipeline->months->firstWhere('month_year', $monthKey);
        $existingAmount = $existing ? (float) $existing->sale_amount : 0.0;

        if (!$overwrite && $existingAmount != 0.0) {
            return false;
        }
        if ($overwrite && round($existingAmount, 2) == round($amount, 2)) {
            return false;
        }

        SalesPipelineMonth::updateOrCreate(
            ['sales_pipeline_id' => $pipeline->id, 'month_year' => $monthKey],
            ['sale_amount' => $amount, 'tenant_id' => $pipeline->tenant_id]
        );
        return true;
    }

    /**
     * Multi-row attribution: one SalesPipeline per brand's own Tally sales this month, never the
     * company-wide total duplicated. Brand match reuses SalesPipelineController::brandForProductText()
     * -- the SAME alias rule the Brand filter already uses -- on both the row's own `product` field and
     * Tally's `product_group`, so "a brand match" means one consistent thing everywhere. A brand is only
     * attributed when exactly one linked row names it; a brand named by zero or >1 rows, or Tally sales
     * whose product_group names no brand any linked row has, are left out of $amount entirely and
     * surfaced via 'unattributed' instead of guessed.
     */
    private function fillMultiRowByBrand($rows, string $normalizedName, string $monthKey, string $monthLabel, float $totalAmount, bool $overwrite): array
    {
        $attribution = $this->attributeByPipeline($rows, $normalizedName, $monthKey);

        $count = 0;
        foreach ($attribution['byPipelineId'] as $pipelineId => $brandAmount) {
            $pipeline = $rows->firstWhere('id', $pipelineId);
            if ($pipeline && $this->writeMonthAmount($pipeline, $monthKey, $brandAmount, $overwrite)) {
                $count++;
            }
        }

        return [
            'count' => $count,
            'amount' => $totalAmount,
            'monthLabel' => $monthLabel,
            'skippedMultiRow' => $count === 0,
            'unattributed' => $attribution['unattributed'],
            'unattributedRows' => $attribution['unattributedRows'],
        ];
    }

    /**
     * Shared by fillMultiRowByBrand() (writes) and syncCurrentMonthPreview() (read-only display) so both
     * show/apply the exact same numbers. See fillCurrentMonth()'s docblock for the attribution rule.
     *
     * @return array{byPipelineId: array<int,float>, unattributed: float, unattributedRows: int}
     */
    private function attributeByPipeline($rows, string $normalizedName, string $monthKey): array
    {
        $rowsByBrand = [];
        foreach ($rows as $pipeline) {
            $brand = SalesPipelineController::brandForProductText($pipeline->product);
            if ($brand !== null) {
                $rowsByBrand[$brand][] = $pipeline;
            }
        }

        $monthStart = $monthKey . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $lineTotals = DB::table('tally_sale_lines')->where('normalized_name', $normalizedName)
            ->whereBetween('invoice_date', [$monthStart, $monthEnd])
            ->selectRaw('product_group, SUM(taxable_amount + IFNULL(tax_amount, 0)) as total')
            ->groupBy('product_group')->get();

        $byPipelineId = [];
        $attributedTotal = 0.0;
        foreach ($lineTotals as $lt) {
            $brand = SalesPipelineController::brandForProductText($lt->product_group);
            $rupeesL = round(((float) $lt->total) / 100000, 3); // same "lakhs" scale as salesPool()'s figures
            if ($brand !== null && isset($rowsByBrand[$brand]) && count($rowsByBrand[$brand]) === 1) {
                $pid = $rowsByBrand[$brand][0]->id;
                $byPipelineId[$pid] = ($byPipelineId[$pid] ?? 0.0) + $rupeesL;
                $attributedTotal += $rupeesL;
            }
        }

        // tally_sale_lines (item-level, used for the brand split) covers "only some invoices" per its own
        // migration docblock -- it CAN differ from the tally_invoices-based company total salesPool() uses
        // elsewhere. 'unattributed' is computed against this method's own line-level total so it always
        // reconciles internally (attributed + unattributed == every line this method actually saw); any
        // gap against the invoice-level total shows up as a difference between this and 'amount' upstream,
        // not hidden inside 'unattributed'.
        $companyTotal = (float) ($lineTotals->sum('total') / 100000);
        return [
            'byPipelineId' => $byPipelineId,
            'unattributed' => round($companyTotal - $attributedTotal, 3),
            'unattributedRows' => max(0, $rows->count() - count($byPipelineId)),
        ];
    }

    /** Read-only preview of what "Sync current month" would change, before it changes anything. */
    public function syncCurrentMonthPreview(Customer $customer)
    {
        if (!$this->customerVisibleToCurrentUser($customer)) {
            return Error::response('You do not have access to this customer.', 403);
        }
        if ($customer->tally_match_status !== Customer::STATUS_VERIFIED) {
            return Error::response('This customer is not matched to a Tally party yet.');
        }

        $party = collect($this->salesPool())->firstWhere('normalized_name', $customer->tally_matched_name);
        $monthKey = $this->currentMonthKey();
        $monthLabel = now()->format('F Y');
        $tallyAmount = $party ? (float) ($party['recent_months'][$monthKey] ?? 0) : null;

        $pipelines = $customer->salesPipelineRows->load('months');

        // Multi-row: show each row its OWN attributed "after sync" figure (or none, if its brand couldn't
        // be matched), not the same company-wide total repeated on every row -- see fillCurrentMonth()'s
        // docblock. Single row keeps the simple case: the whole figure, unambiguous.
        $attribution = $pipelines->count() > 1
            ? $this->attributeByPipeline($pipelines, $customer->tally_matched_name, $monthKey)
            : ['byPipelineId' => [], 'unattributed' => 0.0, 'unattributedRows' => 0];

        $rows = $pipelines->map(function ($pipeline) use ($monthKey, $pipelines, $tallyAmount, $attribution) {
            $existing = $pipeline->months->firstWhere('month_year', $monthKey);
            $after = $pipelines->count() > 1
                ? ($attribution['byPipelineId'][$pipeline->id] ?? null)
                : $tallyAmount;
            return [
                'pipeline_id' => $pipeline->id,
                'party_name' => $pipeline->party_name,
                'product' => $pipeline->product,
                'salesperson' => optional($pipeline->salesperson)->getFullName(),
                'current' => $existing ? (float) $existing->sale_amount : 0.0,
                'after' => $after, // null = couldn't be matched to a brand; this row won't change
            ];
        })->values();

        return Success::response([
            'month_label' => $monthLabel,
            'tally_amount' => $tallyAmount,
            'rows' => $rows,
            'multi_row' => $pipelines->count() > 1,
            'unattributed' => $attribution['unattributed'],
        ]);
    }

    /** Applies the current month's Tally figure to every linked row — the human already confirmed the diff via the preview above. */
    public function syncCurrentMonth(Customer $customer)
    {
        if (!self::canVerify(Auth::user())) {
            return Error::response('You are not allowed to verify customer mappings.');
        }
        if (!$this->customerVisibleToCurrentUser($customer)) {
            return Error::response('You do not have access to this customer.', 403);
        }
        if ($customer->tally_match_status !== Customer::STATUS_VERIFIED) {
            return Error::response('This customer is not matched to a Tally party yet.');
        }

        $party = collect($this->salesPool())->firstWhere('normalized_name', $customer->tally_matched_name);
        if (!$party) {
            return Error::response('Could not find this party in recent Tally sales — it may have no invoices in the recent window.');
        }

        $result = $this->fillCurrentMonth($customer, $party, overwrite: true);

        if ($result['amount'] <= 0) {
            return Success::response(['message' => "Tally has no sales figure for {$result['monthLabel']} for this customer.", 'count' => 0]);
        }

        $message = $result['count'] > 0
            ? "Synced {$result['monthLabel']}'s figure (₹" . number_format($result['amount'] - $result['unattributed'], 2) . "L) to {$result['count']} row(s)."
            : "Already up to date for {$result['monthLabel']}.";
        $note = $this->multiRowNote($result);
        if ($note !== null) {
            $message = $result['count'] > 0 ? ($message . ' ' . $note) : $note;
        }

        return Success::response([
            'message' => $message,
            'count' => $result['count'],
        ]);
    }

    /**
     * Writes this month's Tally figure into EVERY verified customer's linked Sales Report row(s) --
     * the exact same business logic as syncCurrentMonth() (the per-customer "Sync current month"
     * button), batched company-wide across every verified customer. Called automatically right
     * after a Tally push lands invoices for the current month (see TallyPushController::sync()),
     * so the Sales Report stays current without a human re-clicking the button on every verified
     * customer by hand. Deliberately bypasses Auth/per-user visibility -- this is a system-triggered
     * maintenance pass over every verified customer, not one user's own scoped book.
     *
     * @return array{synced:int, alreadyUpToDate:int, skippedNoParty:int, skippedZero:int, skippedMultiRow:int}
     */
    public function syncAllVerifiedCurrentMonth(): array
    {
        $pool = collect($this->salesPool())->keyBy('normalized_name');
        $totals = ['synced' => 0, 'alreadyUpToDate' => 0, 'skippedNoParty' => 0, 'skippedZero' => 0, 'skippedMultiRow' => 0];

        Customer::where('tally_match_status', Customer::STATUS_VERIFIED)->each(function (Customer $customer) use ($pool, &$totals) {
            $party = $pool->get($customer->tally_matched_name);
            if (!$party) {
                $totals['skippedNoParty']++;
                return;
            }
            $result = $this->fillCurrentMonth($customer, $party, overwrite: true);
            if ($result['amount'] <= 0) {
                $totals['skippedZero']++;
            } elseif ($result['count'] > 0) {
                $totals['synced']++;
            } elseif ($result['skippedMultiRow']) {
                $totals['skippedMultiRow']++;
            } else {
                $totals['alreadyUpToDate']++;
            }
        });

        return $totals;
    }

    /** Confirms every currently-suggested (exact-match) customer in one shot. */
    public function bulkConfirmSuggested(Request $request)
    {
        if (!self::canVerify(Auth::user())) {
            return Error::response('You are not allowed to verify customer mappings.');
        }

        // Every 'suggested' row's tally_matched_name is its OWN normalized_name (see
        // MatchCustomersToTallyCommand), which is unique on customers -- so two 'suggested' rows can't
        // collide with each other. The one thing to guard against is a 'suggested' name that was since
        // hand-picked onto a DIFFERENT customer via mapTally() (free choice there, not tied to the
        // customer's own name) -- exclude those rather than silently double-verifying a Tally party.
        $alreadyVerifiedNames = Customer::where('tally_match_status', Customer::STATUS_VERIFIED)
            ->whereNotNull('tally_matched_name')->pluck('tally_matched_name');

        $count = $this->scopedCustomerQuery()
            ->where('tally_match_status', Customer::STATUS_SUGGESTED)
            ->whereNotIn('tally_matched_name', $alreadyVerifiedNames)
            ->update([
                'tally_match_status' => Customer::STATUS_VERIFIED,
                'tally_verified_at' => now(),
                'tally_verified_by_id' => Auth::id(),
            ]);

        return Success::response(['confirmed' => $count, 'message' => "{$count} exact match(es) confirmed."]);
    }
}
