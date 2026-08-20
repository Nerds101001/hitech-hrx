<?php

namespace App\Http\Controllers\tenant\Telephony;

use App\Http\Controllers\Controller;
use App\Models\Telephony\TelephonyAgentSession;
use App\Models\Telephony\TelephonyCall;
use App\Models\Telephony\TelephonyCallEvent;
use App\Models\Telephony\TelephonyCallLeg;
use App\Models\Telephony\TelephonyRecording;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Everything an employee, a manager or an admin can read about calls.
 *
 * Scope is never decided here. Every query starts from
 * TelephonyCall::visibleTo($user), which is the single place that knows
 * "own / team including delegated identities / all" -- so a new report cannot
 * accidentally widen access by forgetting a where clause.
 */
class TelephonyReportController extends Controller
{
  /** Roles that see full customer numbers. Everyone else gets them masked. */
  private function maySeeFullNumbers($user): bool
  {
    return $user->hasAnyRole(config('telephony.recording.unmasked_roles', ['admin', 'hr']));
  }

  /**
   * The window a report covers.
   *
   * Defaults to the last 30 days rather than all time: these tables grow by
   * thousands of rows a week and an unbounded default would get slower every
   * month until someone noticed.
   */
  private function window(Request $request): array
  {
    $tz = config('app.timezone');

    $from = $request->filled('from')
      ? Carbon::parse($request->query('from'), $tz)->startOfDay()
      : Carbon::now($tz)->subDays(29)->startOfDay();

    $to = $request->filled('to')
      ? Carbon::parse($request->query('to'), $tz)->endOfDay()
      : Carbon::now($tz)->endOfDay();

    return [$from, $to];
  }

  // ─────────────────────────────────────────────────────────────── My Calls

  public function calls(Request $request): View
  {
    $user = $request->user();
    [$from, $to] = $this->window($request);

    $base = TelephonyCall::query()
      ->visibleTo($user)
      // A call with no initiated_at is one we only ever saw a fragment of.
      // Ordering by it would hide those rows at the bottom forever, so fall
      // back to when the row was written.
      ->whereBetween('initiated_at', [$from, $to]);

    $filtered = (clone $base)
      ->when($request->filled('direction'), fn ($q) => $q->where('direction', $request->query('direction')))
      ->when($request->filled('origin'), fn ($q) => $q->where('origin', $request->query('origin')))
      ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
      ->when($request->filled('disposition'), fn ($q) => $q->where('disposition_code', $request->query('disposition')))
      ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
      ->when($request->filled('has_recording'), fn ($q) => $q->where('has_recording', true))
      ->when($request->filled('q'), function ($q) use ($request) {
        $term = '%' . $request->query('q') . '%';
        $q->where(fn ($w) => $w->where('caller_number', 'like', $term)
          ->orWhere('callee_number', 'like', $term)
          ->orWhere('call_uid', 'like', $term));
      });

    // Totals come from the filtered set, not the page, or the tiles would
    // describe 25 rows while the table describes thousands.
    $totals = (clone $filtered)
      ->selectRaw('COUNT(*) as calls')
      ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as answered', ['answered'])
      ->selectRaw('SUM(talk_seconds) as talk')
      ->selectRaw('SUM(CASE WHEN has_recording = 1 THEN 1 ELSE 0 END) as recorded')
      ->first();

    $calls = $filtered->clone()
      ->with('user:id,first_name,last_name')
      ->orderByDesc('initiated_at')
      ->paginate(25)
      ->withQueryString();

    return view('tenant.telephony.calls', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'calls' => $calls,
      'stats' => [
        'calls' => (int) ($totals->calls ?? 0),
        'answered' => (int) ($totals->answered ?? 0),
        'talk' => (int) ($totals->talk ?? 0),
        'recorded' => (int) ($totals->recorded ?? 0),
        'answer_rate' => ($totals->calls ?? 0) > 0
          ? round(($totals->answered / $totals->calls) * 100, 1)
          : 0.0,
      ],
      'agents' => $this->agentChoices($user),
      'dispositions' => (clone $base)->whereNotNull('disposition_code')
        ->distinct()->orderBy('disposition_code')->pluck('disposition_code'),
      'filters' => $request->only(['from', 'to', 'direction', 'origin', 'status', 'disposition', 'user_id', 'q', 'has_recording']),
      'window' => [$from, $to],
      'unmasked' => $this->maySeeFullNumbers($user),
    ]);
  }

  // ────────────────────────────────────────────────────────────── One call

  public function show(Request $request, string $callUid): View
  {
    $user = $request->user();

    // visibleTo() before firstOrFail(), so a call outside the caller's scope
    // is a 404 rather than a 403 -- it should not even confirm the call exists.
    $call = TelephonyCall::query()
      ->visibleTo($user)
      ->with('user:id,first_name,last_name')
      ->where('call_uid', $callUid)
      ->firstOrFail();

    return view('tenant.telephony.call-detail', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'call' => $call,
      'legs' => TelephonyCallLeg::where('call_uid', $callUid)->orderBy('started_at')->get(),
      'recordings' => TelephonyRecording::where('call_uid', $callUid)->orderBy('ready_at')->get(),
      'events' => TelephonyCallEvent::where('call_uid', $callUid)
        ->orderBy('sequence')->orderBy('occurred_at')->get(),
      'timeline' => $this->timeline($call),
      'unmasked' => $this->maySeeFullNumbers($user),
      'canPlay' => $user->hasAnyRole(config('telephony.recording.supervisor_roles', []))
        || $call->user_id === $user->id,
    ]);
  }

  /**
   * The call's own clock, as a list of labelled moments.
   *
   * Built from whichever timestamps actually arrived rather than a fixed list,
   * because a manual dial has no agent leg and an abandoned call never gets
   * answered -- printing "—" against six empty rows tells the reader nothing.
   */
  private function timeline(TelephonyCall $call): array
  {
    $points = [
      ['initiated_at', 'Dial started', 'bx-play-circle'],
      ['agent_ring_at', "Agent's phone rang", 'bx-bell'],
      ['agent_answer_at', 'Agent picked up', 'bx-user-voice'],
      ['queue_enter_at', 'Entered queue', 'bx-line-chart-down'],
      ['queue_exit_at', 'Left queue', 'bx-exit'],
      ['customer_ring_at', "Customer's phone rang", 'bx-phone-outgoing'],
      ['answered_at', 'Connected — talk time starts', 'bx-phone-call'],
      ['ended_at', 'Hung up', 'bx-phone-off'],
      ['wrapup_start_at', 'Wrap-up started', 'bx-edit'],
      ['wrapup_end_at', 'Wrap-up finished', 'bx-check'],
    ];

    $rows = [];
    $previous = null;

    foreach ($points as [$field, $label, $icon]) {
      $at = $call->{$field};
      if (!$at) {
        continue;
      }

      $rows[] = [
        'label' => $label,
        'icon' => $icon,
        'at' => $at,
        // Gap from the previous moment that actually happened, which is what
        // makes a slow GSM route or a long queue visible at a glance.
        'gap' => $previous ? max(0, $at->diffInSeconds($previous)) : null,
      ];
      $previous = $at;
    }

    return $rows;
  }

  // ─────────────────────────────────────────────────────────── Productivity

  public function productivity(Request $request): View
  {
    $user = $request->user();
    [$from, $to] = $this->window($request);

    $sessions = TelephonyAgentSession::query()
      ->whereIn('user_id', $this->visibleUserIds($user))
      ->whereBetween('login_at', [$from, $to])
      ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
      ->with('user:id,first_name,last_name')
      ->orderByDesc('login_at')
      ->paginate(25)
      ->withQueryString();

    $totals = TelephonyAgentSession::query()
      ->whereIn('user_id', $this->visibleUserIds($user))
      ->whereBetween('login_at', [$from, $to])
      ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->query('user_id')))
      ->selectRaw('SUM(login_seconds) as login, SUM(break_seconds) as brk, SUM(talk_seconds) as talk')
      ->selectRaw('SUM(wrap_seconds) as wrap, SUM(hold_seconds) as hold, SUM(idle_seconds) as idle')
      ->selectRaw('SUM(calls_handled) as handled, SUM(calls_missed) as missed, COUNT(*) as sessions')
      ->first();

    $login = (int) ($totals->login ?? 0);
    $break = (int) ($totals->brk ?? 0);
    $productive = (int) ($totals->talk ?? 0) + (int) ($totals->hold ?? 0) + (int) ($totals->wrap ?? 0);

    return view('tenant.telephony.productivity', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'sessions' => $sessions,
      'totals' => [
        'sessions' => (int) ($totals->sessions ?? 0),
        'login' => $login,
        // "Active" is login minus break, matching the dialer's own definition.
        'active' => max(0, $login - $break),
        'talk' => (int) ($totals->talk ?? 0),
        'hold' => (int) ($totals->hold ?? 0),
        'wrap' => (int) ($totals->wrap ?? 0),
        'idle' => (int) ($totals->idle ?? 0),
        'break' => $break,
        'handled' => (int) ($totals->handled ?? 0),
        'missed' => (int) ($totals->missed ?? 0),
        // Guarded: a day of nothing but break time would divide by zero.
        'occupancy' => ($login - $break) > 0
          ? round($productive / ($login - $break) * 100, 1)
          : 0.0,
      ],
      'agents' => $this->agentChoices($user),
      'filters' => $request->only(['from', 'to', 'user_id']),
      'window' => [$from, $to],
    ]);
  }

  // ───────────────────────────────────────────────────────────── Analytics

  public function analytics(Request $request): View
  {
    $user = $request->user();
    [$from, $to] = $this->window($request);

    $scoped = fn () => TelephonyCall::query()->visibleTo($user)
      ->whereBetween('initiated_at', [$from, $to]);

    $dispositions = $scoped()
      ->whereNotNull('disposition_code')
      ->selectRaw('disposition_code, disposition_name, COUNT(*) as calls, SUM(talk_seconds) as talk')
      ->groupBy('disposition_code', 'disposition_name')
      ->orderByDesc('calls')
      ->get();

    $byStatus = $scoped()
      ->whereNotNull('status')
      ->selectRaw('status, COUNT(*) as calls')
      ->groupBy('status')
      ->orderByDesc('calls')
      ->get();

    $abandoned = $scoped()
      ->where('status', 'abandoned')
      ->selectRaw('queue, COUNT(*) as calls, AVG(queue_wait_seconds) as avg_wait, MAX(queue_wait_seconds) as max_wait')
      ->groupBy('queue')
      ->orderByDesc('calls')
      ->get();

    $byDay = $scoped()
      ->selectRaw('DATE(initiated_at) as day, COUNT(*) as calls')
      ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as answered', ['answered'])
      ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as abandoned', ['abandoned'])
      ->selectRaw('SUM(talk_seconds) as talk')
      ->groupBy('day')
      ->orderBy('day')
      ->get();

    $legs = TelephonyCallLeg::query()
      ->whereIn('call_uid', $scoped()->select('call_uid'))
      ->selectRaw('leg_type, sub_type, COUNT(*) as legs')
      ->groupBy('leg_type', 'sub_type')
      ->orderByDesc('legs')
      ->get();

    return view('tenant.telephony.analytics', [
      'pageConfigs' => ['contentLayout' => 'wide'],
      'dispositions' => $dispositions,
      'byStatus' => $byStatus,
      'abandoned' => $abandoned,
      'byDay' => $byDay,
      'legs' => $legs,
      'filters' => $request->only(['from', 'to']),
      'window' => [$from, $to],
    ]);
  }

  // ─────────────────────────────────────────────────────────────── helpers

  /**
   * The users this caller may see calls for. Mirrors visibleTo() exactly --
   * agent sessions have no call row to scope through, so the rule is applied
   * to the user list instead.
   */
  private function visibleUserIds($user): array
  {
    if ($user->hasAnyRole(['admin', 'hr'])) {
      return User::pluck('id')->all();
    }

    if ($user->hasRole('manager')) {
      return User::whereIn('reporting_to_id', $user->managerIdentityIds())
        ->pluck('id')->push($user->id)->unique()->all();
    }

    return [$user->id];
  }

  /** Agents the caller may filter by — the same set they may see calls for. */
  private function agentChoices($user)
  {
    if (!$user->hasAnyRole(['admin', 'hr', 'manager'])) {
      return collect();
    }

    return User::whereIn('id', $this->visibleUserIds($user))
      ->orderBy('first_name')
      ->get(['id', 'first_name', 'last_name']);
  }
}
