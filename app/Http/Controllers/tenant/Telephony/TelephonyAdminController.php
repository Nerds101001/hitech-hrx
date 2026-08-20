<?php

namespace App\Http\Controllers\tenant\Telephony;

use App\Http\Controllers\Controller;
use App\Jobs\Telephony\ProcessTelephonyEvent;
use App\Models\Telephony\TelephonyAgentMap;
use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyReconciliationRun;
use App\Models\Telephony\TelephonyWebhookDelivery;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The four screens that keep the integration honest.
 *
 * Webhook Log settles "we sent it" / "we never got it". Agent Mapping is the
 * spine -- an unmapped agent is where every integration of this kind goes
 * wrong first. Reconciliation is the nightly audit, and Exceptions is the
 * queue of calls that arrived but could not be matched to an employee.
 */
class TelephonyAdminController extends Controller
{
  // ────────────────────────────────────────────────────────── Webhook Log

  public function webhookLog(Request $request): View
  {
    $deliveries = TelephonyWebhookDelivery::query()
      ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
      ->when($request->filled('endpoint'), fn ($q) => $q->where('endpoint', $request->query('endpoint')))
      ->when($request->filled('q'), function ($q) use ($request) {
        $term = '%' . $request->query('q') . '%';
        $q->where(fn ($w) => $w->where('call_uid', 'like', $term)
          ->orWhere('event_id', 'like', $term)
          ->orWhere('event_type', 'like', $term));
      })
      ->orderByDesc('received_at')
      ->paginate(30)
      ->withQueryString();

    $counts = TelephonyWebhookDelivery::selectRaw('status, COUNT(*) as n')
      ->groupBy('status')->pluck('n', 'status');

    // Refusals happen in middleware, before anything is interpreted, and are
    // grouped by reason and source IP because the useful question is never
    // "which request failed" but "what is systematically wrong". A wrong
    // allowlist entry shows up here as one row with a large count.
    // Grouped in PHP rather than with SUBSTRING_INDEX, which is MySQL-only
    // and would break the moment anyone pointed a test at SQLite. The set of
    // distinct error strings is small and bounded, so this aggregates a
    // handful of rows rather than a table scan's worth.
    $rejections = TelephonyWebhookDelivery::where('status', 'rejected')
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

    return view('tenant.telephony.admin.webhook-log', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'deliveries' => $deliveries,
      'counts' => $counts,
      'rejections' => $rejections,
      // Deliberately the last ACCEPTED delivery, not the last of any kind: a
      // stream of refusals must not make the "last event received" tile look
      // healthy.
      'lastReceived' => TelephonyWebhookDelivery::where('status', '!=', 'rejected')->max('received_at'),
      'filters' => $request->only(['status', 'endpoint', 'q']),
    ]);
  }

  /** The raw payload, pretty-printed. This is the argument-settler. */
  public function showDelivery(TelephonyWebhookDelivery $delivery): View
  {
    $decoded = json_decode((string) $delivery->payload, true);

    return view('tenant.telephony.admin.delivery-detail', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'delivery' => $delivery,
      'pretty' => json_last_error() === JSON_ERROR_NONE
        ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        // Keep the raw bytes visible when it will not parse -- an unparseable
        // payload is exactly the one worth looking at.
        : (string) $delivery->payload,
    ]);
  }

  /**
   * Re-run normalisation for a delivery whose first attempt failed.
   *
   * The row keeps the original bytes, so this replays what the vendor actually
   * sent rather than anything we reconstructed. Normalisation is idempotent,
   * so replaying a delivery that already succeeded is harmless.
   */
  public function replayDelivery(TelephonyWebhookDelivery $delivery): RedirectResponse
  {
    $delivery->forceFill(['status' => 'queued', 'error' => null])->save();

    try {
      ProcessTelephonyEvent::dispatch($delivery->id)->onQueue(config('telephony.queue'));
    } catch (Throwable $e) {
      return back()->with('error', 'Could not replay: ' . $e->getMessage());
    }

    return back()->with('success', "Delivery #{$delivery->id} was queued for reprocessing.");
  }

  // ─────────────────────────────────────────────────────── Agent Mapping

  public function agentMap(Request $request): View
  {
    $rows = TelephonyAgentMap::query()
      ->with('user:id,first_name,last_name,code,email')
      ->when($request->filled('q'), function ($q) use ($request) {
        $term = '%' . $request->query('q') . '%';
        $q->where(fn ($w) => $w->where('agent_id', 'like', $term)
          ->orWhere('agent_code', 'like', $term)
          ->orWhere('extension', 'like', $term)
          ->orWhere('agent_number', 'like', $term));
      })
      ->orderByDesc('is_active')
      ->orderBy('extension')
      ->paginate(30)
      ->withQueryString();

    return view('tenant.telephony.admin.agent-map', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'rows' => $rows,
      // Only users not already mapped, so the dropdown cannot create the
      // duplicate the unique index would reject anyway.
      'users' => User::whereNotIn('id', TelephonyAgentMap::pluck('user_id'))
        ->orderBy('first_name')
        ->get(['id', 'first_name', 'last_name', 'code']),
      'unmappedCalls' => TelephonyCall::where('is_unassigned', true)->count(),
      'filters' => $request->only(['q']),
    ]);
  }

  public function storeAgent(Request $request): RedirectResponse
  {
    $data = $this->validateAgent($request);
    $data['tenant_id'] = config('telephony.inbound.default_tenant_id');
    $data['created_by_id'] = $request->user()->id;

    $row = TelephonyAgentMap::create($data);

    // A new mapping is worth applying to calls that already arrived unassigned
    // -- otherwise yesterday's calls stay orphaned even though we now know who
    // made them, and the exceptions screen never empties.
    $claimed = $this->claimUnassigned($row);

    return back()->with('success', $claimed > 0
      ? "Mapping saved. {$claimed} previously unassigned call(s) were matched to this employee."
      : 'Mapping saved.');
  }

  public function updateAgent(Request $request, TelephonyAgentMap $agentMap): RedirectResponse
  {
    $data = $this->validateAgent($request, $agentMap->id);
    $data['updated_by_id'] = $request->user()->id;

    $agentMap->update($data);
    $claimed = $this->claimUnassigned($agentMap);

    return back()->with('success', $claimed > 0
      ? "Mapping updated. {$claimed} previously unassigned call(s) were matched."
      : 'Mapping updated.');
  }

  /**
   * Soft-delete only.
   *
   * Calls already attributed to this employee keep their user_id -- history
   * must not silently rewrite itself because someone changed desks.
   */
  public function destroyAgent(TelephonyAgentMap $agentMap): RedirectResponse
  {
    $agentMap->delete();

    return back()->with('success', 'Mapping removed. Existing call history is unchanged.');
  }

  /**
   * @return array<string,mixed>
   */
  private function validateAgent(Request $request, ?int $ignoreId = null): array
  {
    return $request->validate([
      'user_id' => [
        'required', 'integer', 'exists:users,id',
        Rule::unique('telephony_agent_map', 'user_id')->ignore($ignoreId)->whereNull('deleted_at'),
      ],
      'agent_id' => [
        'nullable', 'string', 'max:191',
        Rule::unique('telephony_agent_map', 'agent_id')->ignore($ignoreId)->whereNull('deleted_at'),
      ],
      'agent_code' => ['nullable', 'string', 'max:191'],
      'extension' => [
        'nullable', 'string', 'max:191',
        Rule::unique('telephony_agent_map', 'extension')->ignore($ignoreId)->whereNull('deleted_at'),
      ],
      'agent_number' => ['nullable', 'string', 'max:191'],
      'default_campaign_id' => ['nullable', 'string', 'max:191'],
      'default_campaign_name' => ['nullable', 'string', 'max:191'],
      'is_active' => ['nullable', 'boolean'],
      'notes' => ['nullable', 'string', 'max:2000'],
    ] + [], [
      'user_id.unique' => 'That employee already has a dialer mapping.',
      'agent_id.unique' => 'That dialer agent id is already mapped to someone else.',
      'extension.unique' => 'That extension is already mapped to someone else.',
    ]);
  }

  /**
   * Attach calls that arrived before this mapping existed.
   *
   * Matched on whichever identifiers the mapping actually carries; a blank
   * extension must not match every call whose extension is also blank.
   */
  private function claimUnassigned(TelephonyAgentMap $row): int
  {
    $identifiers = array_filter([
      'agent_id' => $row->agent_id,
      'agent_code' => $row->agent_code,
      'extension' => $row->extension,
      'agent_number' => $row->agent_number,
    ], fn ($v) => $v !== null && $v !== '');

    if ($identifiers === [] || !$row->is_active) {
      return 0;
    }

    return TelephonyCall::where('is_unassigned', true)
      ->where(function ($q) use ($identifiers) {
        foreach ($identifiers as $column => $value) {
          $q->orWhere($column, $value);
        }
      })
      ->update(['user_id' => $row->user_id, 'is_unassigned' => false]);
  }

  // ────────────────────────────────────────────────────── Reconciliation

  public function reconciliation(Request $request): View
  {
    $runs = TelephonyReconciliationRun::orderByDesc('period_date')
      ->paginate(30)
      ->withQueryString();

    // "Seven consecutive zero-gap days" is the acceptance criterion, so count
    // the current streak rather than a total that a single old pass inflates.
    $recent = TelephonyReconciliationRun::orderByDesc('period_date')->limit(30)->get();
    $streak = 0;
    foreach ($recent as $run) {
      if ($run->result === 'pass') {
        $streak++;
        continue;
      }
      break;
    }

    return view('tenant.telephony.admin.reconciliation', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'runs' => $runs,
      'streak' => $streak,
      'lastRun' => $recent->first(),
    ]);
  }

  /**
   * Run the audit on demand.
   *
   * Runs inline. It is an admin pressing a button and waiting for the answer,
   * and the command is bounded by the gateway's own page size.
   */
  public function runReconciliation(Request $request): RedirectResponse
  {
    $date = $request->validate([
      'date' => ['required', 'date', 'before_or_equal:today'],
    ])['date'];

    try {
      Artisan::call('telephony:reconcile', ['--date' => $date]);
    } catch (Throwable $e) {
      return back()->with('error', 'Reconciliation could not run: ' . $e->getMessage());
    }

    $run = TelephonyReconciliationRun::where('period_date', $date)->first();

    // A non-zero exit is the command reporting a gap, which is a finding, not
    // a crash -- so report the finding rather than an error.
    return back()->with(
      $run && $run->result === 'pass' ? 'success' : 'error',
      $run
        ? "Reconciliation for {$date}: {$run->result}. Vendor {$run->vendor_count}, HRX {$run->local_count}, gap {$run->count_gap}, backfilled {$run->backfilled}."
        : "Reconciliation for {$date} produced no result row."
    );
  }

  // ───────────────────────────────────────────────────────── Exceptions

  /**
   * Calls that arrived but could not be matched to an employee.
   *
   * These are never dropped, precisely so they show up here: a dropped call is
   * invisible, an unassigned one gets fixed. The identifiers are grouped so the
   * fix is usually one new mapping rather than one edit per call.
   */
  public function exceptions(Request $request): View
  {
    $calls = TelephonyCall::where('is_unassigned', true)
      ->orderByDesc('initiated_at')
      ->paginate(30)
      ->withQueryString();

    $groups = TelephonyCall::where('is_unassigned', true)
      ->selectRaw('agent_id, agent_code, extension, agent_number, COUNT(*) as calls')
      ->selectRaw('MIN(initiated_at) as first_seen, MAX(initiated_at) as last_seen')
      ->groupBy('agent_id', 'agent_code', 'extension', 'agent_number')
      ->orderByDesc('calls')
      ->get();

    return view('tenant.telephony.admin.exceptions', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'calls' => $calls,
      'groups' => $groups,
      'users' => User::orderBy('first_name')->get(['id', 'first_name', 'last_name', 'code']),
    ]);
  }

  /**
   * Attach every unassigned call sharing a set of identifiers to one employee,
   * and optionally remember the mapping so it never happens again.
   */
  public function assignException(Request $request): RedirectResponse
  {
    $data = $request->validate([
      'user_id' => ['required', 'integer', 'exists:users,id'],
      'agent_id' => ['nullable', 'string', 'max:191'],
      'agent_code' => ['nullable', 'string', 'max:191'],
      'extension' => ['nullable', 'string', 'max:191'],
      'agent_number' => ['nullable', 'string', 'max:191'],
      'create_mapping' => ['nullable', 'boolean'],
    ]);

    $identifiers = array_filter([
      'agent_id' => $data['agent_id'] ?? null,
      'agent_code' => $data['agent_code'] ?? null,
      'extension' => $data['extension'] ?? null,
      'agent_number' => $data['agent_number'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');

    if ($identifiers === []) {
      return back()->with('error', 'That group carries no agent identifier, so there is nothing to match on. Assign those calls individually.');
    }

    $updated = TelephonyCall::where('is_unassigned', true)
      ->where(function ($q) use ($identifiers) {
        foreach ($identifiers as $column => $value) {
          $q->orWhere($column, $value);
        }
      })
      ->update(['user_id' => $data['user_id'], 'is_unassigned' => false]);

    $message = "{$updated} call(s) assigned.";

    if ($request->boolean('create_mapping')) {
      $exists = TelephonyAgentMap::where('user_id', $data['user_id'])->exists();

      if ($exists) {
        $message .= ' That employee already has a mapping, so a second one was not created.';
      } else {
        TelephonyAgentMap::create($identifiers + [
          'tenant_id' => config('telephony.inbound.default_tenant_id'),
          'user_id' => $data['user_id'],
          'is_active' => true,
          'created_by_id' => $request->user()->id,
          'notes' => 'Created from the exceptions screen.',
        ]);
        $message .= ' A mapping was saved so future calls resolve automatically.';
      }
    }

    return back()->with('success', $message);
  }
}
