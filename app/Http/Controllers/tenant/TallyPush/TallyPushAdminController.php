<?php

namespace App\Http\Controllers\tenant\TallyPush;

use App\Http\Controllers\Controller;
use App\Models\TallyPushRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The Tally Push request log: every inbound hit on the push endpoint, accepted
 * or rejected, with its time, source IP and reason. Settles "the office PC is
 * pushing" / "nothing is arriving, and here is why".
 */
class TallyPushAdminController extends Controller
{
    public function requestLog(Request $request): View
    {
        $requests = TallyPushRequest::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('ip'), fn ($q) => $q->where('source_ip', $request->query('ip')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->query('q') . '%';
                $q->where(fn ($w) => $w->where('source_ip', 'like', $term)
                    ->orWhere('endpoint', 'like', $term)
                    ->orWhere('error', 'like', $term));
            })
            ->orderByDesc('received_at')
            ->paginate(40)
            ->withQueryString();

        $counts = TallyPushRequest::selectRaw('status, COUNT(*) as n')
            ->groupBy('status')->pluck('n', 'status');

        // Refusals grouped by reason + source IP: the useful question is never "which request failed" but "what
        // is systematically wrong". A wrong allowlist entry shows up here as one row with a large count. Grouped
        // in PHP (not SUBSTRING_INDEX) so it works on SQLite too; the set of distinct reasons is small.
        $rejections = TallyPushRequest::whereIn('status', ['rejected', 'failed'])
            ->where('received_at', '>=', now()->subDays(7))
            ->selectRaw('source_ip, error, COUNT(*) as n, MAX(received_at) as last_seen')
            ->groupBy('source_ip', 'error')
            ->get()
            ->groupBy(fn ($r) => $r->source_ip . '|' . strtok((string) $r->error, ':'))
            ->map(fn ($group) => (object) [
                'source_ip' => $group->first()->source_ip,
                'reason' => strtok((string) $group->first()->error, ':'),
                'n' => $group->sum('n'),
                'last_seen' => $group->max('last_seen'),
            ])
            ->sortByDesc('n')
            ->values();

        return view('tenant.tally-push.admin.request-log', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'requests' => $requests,
            'counts' => $counts,
            'rejections' => $rejections,
            // The last ACCEPTED request, not the last of any kind: a stream of refusals must not make the
            // "last push received" tile look healthy.
            'lastReceived' => TallyPushRequest::where('status', 'accepted')->max('received_at'),
            'filters' => $request->only(['status', 'ip', 'q']),
        ]);
    }

    /** One request's detail: headers and (for a refusal) the bounded body excerpt. */
    public function showRequest(TallyPushRequest $tallyRequest): View
    {
        $decoded = json_decode((string) $tallyRequest->payload, true);

        return view('tenant.tally-push.admin.request-detail', [
            'pageConfigs' => ['contentLayout' => 'wide'],
            'request' => $tallyRequest,
            'pretty' => $tallyRequest->payload === null
                ? null
                : (json_last_error() === JSON_ERROR_NONE && $decoded !== null
                    ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : (string) $tallyRequest->payload),
        ]);
    }
}
