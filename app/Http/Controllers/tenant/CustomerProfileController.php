<?php

namespace App\Http\Controllers\tenant;

use App\Enums\CrmActivityType;
use App\Http\Controllers\Controller;
use App\Models\CrmClientActivity;
use App\Models\CrmClientMapping;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Customer Profile" -- a richer, tabbed alternative view of a CRM account,
 * built alongside (not replacing) crm-clients/show.blade.php. Contacts,
 * Internal Team, and the comment/follow-up feed all reuse the SAME routes
 * and controller methods as that page (CrmClientMappingController) — those
 * actions are generic over $mappingId and were never page-specific, so
 * duplicating them here would just be a second copy to keep in sync.
 *
 * What's genuinely new to this page: the tabbed Overview layout itself, the
 * sales-trend chart, a computed "health score", a manual Call Log (for a
 * call made outside Smart Agent -- a dialer-placed call is still
 * auto-logged by TelephonyCallService::disposition(), unchanged), and
 * customer Documents (a new table, nothing else in HRX had this).
 */
class CustomerProfileController extends Controller
{
    /** Same visibility rule as CrmClientMappingController — kept in sync
     *  deliberately rather than shared, since the two controllers already
     *  don't share a base class and adding one just for this would be more
     *  churn than two small, stable, identical methods. */
    protected function canManageAll(): bool
    {
        $user = Auth::user();
        return $user->hasRole(['admin', 'Admin', 'hr', 'HR', 'accounts'])
            || $user->can('crm_clients.manage');
    }

    protected function canViewMapping(CrmClientMapping $mapping, Request $request): bool
    {
        if ($this->canManageAll()) {
            return true;
        }
        $allowed = \App\Support\SalesScope::viewIds(Auth::user(), $request, 'salesperson_id');
        if ($allowed === null) {
            return true;
        }
        return in_array($mapping->salesperson_id, $allowed, true)
            || in_array($mapping->ccare_id, $allowed, true)
            || in_array($mapping->new_biz_id, $allowed, true)
            || in_array($mapping->billing_id, $allowed, true)
            || in_array($mapping->finance_id, $allowed, true);
    }

    /** April 1 of the fiscal year containing $asOf, HRX's fiscal-year convention. */
    private function fiscalYearStart(?\Carbon\Carbon $asOf = null): \Carbon\Carbon
    {
        $asOf ??= now();
        $year = $asOf->month >= 4 ? $asOf->year : $asOf->year - 1;
        return \Carbon\Carbon::create($year, 4, 1)->startOfDay();
    }

    public function show(Request $request, $id)
    {
        $mapping = CrmClientMapping::with(['salesperson', 'ccare', 'newBiz', 'billing', 'finance'])->findOrFail($id);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        $contacts = $mapping->contacts()->orderByDesc('is_primary')->orderBy('name')->get();
        $teamByRole = $mapping->teamMembersByRole();
        $teamPickable = User::where('status', 'active')->notTerminated()->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

        $activities = $mapping->activities()
            ->with(['createdBy:id,first_name,last_name', 'followUpAssignedTo:id,first_name,last_name'])
            ->paginate(20)
            ->withQueryString();

        $openFollowUps = $mapping->activities()
            ->where('needs_follow_up', true)
            ->whereNull('follow_up_completed_at')
            ->with('followUpAssignedTo:id,first_name,last_name')
            ->orderBy('follow_up_due_at')
            ->get();

        $pipelines = $mapping->salesPipelines()->with('months')->get();

        $fyStart = $this->fiscalYearStart();
        $priorFyStart = $this->fiscalYearStart($fyStart->copy()->subDay());
        $priorFyStartKey = $priorFyStart->format('Y-m');
        // Same number of calendar months into last FY as we are into this
        // one, e.g. "Apr-Aug this year" vs "Apr-Aug last year" -- an
        // honest like-for-like comparison, not a full-year one that would
        // be comparing a partial year against a complete one.
        $priorFyEndKey = $priorFyStart->copy()->addMonths($fyStart->diffInMonths(now()))->format('Y-m');

        $totalSalesFy = 0.0;
        $totalSalesPriorFyToDate = 0.0;
        $currentMonthKey = now()->format('Y-m');
        $openOpportunityCount = 0;
        $openOpportunityValue = 0.0;

        foreach ($pipelines as $p) {
            foreach ($p->months as $m) {
                if ($m->month_year >= $fyStart->format('Y-m')) {
                    $totalSalesFy += (float) $m->sale_amount;
                }
                if ($m->month_year >= $priorFyStartKey && $m->month_year <= $priorFyEndKey) {
                    $totalSalesPriorFyToDate += (float) $m->sale_amount;
                }
                if ($m->month_year === $currentMonthKey && (float) $m->forecast_amount > 0) {
                    $openOpportunityCount++;
                    $openOpportunityValue += (float) $m->forecast_amount;
                }
            }
        }

        $salesTrendPct = $totalSalesPriorFyToDate > 0
            ? round((($totalSalesFy - $totalSalesPriorFyToDate) / $totalSalesPriorFyToDate) * 100, 1)
            : null; // no prior-period data at all -- nothing honest to compare against

        // telephony_calls only ever tracks a call an HRX agent placed through
        // Smart Agent -- there's no inbound-call tracking in this schema, so
        // this is a total, not an inbound/outbound split (nothing here would
        // honestly back that split).
        $callsThisMonthTotal = $mapping->telephonyCalls()
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();

        $lastActivity = $mapping->activities()->first();
        $recentCalls = $mapping->telephonyCalls()->with('agent:id,first_name,last_name')->limit(6)->get();

        $health = $this->computeHealthScore($mapping, $pipelines, $lastActivity, $openFollowUps);

        return view('tenant.crm-clients.profile', compact(
            'mapping', 'contacts', 'activities', 'openFollowUps', 'teamByRole', 'teamPickable',
            'pipelines', 'totalSalesFy', 'salesTrendPct', 'openOpportunityCount', 'openOpportunityValue',
            'callsThisMonthTotal',
            'lastActivity', 'recentCalls', 'health'
        ));
    }

    /**
     * A plain, explainable heuristic -- not a black box. Starts at a neutral
     * 65 and moves on a handful of signals we actually have: how recently
     * someone talked to this account, whether sales are trending up or
     * silent, and whether a follow-up has been left overdue. A "Dropped"
     * stage overrides everything else, because no amount of past activity
     * makes a dropped account healthy.
     *
     * @return array{score:int, label:string, reasons:array<int,string>}
     */
    private function computeHealthScore(CrmClientMapping $mapping, $pipelines, ?CrmClientActivity $lastActivity, $openFollowUps): array
    {
        if ($mapping->crm_stage && stripos($mapping->crm_stage, 'drop') !== false) {
            return ['score' => 15, 'label' => 'At Risk', 'reasons' => ['Account is marked "' . $mapping->crm_stage . '"']];
        }

        $score = 65;
        $reasons = [];

        $daysSinceActivity = $lastActivity ? $lastActivity->created_at->diffInDays(now()) : null;
        if ($daysSinceActivity === null) {
            $score -= 15;
        } elseif ($daysSinceActivity <= 14) {
            $score += 15;
            $reasons[] = 'Regular communication';
        } elseif ($daysSinceActivity > 90) {
            $score -= 20;
            $reasons[] = 'No recent contact';
        }

        // Last 2 calendar months' combined sale total vs the 2 before that --
        // simple direction-of-travel, not a formal forecast.
        $byMonth = [];
        foreach ($pipelines as $p) {
            foreach ($p->months as $m) {
                $byMonth[$m->month_year] = ($byMonth[$m->month_year] ?? 0) + (float) $m->sale_amount;
            }
        }
        krsort($byMonth);
        $recentMonths = array_slice($byMonth, 0, 2, true);
        $priorMonths = array_slice($byMonth, 2, 2, true);
        $recentTotal = array_sum($recentMonths);
        $priorTotal = array_sum($priorMonths);

        if ($recentTotal > 0 && $recentTotal >= $priorTotal) {
            $score += 15;
            $reasons[] = 'Growing opportunities';
        } elseif ($recentTotal <= 0 && $priorTotal > 0) {
            $score -= 15;
            $reasons[] = 'Sales have gone quiet';
        }

        $overdue = $openFollowUps->filter(fn($a) => $a->follow_up_due_at && $a->follow_up_due_at->isPast());
        if ($overdue->isNotEmpty()) {
            $score -= 20;
            $reasons[] = 'Overdue follow-up';
        }

        $score = max(0, min(100, $score));
        $label = $score >= 75 ? 'Strong relationship' : ($score >= 50 ? 'Stable' : 'At Risk');
        if (empty($reasons)) {
            $reasons[] = 'Not enough activity yet to say more';
        }

        return ['score' => $score, 'label' => $label, 'reasons' => array_slice($reasons, 0, 3)];
    }

    /** Sales-trend chart data. $range is 6M|1Y|2Y, most recent month last. */
    public function salesTrend(Request $request, $id)
    {
        $mapping = CrmClientMapping::findOrFail($id);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        $months = match ($request->get('range', '1Y')) {
            '6M' => 6,
            '2Y' => 24,
            default => 12,
        };

        $keys = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $keys[] = now()->copy()->subMonths($i)->format('Y-m');
        }

        $byMonth = array_fill_keys($keys, 0.0);
        foreach ($mapping->salesPipelines()->with('months')->get() as $p) {
            foreach ($p->months as $m) {
                if (array_key_exists($m->month_year, $byMonth)) {
                    $byMonth[$m->month_year] += (float) $m->sale_amount;
                }
            }
        }

        return response()->json([
            'labels' => array_map(fn($k) => \Carbon\Carbon::createFromFormat('Y-m-d', $k . '-01')->format('M y'), array_keys($byMonth)),
            'values' => array_map(fn($v) => round($v, 2), array_values($byMonth)),
        ]);
    }

    /**
     * Manual call log entry -- for a call made outside Smart Agent (a
     * personal phone, a walk-in, a call another system placed). A
     * dialer-placed call is still auto-logged by
     * TelephonyCallService::disposition(); this does NOT create a
     * telephony_calls row, only an activity-feed entry, since HRX never
     * actually placed or tracked this call.
     */
    public function logCall(Request $request, $mappingId)
    {
        $mapping = CrmClientMapping::findOrFail($mappingId);
        if (!$this->canViewMapping($mapping, $request)) {
            abort(403);
        }

        $data = $request->validate([
            'call_outcome'     => ['required', 'string', 'max:60'],
            'call_type'        => ['required', 'in:Inbound,Outbound'],
            'notes'            => ['required', 'string'],
            'next_follow_up'   => ['nullable', 'date'],
            'follow_up_type'   => ['nullable', 'string', 'max:60'],
            'set_reminder'     => ['nullable', 'boolean'],
        ]);

        $needsFollowUp = $request->boolean('set_reminder') && !empty($data['next_follow_up']);
        $summary = sprintf('%s — %s call, outcome: %s', Auth::user()->getFullName(), $data['call_type'], $data['call_outcome']);
        if (!empty($data['follow_up_type'])) {
            $summary .= " (follow-up: {$data['follow_up_type']})";
        }

        $activity = CrmClientActivity::create([
            'crm_client_mapping_id'    => $mapping->id,
            'type'                     => CrmActivityType::CALL,
            'body'                     => "{$summary}\n\n{$data['notes']}",
            'needs_follow_up'          => $needsFollowUp,
            'follow_up_due_at'         => $needsFollowUp ? $data['next_follow_up'] : null,
            'follow_up_assigned_to_id' => $needsFollowUp ? Auth::id() : null,
            'created_by_id'            => Auth::id(),
            'tenant_id'                => $mapping->tenant_id,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Call logged.',
            'activity' => $activity->load(['createdBy:id,first_name,last_name', 'followUpAssignedTo:id,first_name,last_name']),
        ]);
    }

    // storeDocument()/destroyDocument() (Documents tab) were built and
    // pulled for now -- CrmClientDocument and its migration are still there
    // so this can come back without rebuilding the schema.
}
