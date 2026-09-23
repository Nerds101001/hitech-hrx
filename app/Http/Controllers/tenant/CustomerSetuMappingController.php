<?php

namespace App\Http\Controllers\tenant;

use App\ApiClasses\Error;
use App\ApiClasses\Success;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalesPipelineMonth;
use App\Models\SetuCustomer;
use App\Support\NameMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The verify/map UI for linking a canonical Customer to a Setu (Tally)
 * customer — badges on the Sales Report grid, driven by this controller.
 * Same rule as everywhere else in this reconciliation: a match is only ever
 * SUGGESTED automatically (see MatchCustomersToSetuCommand); it becomes
 * 'verified' only through mapSetu(), a human action.
 */
class CustomerSetuMappingController extends Controller
{
    /** Same book-edit rule as SalesPipelineController::canEditBook(). */
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

    private function recentMonthsPayload(?SetuCustomer $setu): ?array
    {
        if (!$setu) {
            return null;
        }
        return [
            'id' => $setu->id,
            'name' => $setu->name,
            'recent_months' => $setu->recent_months ?? [],
            'recent_total' => (float) $setu->recent_total,
        ];
    }

    /** Same floor SetuReportService::fuzzyCandidates() uses for "worth showing
     *  a human" — kept consistent rather than inventing a second quality bar. */
    private const FUZZY_FLOOR = 80.0;

    /**
     * Up to 5 nearest Setu customers by name, best first — shown regardless of
     * the customer's current status (none/suggested/verified), so a reviewer
     * can reconsider an existing match, not just accept-or-search-blind. The
     * currently mapped one (if any) is always included and flagged, even if
     * its score has since drifted below the floor.
     */
    private function nearestMatches(Customer $customer): array
    {
        $pool = SetuCustomer::all(['id', 'name', 'recent_months', 'recent_total'])
            ->map(fn ($s) => [
                'label' => $s->name, 'id' => $s->id, 'name' => $s->name,
                'recent_months' => $s->recent_months ?? [], 'recent_total' => (float) $s->recent_total,
            ])->all();

        $matches = collect(NameMatcher::bestMatches($customer->canonical_name, $pool, self::FUZZY_FLOOR, 5));

        $currentName = $customer->setu_matched_name;
        if ($currentName && !$matches->contains('name', $currentName)) {
            $current = collect($pool)->firstWhere('name', $currentName);
            if ($current) {
                $matches->push($current + ['score' => null]);
            }
        }

        return $matches->map(fn ($m) => array_diff_key($m, ['label' => null]) + ['is_current' => $m['name'] === $currentName])
            ->sortByDesc('is_current')->values()->all();
    }

    /** What the badge's popup shows on open: the customer's current state
     *  plus its nearest Setu candidates (whatever the current status). */
    public function preview(Customer $customer)
    {
        return Success::response([
            'customer' => [
                'id' => $customer->id,
                'canonical_name' => $customer->canonical_name,
                'status' => $customer->setu_match_status,
                // Once verified, nobody re-matches or re-maps this customer —
                // the frontend uses this to drop every "Match this" action
                // (candidates and search both), leaving only Sync + Close.
                'locked' => $customer->setu_match_status === Customer::STATUS_VERIFIED,
                'verified_at' => optional($customer->setu_verified_at)->format('d M Y, h:i A'),
                'verified_by' => optional($customer->verifiedBy)->getFullName(),
            ],
            'candidates' => $this->nearestMatches($customer),
        ]);
    }

    /** Search the local Setu cache — backs the "search all Setu customers" box. */
    public function search(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return Success::response(['results' => []]);
        }

        $results = SetuCustomer::where('name', 'like', "%{$term}%")
            ->orderByDesc('recent_total')
            ->limit(15)
            ->get()
            ->map(fn ($s) => $this->recentMonthsPayload($s))
            ->values();

        return Success::response(['results' => $results]);
    }

    /** Human confirms (or picks) the Setu customer this maps to. */
    public function mapSetu(Request $request, Customer $customer)
    {
        if (!self::canVerify(Auth::user())) {
            return Error::response('You are not allowed to verify customer mappings.');
        }

        // Locked the moment a human confirms it -- no redo, no re-mapping to
        // a different Setu customer. A genuine mistake needs an admin fixing
        // the row directly (and it'll show in the audit trail either way),
        // not a button that makes "verified" mean "still provisional".
        if ($customer->setu_match_status === Customer::STATUS_VERIFIED) {
            return Error::response('This customer is already verified — a verified match cannot be changed.');
        }

        $request->validate(['setu_customer_id' => 'required|exists:setu_customers,id']);
        $setu = SetuCustomer::findOrFail($request->setu_customer_id);

        // Setu (Tally) becomes the name of record on verify -- adopting its
        // exact spelling is what "verified" means. normalized_name is left
        // alone: it's already equal-or-fuzzy to Setu's own normalized form,
        // so recomputing it here can't collide with another customer's row.
        $customer->update([
            'canonical_name' => $setu->name,
            'setu_match_status' => Customer::STATUS_VERIFIED,
            'setu_matched_name' => $setu->name,
            'setu_verified_at' => now(),
            'setu_verified_by_id' => Auth::id(),
        ]);

        // Auto-sync, but only ever a BLANK cell -- matching is a strong enough
        // signal to fill in a month nobody has entered yet, not to correct one
        // someone already typed. That stays the "Sync current month" button's
        // job, where a human sees the before/after and confirms it themselves.
        $filled = $this->fillCurrentMonth($customer, $setu, overwrite: false);

        $message = "Verified as \"{$setu->name}\".";
        if ($filled['count'] > 0) {
            $message .= " Also filled in {$filled['monthLabel']}'s figure (₹" . number_format($filled['amount'], 2) . "L) on {$filled['count']} row(s).";
        }

        return Success::response([
            'customer' => ['id' => $customer->id, 'status' => $customer->setu_match_status],
            'message' => $message,
        ]);
    }

    private function currentMonthKey(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Writes this month's Setu figure into every sales_pipeline row linked to
     * $customer. $overwrite=false (the auto-sync-on-verify path) only ever
     * touches a row whose current-month cell is blank/zero -- never corrects
     * a figure someone already entered. $overwrite=true (the "Sync current
     * month" button, after a human has seen the before/after) writes every
     * linked row regardless.
     *
     * @return array{count:int, amount:float, monthLabel:string}
     */
    private function fillCurrentMonth(Customer $customer, SetuCustomer $setu, bool $overwrite): array
    {
        $monthKey = $this->currentMonthKey();
        $monthLabel = now()->format('F Y');
        $amount = (float) (($setu->recent_months ?? [])[$monthKey] ?? 0);

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
                continue; // already matches -- nothing to write
            }

            SalesPipelineMonth::updateOrCreate(
                ['sales_pipeline_id' => $pipeline->id, 'month_year' => $monthKey],
                ['sale_amount' => $amount, 'tenant_id' => $pipeline->tenant_id]
            );
            $count++;
        }

        return ['count' => $count, 'amount' => $amount, 'monthLabel' => $monthLabel];
    }

    /**
     * What the "Sync current month" button shows before it does anything --
     * this customer's linked Sales Report row(s) alongside Setu's figure for
     * the current month, so a human can see exactly what would change before
     * confirming. Read-only; syncCurrentMonth() below is the only endpoint
     * that writes.
     */
    public function syncCurrentMonthPreview(Customer $customer)
    {
        if ($customer->setu_match_status !== Customer::STATUS_VERIFIED) {
            return Error::response('This customer is not matched to a Setu customer yet.');
        }

        $setu = SetuCustomer::where('name', $customer->setu_matched_name)->first();
        $monthKey = $this->currentMonthKey();
        $monthLabel = now()->format('F Y');
        $setuAmount = $setu ? (float) (($setu->recent_months ?? [])[$monthKey] ?? 0) : null;

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
            'setu_amount' => $setuAmount,
            'setu_last_synced_at' => optional($setu?->last_synced_at)->format('d M Y, h:i A'),
            'rows' => $rows,
        ]);
    }

    /** Applies the current month's Setu figure to every linked row -- the human already confirmed the diff via the preview above. */
    public function syncCurrentMonth(Customer $customer)
    {
        if (!self::canVerify(Auth::user())) {
            return Error::response('You are not allowed to verify customer mappings.');
        }
        if ($customer->setu_match_status !== Customer::STATUS_VERIFIED) {
            return Error::response('This customer is not matched to a Setu customer yet.');
        }

        $setu = SetuCustomer::where('name', $customer->setu_matched_name)->first();
        if (!$setu) {
            return Error::response('Could not find this customer in the Setu cache — try re-syncing Setu customers first.');
        }

        $result = $this->fillCurrentMonth($customer, $setu, overwrite: true);

        if ($result['amount'] <= 0) {
            return Success::response(['message' => "Setu has no sales figure for {$result['monthLabel']} for this customer.", 'count' => 0]);
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

        // A single bulk UPDATE, not a loop of individual saves — with several
        // thousand suggested rows, looping model saves risked the request
        // outrunning the server's execution/timeout limit and leaving the
        // confirm half-applied (caught locally: 2,364 queued, only 1,439 made
        // it through before the request gave up).
        //
        // Trade-off worth knowing about: a bulk query update() never fires
        // Eloquent model events, so these rows get NO per-row audit trail
        // entry (unlike mapSetu(), which saves through the model). Anyone
        // auditing "who verified X" should check whether X was bulk-confirmed
        // (no entry) or individually mapped (full who/when/rename entry).
        $count = Customer::where('setu_match_status', Customer::STATUS_SUGGESTED)->update([
            // 'suggested' already means an exact normalized-name match, so
            // this is purely a spelling/casing adoption, same as mapSetu().
            'canonical_name' => DB::raw('setu_matched_name'),
            'setu_match_status' => Customer::STATUS_VERIFIED,
            'setu_verified_at' => now(),
            'setu_verified_by_id' => Auth::id(),
        ]);

        return Success::response(['confirmed' => $count, 'message' => "{$count} exact match(es) confirmed."]);
    }
}
