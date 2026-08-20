@extends('layouts/layoutMaster')

@section('title', 'Call detail')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Call detail',
    'subtitle' => $call->call_uid . ' — every timestamp CentroVision reported for this call.',
    'crumb' => 'Call detail',
    'tabs' => false,
  ])

  <div class="px-4">

    <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary rounded-pill mb-3">
      <i class="bx bx-arrow-back me-1"></i>Back
    </a>

    <div class="row g-4">

      {{-- ─────────────────────────────────────────────── Summary --}}
      <div class="col-12 col-xl-5">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
          @php $tone = telephony_tone($call->status); @endphp

          <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="fw-bold mb-0">Summary</h6>
            <span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">
              {{ ucfirst(str_replace('_',' ', $call->status ?? 'in progress')) }}
            </span>
          </div>

          @php
            $rows = [
              'Employee' => $call->user
                ? trim($call->user->first_name.' '.$call->user->last_name)
                : ($call->is_unassigned ? 'Unassigned — no agent mapping matched' : '—'),
              'Direction' => ucfirst($call->direction ?? '—'),
              'How it started' => telephony_origin_label($call->origin),
              'Dialling mode' => $call->mode ? ucfirst($call->mode) : '—',
              'From' => telephony_number($call->caller_number, $unmasked),
              'To' => telephony_number($call->callee_number, $unmasked),
              'Caller ID shown' => telephony_number($call->did_number, $unmasked),
              'Campaign' => $call->campaign_name ?: ($call->campaign_id ?: '—'),
              'Queue / skill' => trim(($call->queue ?: '—') . ' / ' . ($call->skill ?: '—')),
              'Agent extension' => $call->extension ?: '—',
              'Dialer agent' => $call->agent_id ?: ($call->agent_code ?: '—'),
              'Hung up by' => $call->hangup_by ? ucfirst($call->hangup_by) : '—',
              'Hangup cause' => $call->hangup_cause
                ? $call->hangup_cause . ($call->hangup_cause_code !== null ? " ({$call->hangup_cause_code})" : '')
                : '—',
              'GSM channel' => trim(($call->gateway_id ?: '—') . ' / ch ' . ($call->channel ?: '—')),
              'SIM used' => telephony_number($call->sim_msisdn, $unmasked),
              'Operator' => $call->operator ?: '—',
            ];
          @endphp

          <table class="table table-sm mb-0">
            <tbody>
              @foreach($rows as $label => $value)
                <tr>
                  <td class="text-muted small" style="width:45%;">{{ $label }}</td>
                  <td class="small fw-semibold">{{ $value }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- Durations --}}
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
          <h6 class="fw-bold mb-3">Durations</h6>
          <div class="row g-3">
            @php
              $durations = [
                ['Agent ring', $call->agent_ring_seconds, 'How long the agent\'s own phone rang'],
                ['Customer ring', $call->customer_ring_seconds, 'How long the customer\'s phone rang'],
                ['Post-dial delay', $call->pdd_seconds, 'Dial to first ring — a high value points at a bad GSM route'],
                ['Queue wait', $call->queue_wait_seconds, 'Time waiting before answer or abandon'],
                ['Talk', $call->talk_seconds, 'Bridged conversation, excluding hold'],
                ['Hold', $call->hold_seconds, 'Total time the customer spent on hold'],
                ['Wrap-up', $call->wrap_seconds, 'After-call work'],
                ['Total', $call->total_seconds, 'Dial to hangup'],
              ];
            @endphp
            @foreach($durations as [$label, $value, $hint])
              <div class="col-6">
                <div class="text-muted" style="font-size:.72rem;" title="{{ $hint }}">{{ $label }}</div>
                <div class="fw-bold" style="font-size:1.05rem; color:{{ $label === 'Talk' ? '#0e6b62' : '#0f172a' }};">
                  {{ telephony_hms($value) }}
                </div>
              </div>
            @endforeach
          </div>

          @if($call->billed_seconds !== null)
            <p class="text-muted small mb-0 mt-3">Operator-billed: {{ telephony_hms($call->billed_seconds) }}</p>
          @endif
        </div>

        {{-- Disposition --}}
        @if($call->disposition_code || $call->remarks)
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
            <h6 class="fw-bold mb-3">Disposition</h6>
            <p class="mb-1 fw-semibold">{{ $call->disposition_name ?? $call->disposition_code }}</p>
            @if($call->sub_disposition_name)
              <p class="text-muted small mb-2">{{ $call->sub_disposition_name }}</p>
            @endif
            @if($call->remarks)
              <p class="mb-2">{{ $call->remarks }}</p>
            @endif
            @if($call->callback_at)
              <p class="text-muted small mb-0">
                <i class="bx bx-calendar me-1"></i>Callback due {{ $call->callback_at->format('d M Y, H:i') }}
              </p>
            @endif
          </div>
        @endif

        {{-- Line quality — only shown when the gateway sent it, since an empty
             quality panel reads as "the line was fine" rather than "unknown". --}}
        @if(!empty($call->quality))
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4">
            <h6 class="fw-bold mb-3">Line quality</h6>
            <div class="row g-3">
              @foreach($call->quality as $k => $v)
                <div class="col-4">
                  <div class="text-muted" style="font-size:.72rem;">{{ strtoupper(str_replace('_',' ', $k)) }}</div>
                  <div class="fw-bold">{{ $v }}</div>
                </div>
              @endforeach
            </div>
          </div>
        @endif
      </div>

      {{-- ─────────────────────────────────────────────── Timeline --}}
      <div class="col-12 col-xl-7">

        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
          <h6 class="fw-bold mb-1">Timeline</h6>
          <p class="text-muted small mb-4">
            Only moments the gateway actually reported. The gap column is the time since the previous step.
          </p>

          @forelse($timeline as $t)
            <div class="d-flex gap-3 mb-3">
              <div class="text-center" style="width:34px; flex:0 0 34px;">
                <i class="bx {{ $t['icon'] }}" style="font-size:1.1rem; color:var(--hitech-primary, #008080);"></i>
                @unless($loop->last)
                  <div class="mx-auto" style="width:2px; height:22px; background:#e2e8f0;"></div>
                @endunless
              </div>
              <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                  <span class="fw-semibold small">{{ $t['label'] }}</span>
                  @if($t['gap'] !== null)
                    <span class="text-muted" style="font-size:.72rem;">+{{ telephony_hms($t['gap']) }}</span>
                  @endif
                </div>
                <div class="text-muted small">{{ $t['at']->format('d M Y, H:i:s') }}</div>
              </div>
            </div>
          @empty
            <p class="text-muted small mb-0">No timestamps were reported for this call.</p>
          @endforelse
        </div>

        {{-- Recordings --}}
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
          <h6 class="fw-bold mb-1">Recordings</h6>
          <p class="text-muted small mb-3">
            Audio is streamed through HRX — the gateway link never reaches your browser, and every
            playback is written to the access log.
          </p>

          @forelse($recordings as $r)
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
              <div>
                <div class="small fw-semibold">
                  {{ ucfirst(str_replace('_',' ', $r->recording_type)) }}
                  <span class="text-muted fw-normal">
                    · {{ telephony_hms($r->duration_seconds) }}
                    @if($r->format) · {{ strtoupper($r->format) }}@endif
                    @if($r->channels) · {{ $r->channels }}@endif
                  </span>
                </div>
                @if($r->retention_until)
                  <div class="text-muted" style="font-size:.72rem;">
                    Kept until {{ $r->retention_until->format('d M Y') }}
                  </div>
                @endif
              </div>

              @if(!$canPlay)
                <span class="text-muted small">Not available to your role</span>
              @elseif($r->isExpired())
                <span class="text-muted small">Past its retention date</span>
              @else
                <audio controls preload="none" style="height:36px; max-width:100%;"
                       src="{{ route('user.telephony.recording.stream', $r->recording_uid) }}">
                  Your browser cannot play audio.
                </audio>
              @endif
            </div>
          @empty
            <p class="text-muted small mb-0">
              @if($call->has_recording)
                The call is marked as recorded but no recording.ready event has arrived yet.
              @else
                No recording for this call.
              @endif
            </p>
          @endforelse
        </div>

        {{-- Transfer / conference / forward legs --}}
        @if($legs->isNotEmpty())
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
            <h6 class="fw-bold mb-3">Transfers, conferences and forwards</h6>
            @foreach($legs as $leg)
              <div class="py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <span class="badge badge-brand-soft rounded-pill">{{ ucfirst($leg->leg_type) }}</span>
                  @if($leg->sub_type)
                    <span class="small text-muted">{{ ucfirst(str_replace('_',' ', $leg->sub_type)) }}</span>
                  @endif
                  @if($leg->started_at)
                    <span class="small text-muted">{{ $leg->started_at->format('H:i:s') }}</span>
                  @endif
                </div>

                @if($leg->leg_type === 'conference' && $leg->participants)
                  <ul class="mb-0 ps-3 small">
                    @foreach($leg->participants as $p)
                      <li>
                        {{ ucfirst($p['role'] ?? 'participant') }} —
                        {{ telephony_number($p['number'] ?? null, $unmasked) }}
                        @if(!empty($p['joined_at']))
                          <span class="text-muted">joined {{ \Illuminate\Support\Str::afterLast($p['joined_at'], 'T') }}</span>
                        @endif
                      </li>
                    @endforeach
                  </ul>
                @else
                  <div class="small">
                    {{ telephony_number($leg->from_number, $unmasked) }}
                    <i class="bx bx-right-arrow-alt mx-1"></i>
                    {{ $leg->to_extension ?: $leg->to_queue ?: telephony_number($leg->to_number, $unmasked) }}
                    @if($leg->consult_seconds !== null)
                      <span class="text-muted">· consulted {{ telephony_hms($leg->consult_seconds) }}</span>
                    @endif
                  </div>
                  @if($leg->child_call_uid)
                    <div class="small">
                      <a href="{{ route('user.telephony.reports.call', $leg->child_call_uid) }}">
                        Open the resulting call ({{ $leg->child_call_uid }})
                      </a>
                    </div>
                  @endif
                @endif
              </div>
            @endforeach
          </div>
        @endif

        {{-- Hold periods --}}
        @if(!empty($call->hold_events))
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
            <h6 class="fw-bold mb-3">Hold periods</h6>
            <ul class="mb-0 ps-3 small">
              @foreach($call->hold_events as $h)
                <li>
                  {{ \Illuminate\Support\Str::afterLast($h['from'] ?? '?', 'T') }}
                  to {{ \Illuminate\Support\Str::afterLast($h['to'] ?? '?', 'T') }}
                  — {{ telephony_hms($h['seconds'] ?? null) }}
                </li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- The raw event stream. Kept last and collapsed: useful when HRX and
             the dialer disagree, noise the rest of the time. --}}
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4">
          <div class="d-flex align-items-center justify-content-between">
            <h6 class="fw-bold mb-0">Events received ({{ $events->count() }})</h6>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button"
                    data-bs-toggle="collapse" data-bs-target="#rawEvents">
              Show
            </button>
          </div>
          <div class="collapse mt-3" id="rawEvents">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>Seq</th><th>Event</th><th>Occurred</th></tr></thead>
                <tbody>
                  @forelse($events as $e)
                    <tr>
                      <td class="small">{{ $e->sequence }}</td>
                      <td class="small"><code>{{ $e->event_type }}</code></td>
                      <td class="small text-muted">{{ $e->occurred_at?->format('d M, H:i:s') }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="3" class="text-muted small">No individual events — this call arrived as a single CDR row.</td></tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection
