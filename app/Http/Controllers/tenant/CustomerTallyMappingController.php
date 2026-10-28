<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Customer;
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
    /** Same book-edit rule as SalesPipelineController::canEditBook() / CustomerSetuMappingController::canVerify(). */
    private static function canVerify($user): bool
    {
        if (!$user) {
            return false;
        }
        return $user->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'manager', 'Manager'])
            || $user->isCcareAgent()
            || $user->isNewBizAgent()
            || $user->can('sales_report.manage');
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
            return Error::response('This customer is already verified — a verified match cannot be changed.');
        }

        $request->validate(['tally_normalized_name' => 'required|string']);
        $normalizedName = $request->input('tally_normalized_name');

        $party = collect($this->salesPool())->firstWhere('normalized_name', $normalizedName);
        if (!$party) {
            return Error::response('Could not find that Tally party anymore — it may have no recent sales.');
        }

        $customer->update([
            'tally_match_status' => Customer::STATUS_VERIFIED,
            'tally_matched_name' => $normalizedName,
            'tally_verified_at' => now(),
            'tally_verified_by_id' => Auth::id(),
        ]);

        $filled = $this->fillCurrentMonth($customer, $party, overwrite: false);

        $message = "Verified as \"{$party['name']}\".";
        if ($filled['count'] > 0) {
            $message .= " Also filled in {$filled['monthLabel']}'s figure (₹" . number_format($filled['amount'], 2) . "L) on {$filled['count']} row(s).";
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
     * Writes this month's Tally figure into every sales_pipeline row linked
     * to $customer. $overwrite=false (auto-sync-on-verify) only ever touches
     * a blank/zero current-month cell. $overwrite=true (the "Sync current
     * month" button, after a human sees the before/after) writes every
     * linked row regardless.
     *
     * @return array{count:int, amount:float, monthLabel:string}
     */
    private function fillCurrentMonth(Customer $customer, array $party, bool $overwrite): array
    {
        $monthKey = $this->currentMonthKey();
        $monthLabel = now()->format('F Y');
        $amount = (float) ($party['recent_months'][$monthKey] ?? 0);

        if ($amount <= 0) {
            return ['count' => 0, 'amount' => 0.0, 'monthLabel' => $monthLabel];
        }

        $count = 0;
        foreach ($customer->salesPipelineRows as $pipeline) {
            $existing = $pipeline->months->firstWhere('month_year', $monthKey);
            $existingAmount = $existing ? (float) $existing->sale_amount : 0.0;

            if (!$overwrite && $existingAmount != 0.0) {
                continue;
            }
            if ($overwrite && round($existingAmount, 2) == round($amount, 2)) {
                continue;
            }

            SalesPipelineMonth::updateOrCreate(
                ['sales_pipeline_id' => $pipeline->id, 'month_year' => $monthKey],
                ['sale_amount' => $amount, 'tenant_id' => $pipeline->tenant_id]
            );
            $count++;
        }

        return ['count' => $count, 'amount' => $amount, 'monthLabel' => $monthLabel];
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

        $rows = $customer->salesPipelineRows->load('months')->map(function ($pipeline) use ($monthKey) {
            $existing = $pipeline->months->firstWhere('month_year', $monthKey);
            return [
                'pipeline_id' => $pipeline->id,
                'party_name' => $pipeline->party_name,
                'salesperson' => optional($pipeline->salesperson)->getFullName(),
                'current' => $existing ? (float) $existing->sale_amount : 0.0,
            ];
        })->values();

        return Success::response([
            'month_label' => $monthLabel,
            'tally_amount' => $tallyAmount,
            'rows' => $rows,
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

        return Success::response([
            'message' => $result['count'] > 0
                ? "Synced {$result['monthLabel']}'s figure (₹" . number_format($result['amount'], 2) . "L) to {$result['count']} row(s)."
                : "Already up to date for {$result['monthLabel']}.",
            'count' => $result['count'],
        ]);
    }

    /** Confirms every currently-suggested (exact-match) customer in one shot. */
    public function bulkConfirmSuggested(Request $request)
    {
        if (!self::canVerify(Auth::user())) {
            return Error::response('You are not allowed to verify customer mappings.');
        }

        $count = $this->scopedCustomerQuery()->where('tally_match_status', Customer::STATUS_SUGGESTED)->update([
            'tally_match_status' => Customer::STATUS_VERIFIED,
            'tally_verified_at' => now(),
            'tally_verified_by_id' => Auth::id(),
        ]);

        return Success::response(['confirmed' => $count, 'message' => "{$count} exact match(es) confirmed."]);
    }
}
