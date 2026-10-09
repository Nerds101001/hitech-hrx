<?php

namespace App\Http\Controllers\tenant\TallyPush;

use App\Http\Controllers\Controller;
use App\Models\TallyPushCredential;
use App\Models\TallyPushRequest;
use App\Models\TallySyncJob;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The Tally Push connection screen: the office PC's credentials + IP allowlist,
 * and proof the push is actually arriving.
 *
 * Everything here used to require an SSH session and a .env edit. A key that can
 * only be rotated from a terminal is a key that never gets rotated, and "is the
 * office PC pushing yet?" was answerable only by reading a server log file.
 */
class TallyPushConnectionController extends Controller
{
    public function index(Request $request): View
    {
        $credentials = TallyPushCredential::with('createdBy:id,first_name,last_name')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        // A secret is readable exactly once, in the response to the request that created it.
        $justIssued = $request->session()->get('tally_push_new_credential');

        return view('tenant.tally-push.admin.connection', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'credentials' => $credentials,
            'justIssued' => $justIssued,
            'endpoint' => url('/api/tally-push/sync'),
            'partiesEndpoint' => url('/api/tally-push/sync-parties'),
            'killSwitchOn' => (bool) config('tally_push.inbound.enabled'),
            'viewers' => config('tally_push.viewers', []),
            'configFallback' => $this->configFallback(),
            'stats' => $this->stats(),
            'fyMonths' => $this->financialYearMonths(),
            'jobs' => TallySyncJob::orderByDesc('requested_at')->limit(12)->get(),
        ]);
    }

    /** Each month of the current financial year up to today, newest first, for the backfill picker. */
    private function financialYearMonths(): array
    {
        $today = Carbon::today();
        $fyStart = $today->month >= 4
            ? Carbon::create($today->year, 4, 1)
            : Carbon::create($today->year - 1, 4, 1);

        $months = [];
        for ($m = $fyStart->copy(); $m->lte($today); $m->addMonth()) {
            $months[] = [
                'value' => $m->format('Y-m'),
                'label' => $m->format('F Y'),
                'from' => $m->copy()->startOfMonth()->toDateString(),
                'to' => $m->copy()->endOfMonth()->min($today)->toDateString(),
            ];
        }

        return array_reverse($months);
    }

    /** Is anything still relying on the .env credentials? The table wins if both are set. */
    private function configFallback(): array
    {
        $key = (string) config('tally_push.inbound.api_key');

        return [
            'in_use' => $key !== '' && TallyPushCredential::where('is_active', true)->doesntExist(),
            'configured' => $key !== '',
            'allowed_ips' => config('tally_push.inbound.allowed_ips', []),
        ];
    }

    /**
     * How much has actually arrived. Counts REQUESTS, not pushes: the office PC can be sending perfectly while
     * every call is refused for a bad IP or signature, and a screen that counted only successful syncs would show
     * zero and give no clue why.
     */
    private function stats(): array
    {
        $since = now()->subDays(29)->startOfDay();

        $daily = TallyPushRequest::where('received_at', '>=', $since)
            ->selectRaw('DATE(received_at) as day, status, COUNT(*) as n')
            ->groupBy('day', 'status')
            ->get()
            ->groupBy('day');

        $series = [];
        for ($d = $since->copy(); $d->lte(now()); $d->addDay()) {
            $rows = $daily->get($d->toDateString(), collect());
            $series[] = [
                'day' => $d->copy(),
                'accepted' => (int) $rows->where('status', 'accepted')->sum('n'),
                'rejected' => (int) $rows->whereIn('status', ['rejected', 'failed'])->sum('n'),
            ];
        }

        $byIp = TallyPushRequest::where('received_at', '>=', $since)
            ->selectRaw('source_ip, COUNT(*) as n, MAX(received_at) as last_seen')
            ->groupBy('source_ip')
            ->orderByDesc('n')
            ->limit(15)
            ->get();

        $lastAccepted = TallyPushRequest::where('status', 'accepted')->max('received_at');
        $lastRun = DB::table('tally_push_runs')->orderByDesc('id')->first();

        return [
            'total' => TallyPushRequest::count(),
            'today' => TallyPushRequest::whereDate('received_at', today())->count(),
            'accepted_30d' => collect($series)->sum('accepted'),
            'rejected_30d' => collect($series)->sum('rejected'),
            'series' => $series,
            'by_ip' => $byIp,
            'last_accepted' => $lastAccepted ? Carbon::parse($lastAccepted) : null,
            'last_run' => $lastRun,
        ];
    }

    // ──────────────────────────────────────────────────────────── mutations

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'allowed_ips' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $secret = TallyPushCredential::newSecret();

        $credential = TallyPushCredential::create([
            'tenant_id' => config('tally_push.default_tenant_id'),
            'label' => $data['label'],
            'api_key' => TallyPushCredential::newApiKey(),
            'secret' => $secret,
            'allowed_ips' => $data['allowed_ips'] ?: null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
            'created_by_id' => $request->user()->id,
        ]);
        $this->forget($credential);

        // The only moment the secret is readable. Flashed, not stored.
        return back()->with('tally_push_new_credential', [
            'label' => $credential->label,
            'api_key' => $credential->api_key,
            'secret' => $secret,
            'rotated' => false,
        ]);
    }

    public function rotate(Request $request, TallyPushCredential $credential): RedirectResponse
    {
        $days = (int) $request->validate([
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
        ])['grace_days'];

        $secret = $credential->rotateSecret($days);
        $this->forget($credential);

        return back()->with('tally_push_new_credential', [
            'label' => $credential->label,
            'api_key' => $credential->api_key,
            'secret' => $secret,
            'rotated' => true,
            'grace_days' => $days,
        ]);
    }

    public function update(Request $request, TallyPushCredential $credential): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'allowed_ips' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $credential->update([
            'label' => $data['label'],
            'allowed_ips' => $data['allowed_ips'] ?: null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->forget($credential);

        return back()->with('success', 'Credential updated.');
    }

    public function destroy(TallyPushCredential $credential): RedirectResponse
    {
        $this->forget($credential);
        $credential->delete();

        return back()->with('success', 'Credential deleted. Any request still using it will be refused.');
    }

    /**
     * Queue a backfill. The live server can't reach Tally, so this only RECORDS the request: the office PC's poller
     * claims it (claimJob) within a minute or two, runs hrx:tally-push for the range, and reports back (jobUpdate).
     * A multi-month request is split into one job per calendar month so each stays under the command's 31-day cap
     * and the page can show month-by-month progress. A month already waiting or running is not queued again.
     */
    public function backfill(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $today = Carbon::today();
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->startOfDay()->min($today);

        if ($from->gt($to)) {
            return back()->with('error', 'That range is entirely in the future — nothing to sync yet.');
        }

        // Months the office PC hasn't finished with yet, so clicking twice doesn't pile up duplicates.
        $open = TallySyncJob::whereIn('status', [TallySyncJob::PENDING, TallySyncJob::CLAIMED, TallySyncJob::RUNNING])
            ->pluck('label')->filter()->all();

        $queued = [];
        $skipped = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $monthFrom = $m->copy()->startOfMonth()->max($from);
            $monthTo = $m->copy()->endOfMonth()->min($to);
            $label = $m->format('F Y');

            if (in_array($label, $open, true)) {
                $skipped[] = $label;
                continue;
            }

            TallySyncJob::create([
                'tenant_id' => config('tally_push.default_tenant_id'),
                'kind' => TallySyncJob::KIND_BACKFILL,
                'from_date' => $monthFrom->toDateString(),
                'to_date' => $monthTo->toDateString(),
                'status' => TallySyncJob::PENDING,
                'label' => $label,
                'requested_by_id' => $request->user()->id,
                'requested_by_name' => $this->requesterName($request),
                'requested_at' => now(),
                'days_total' => $monthFrom->diffInDays($monthTo) + 1,
            ]);
            $queued[] = $label;
        }

        $msg = $queued
            ? 'Queued ' . implode(', ', $queued) . '. The office PC will pick '
                . (count($queued) === 1 ? 'it' : 'them') . ' up within a minute or two — this page shows the progress.'
            : 'Nothing new to queue.';
        if ($skipped) {
            $msg .= ' Already in progress: ' . implode(', ', $skipped) . '.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Queue a snapshot refresh -- the whole bill-wise outstanding (receivables) or the customer ledger master.
     * Same hand-off as backfill(): the office PC claims it and runs hrx:tally-push-outstanding / -parties. A
     * refresh of that kind already waiting isn't queued again (clicking twice does nothing extra).
     */
    public function refresh(Request $request): RedirectResponse
    {
        $kind = $request->validate([
            'kind' => ['required', 'in:' . TallySyncJob::KIND_OUTSTANDING . ',' . TallySyncJob::KIND_PARTIES],
        ])['kind'];

        $label = $kind === TallySyncJob::KIND_OUTSTANDING ? 'Outstanding' : 'Customer ledgers';

        $alreadyWaiting = TallySyncJob::where('kind', $kind)
            ->whereIn('status', [TallySyncJob::PENDING, TallySyncJob::CLAIMED, TallySyncJob::RUNNING])
            ->exists();
        if ($alreadyWaiting) {
            return back()->with('success', "A {$label} refresh is already in progress — this page shows the progress.");
        }

        TallySyncJob::create([
            'tenant_id' => config('tally_push.default_tenant_id'),
            'kind' => $kind,
            'status' => TallySyncJob::PENDING,
            'label' => $label,
            'requested_by_id' => $request->user()->id,
            'requested_by_name' => $this->requesterName($request),
            'requested_at' => now(),
        ]);

        return back()->with('success', "Queued a {$label} refresh. The office PC will pick it up within a minute or two — this page shows the progress.");
    }

    private function requesterName(Request $request): string
    {
        return trim($request->user()->first_name . ' ' . $request->user()->last_name) ?: $request->user()->email;
    }

    /** Drop the middleware's cached lookups so a change takes effect at once, not up to a minute later. */
    private function forget(TallyPushCredential $credential): void
    {
        Cache::store('file')->forget('tally_push:cred:' . hash('sha256', (string) $credential->api_key));
        Cache::store('file')->forget('tally_push:allowlist');
    }
}
