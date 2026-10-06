<?php

namespace App\Http\Controllers\tenant;

use App\Enums\CrmActivityType;
use App\Http\Controllers\Controller;
use App\Models\CrmClientActivity;
use App\Models\CrmClientMapping;
use App\Models\User;
use App\Services\CompanySales;
use App\Services\CrmCustomerResolver;
use App\Services\TallyAnalytics;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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

    /** Tally figures are company-wide accounting data: the same people who may open Sales Sources, not everyone who is on this account's team. */
    protected function canSeeTallySales(): bool
    {
        // Role rules first, then the named-viewer list (TALLY_VIEWER_EMAILS): holding the role is not enough.
        return (bool) Auth::user()->hasRole(['admin', 'Admin', 'super_admin', 'hr', 'HR', 'accounts'])
            && \App\Http\Middleware\RestrictTallyAccess::permits(Auth::user());
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
        //
        // A call made outside Smart Agent and logged by hand (logCall) is saved
        // as a call ACTIVITY with no telephony_call_id, never as a dialer row, so
        // it has to be read from there too or it would never show under Calls.
        // (Dialer calls also leave a call activity, but with telephony_call_id set.)
        $manualCallQuery = fn() => $mapping->activities()->reorder()
            ->where('type', CrmActivityType::CALL->value)->whereNull('telephony_call_id');
        $callsAgentThisMonth = $mapping->telephonyCalls()
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $callsLoggedThisMonth = $manualCallQuery()
            ->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count();
        $callsThisMonthTotal = $callsAgentThisMonth + $callsLoggedThisMonth;

        $callLog = $this->callLog($mapping, $contacts);

        // Who the Log Call form offers as "our team member": the account's HRX team plus whoever is logging.
        $ourPeople = $teamByRole->flatten(1)->map(fn($m) => $m->user)->filter()->push(Auth::user())->unique('id')->values();

        // Follow-ups that were ticked off: kept on the Tasks tab so a finished task doesn't just vanish.
        $completedFollowUps = $mapping->activities()->reorder()
            ->where('needs_follow_up', true)->whereNotNull('follow_up_completed_at')
            ->with('followUpAssignedTo:id,first_name,last_name')
            ->orderByDesc('follow_up_completed_at')->limit(10)->get();

        $lastActivity = $mapping->activities()->first();

        $health = $this->computeHealthScore($mapping, $pipelines, $lastActivity, $openFollowUps);

        // Tally / Setu figures by name, only where the name certainly is this customer (see CrmCustomerResolver::keysFor).
        $co = null;
        $tallyFy = 0.0;
        $tallyFyInvoices = 0;
        $sharedName = 0;
        if ($this->canSeeTallySales()) {
            [$salesKeys, $sharedName] = app(CrmCustomerResolver::class)->keysFor($mapping);
            $co = app(CompanySales::class)->for($salesKeys);
            $fyKey = $fyStart->format('Y-m');
            foreach ($co['tally'] as $m => $lakh) {
                if ($m >= $fyKey) {
                    $tallyFy += $lakh;
                    $tallyFyInvoices += $co['tallyCount'][$m] ?? 0;
                }
            }
        }

        return view('tenant.crm-clients.profile', compact(
            'mapping', 'contacts', 'activities', 'openFollowUps', 'teamByRole', 'teamPickable',
            'pipelines', 'totalSalesFy', 'salesTrendPct', 'openOpportunityCount', 'openOpportunityValue',
            'callsThisMonthTotal', 'callsAgentThisMonth', 'callsLoggedThisMonth', 'callLog', 'ourPeople', 'completedFollowUps',
            'lastActivity', 'health', 'co', 'tallyFy', 'tallyFyInvoices', 'sharedName'
        ));
    }

    /**
     * The account's calls as plain rows -- what the Smart Agent dialer placed
     * (telephony_calls) and what people logged by hand (a call activity with no
     * telephony_call_id) -- newest first. Every row names both sides with their
     * phone and email: 'our' (the person on our team) and 'customer' (the contact
     * they spoke to; null when a hand-logged call didn't record one).
     */
    private function callLog(CrmClientMapping $mapping, $contacts)
    {
        $key = fn($n) => substr(preg_replace('/\D+/', '', (string) $n), -10);
        $byPhone = $contacts->filter(fn($c) => $c->phone)->keyBy(fn($c) => $key($c->phone));
        $ours = fn($u) => $u ? ['name' => trim($u->first_name . ' ' . $u->last_name), 'phone' => $u->phone, 'email' => $u->email] : null;
        $theirs = fn($c) => $c ? ['name' => $c->name, 'role' => $c->role?->label(), 'phone' => $c->phone, 'email' => $c->email] : null;

        $agent = $mapping->telephonyCalls()->with('agent:id,first_name,last_name,email,phone')->limit(50)->get()
            ->map(function ($c) use ($byPhone, $key, $ours, $theirs) {
                $contact = $byPhone[$key($c->to_number)] ?? null;   // the number dialled, matched to one of the customer's contacts
                $customer = $theirs($contact) ?? ['name' => null, 'role' => null, 'phone' => $c->to_number, 'email' => null];
                return [
                    'kind' => 'agent', 'at' => $c->created_at, 'ended' => $c->status?->value === 'ended',
                    'headline' => 'Smart Agent call' . ($c->duration ? ' · ' . gmdate('i:s', $c->duration) : ''), 'notes' => '',
                    'our' => $ours($c->agent), 'customer' => $customer, 'followUp' => null, 'followType' => null,
                    'chip' => $c->disposition_code ? ($c->vendor_disposition ?: 'Logged') : 'Follow-up Due',
                    'chipShort' => $c->disposition_code ? 'Logged' : 'Follow-up Due',
                    'chipClass' => $c->disposition_code ? 'logged' : 'pending',
                ];
            });

        $manual = $mapping->activities()->reorder()->where('type', CrmActivityType::CALL->value)->whereNull('telephony_call_id')
            ->with(['createdBy:id,first_name,last_name,email,phone', 'ourUser:id,first_name,last_name,email,phone', 'customerContact'])
            ->latest('id')->limit(50)->get()
            ->map(function ($a) use ($ours, $theirs) {
                [$headline, $notes] = array_pad(preg_split('/\R\R/', (string) $a->body, 2), 2, '');
                $us = $ours($a->ourUser ?? $a->createdBy);
                if ($us) {   // the summary starts with the caller's name; the row already shows them
                    $headline = preg_replace('/^' . preg_quote($us['name'], '/') . '\s+—\s+/u', '', $headline);
                }
                $headline = trim($headline);
                $followType = null;
                if (preg_match('/^(Inbound|Outbound) call, outcome: (.+?)(?: \(follow-up: (.+?)\))?$/u', $headline, $m)) {
                    $headline = $m[1] . ' call · ' . $m[2];   // the full sentence is still in the feed; here it has to fit one line
                    $followType = $m[3] ?? null;
                }
                return [
                    'kind' => 'manual', 'at' => $a->created_at, 'ended' => null,
                    'headline' => $headline, 'notes' => trim($notes), 'followType' => $followType,
                    'our' => $us, 'customer' => $theirs($a->customerContact),
                    'followUp' => $a->needs_follow_up && !$a->follow_up_completed_at ? $a->follow_up_due_at : null,
                    'chip' => 'Logged by hand', 'chipShort' => 'By hand', 'chipClass' => 'manual',
                ];
            });

        return $agent->concat($manual)->sortByDesc('at')->values();
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

    /**
     * One Tally invoice with its item lines (product, HSN, quantity, rate, taxable value, GST) for the
     * "item view" on the Sales & Revenue tab. Same people as the Tally figures themselves, and only an
     * invoice that belongs to THIS customer.
     */
    public function invoice(Request $request, $id)
    {
        $mapping = CrmClientMapping::findOrFail($id);
        abort_unless($this->canViewMapping($mapping, $request) && $this->canSeeTallySales(), 403);

        $no = trim((string) $request->query('invoice_no'));
        [$keys] = app(CrmCustomerResolver::class)->keysFor($mapping);
        abort_unless($no !== '' && $keys && DB::table('tally_invoices')->where('invoice_no', $no)->whereIn('normalized_name', $keys)->exists(), 404);

        $bill = (new TallyAnalytics(TallyAnalytics::filters([])))->bill($no);
        abort_if($bill === null, 404);
        return response()->json($bill)->header('Cache-Control', 'private, max-age=60');
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

        // Tally as a second series. A month outside the Tally export's window is null (no data), never a made-up zero.
        $tally = null;
        if ($this->canSeeTallySales()) {
            [$salesKeys] = app(CrmCustomerResolver::class)->keysFor($mapping);
            $t = app(CompanySales::class)->tallyByMonth($salesKeys);
            if ($t['byMonth']) {
                $tally = array_map(
                    fn($k) => ($t['from'] && $k >= $t['from'] && $k <= $t['to']) ? round($t['byMonth'][$k] ?? 0.0, 2) : null,
                    array_keys($byMonth)
                );
            }
        }

        return response()->json([
            'labels' => array_map(fn($k) => \Carbon\Carbon::createFromFormat('Y-m-d', $k . '-01')->format('M y'), array_keys($byMonth)),
            'values' => array_map(fn($v) => round($v, 2), array_values($byMonth)),
            'tally' => $tally,
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
            'customer_contact_id' => ['nullable', 'integer', Rule::exists('crm_client_contacts', 'id')->where('crm_client_mapping_id', $mapping->id)->whereNull('deleted_at')],
            'our_user_id'      => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // The person from our team defaults to whoever is logging; anyone else has to be on this account's team.
        $ourUserId = !empty($data['our_user_id']) ? (int) $data['our_user_id'] : (int) Auth::id();
        $allowed = $mapping->teamMembers()->pluck('user_id')->map(fn($i) => (int) $i)->push((int) Auth::id())->all();
        abort_unless(in_array($ourUserId, $allowed, true), 422, "That person isn't on this account's team.");
        $caller = User::findOrFail($ourUserId);

        $needsFollowUp = $request->boolean('set_reminder') && !empty($data['next_follow_up']);
        $summary = sprintf('%s — %s call, outcome: %s', $caller->getFullName(), $data['call_type'], $data['call_outcome']);
        if (!empty($data['follow_up_type'])) {
            $summary .= " (follow-up: {$data['follow_up_type']})";
        }

        $activity = CrmClientActivity::create([
            'crm_client_mapping_id'    => $mapping->id,
            'type'                     => CrmActivityType::CALL,
            'body'                     => "{$summary}\n\n{$data['notes']}",
            'customer_contact_id'      => $data['customer_contact_id'] ?? null,
            'our_user_id'              => $ourUserId,
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
