@extends('layouts/layoutMaster')

@section('title', 'Calls')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Calls',
    'subtitle' => 'Every call CentroVision has reported — dialled from HRX, dialled on the handset, or received.',
    'crumb' => 'Calls',
  ])

  <div class="px-4">

    {{-- Stat tiles. These describe the FILTERED set, not the page, so they do
         not contradict the table when someone pages through. --}}
    <div class="row g-3 mb-4">
      @php
        $tiles = [
          ['label' => 'Calls', 'value' => number_format($stats['calls']), 'icon' => 'bx-phone', 'color' => '#0f172a'],
          ['label' => 'Answered', 'value' => number_format($stats['answered']), 'icon' => 'bx-phone-incoming', 'color' => '#15803d'],
          ['label' => 'Answer rate', 'value' => $stats['answer_rate'] . '%', 'icon' => 'bx-trending-up', 'color' => $stats['answer_rate'] >= 50 ? '#15803d' : '#b45309'],
          ['label' => 'Talk time', 'value' => telephony_hms($stats['talk']), 'icon' => 'bx-time-five', 'color' => '#0e6b62'],
          ['label' => 'Recorded', 'value' => number_format($stats['recorded']), 'icon' => 'bx-microphone', 'color' => '#4d6cf0'],
        ];
      @endphp
      @foreach($tiles as $t)
        <div class="col-6 col-md-4 col-xl">
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bx {{ $t['icon'] }}" style="font-size:1.1rem; color:{{ $t['color'] }};"></i>
              <span class="text-muted small">{{ $t['label'] }}</span>
            </div>
            <div style="font-size:1.5rem; font-weight:800; color:{{ $t['color'] }};">{{ $t['value'] }}</div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Filters --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">From</label>
          <input type="date" name="from" value="{{ $filters['from'] ?? $window[0]->toDateString() }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">To</label>
          <input type="date" name="to" value="{{ $filters['to'] ?? $window[1]->toDateString() }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Direction</label>
          <select name="direction" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['outbound' => 'Outbound', 'inbound' => 'Inbound', 'internal' => 'Internal'] as $v => $l)
              <option value="{{ $v }}" @selected(($filters['direction'] ?? '') === $v)>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">How it started</label>
          <select name="origin" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['api_click_to_call','manual_dial','inbound','ivr','predictive','preview','progressive','callback','internal'] as $v)
              <option value="{{ $v }}" @selected(($filters['origin'] ?? '') === $v)>{{ telephony_origin_label($v) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Outcome</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['answered','no_answer','busy','failed','congestion','cancelled','abandoned','rejected','not_reachable','switched_off'] as $v)
              <option value="{{ $v }}" @selected(($filters['status'] ?? '') === $v)>{{ ucfirst(str_replace('_',' ',$v)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Disposition</label>
          <select name="disposition" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach($dispositions as $d)
              <option value="{{ $d }}" @selected(($filters['disposition'] ?? '') === $d)>{{ $d }}</option>
            @endforeach
          </select>
        </div>

        @if($agents->isNotEmpty())
          <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold mb-1">Employee</label>
            <select name="user_id" class="form-select form-select-sm">
              <option value="">Everyone I can see</option>
              @foreach($agents as $a)
                <option value="{{ $a->id }}" @selected((string)($filters['user_id'] ?? '') === (string)$a->id)>{{ trim($a->first_name.' '.$a->last_name) }}</option>
              @endforeach
            </select>
          </div>
        @endif

        <div class="col-6 col-md-3">
          <label class="form-label small fw-semibold mb-1">Number or call id</label>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. 9812309876">
        </div>

        <div class="col-6 col-md-2 d-flex align-items-center pt-3">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="has_recording" value="1" id="hasRec" @checked($filters['has_recording'] ?? false)>
            <label class="form-check-label small" for="hasRec">Has recording</label>
          </div>
        </div>

        <div class="col-6 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-hitech-primary btn-sm flex-grow-1"><i class="bx bx-filter-alt me-1"></i>Filter</button>
          <a href="{{ route('user.telephony.reports.calls') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>

    {{-- Table --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>When</th>
              <th>Employee</th>
              <th>Direction</th>
              <th>Number</th>
              <th>How it started</th>
              <th>Outcome</th>
              <th class="text-end">Ring</th>
              <th class="text-end">Talk</th>
              <th class="text-end">Hold</th>
              <th>Disposition</th>
              <th class="text-center">Flags</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($calls as $c)
              @php
                $tone = telephony_tone($c->status);
                $other = $c->direction === 'inbound' ? $c->caller_number : $c->callee_number;
              @endphp
              <tr>
                <td class="small text-muted text-nowrap">
                  {{ $c->initiated_at?->format('d M, H:i') ?? '—' }}
                </td>
                <td>
                  @if($c->user)
                    {{ trim($c->user->first_name.' '.$c->user->last_name) }}
                  @elseif($c->is_unassigned)
                    <span class="badge bg-label-danger rounded-pill" title="No agent mapping matched this call">Unassigned</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td class="small">
                  <i class="bx {{ $c->direction === 'inbound' ? 'bx-phone-incoming' : 'bx-phone-outgoing' }} me-1"></i>{{ ucfirst($c->direction ?? '—') }}
                </td>
                <td class="text-nowrap">{{ telephony_number($other, $unmasked) }}</td>
                <td class="small">{{ telephony_origin_label($c->origin) }}</td>
                <td>
                  <span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">
                    {{ ucfirst(str_replace('_',' ', $c->status ?? 'in progress')) }}
                  </span>
                </td>
                <td class="text-end small">{{ telephony_hms($c->customer_ring_seconds) }}</td>
                <td class="text-end small fw-semibold">{{ telephony_hms($c->talk_seconds) }}</td>
                <td class="text-end small">{{ $c->hold_seconds > 0 ? telephony_hms($c->hold_seconds) : '—' }}</td>
                <td class="small">
                  {{ $c->disposition_name ?? $c->disposition_code ?? '—' }}
                  @if($c->sub_disposition_name)
                    <div class="text-muted" style="font-size:.72rem;">{{ $c->sub_disposition_name }}</div>
                  @endif
                </td>
                <td class="text-center text-nowrap">
                  @if($c->was_transferred)<i class="bx bx-transfer text-primary" title="Transferred"></i>@endif
                  @if($c->was_conferenced)<i class="bx bx-group text-primary" title="Conference"></i>@endif
                  @if($c->was_forwarded)<i class="bx bx-share text-primary" title="Forwarded"></i>@endif
                  @if($c->has_recording)<i class="bx bx-microphone text-success" title="Recorded"></i>@endif
                  @if($c->via_reconciliation)<i class="bx bx-refresh text-warning" title="Recovered by the nightly reconciliation, not pushed"></i>@endif
                </td>
                <td class="text-end">
                  <a href="{{ route('user.telephony.reports.call', $c->call_uid) }}"
                     class="btn btn-sm btn-outline-secondary rounded-pill">Detail</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="12" class="text-center py-5">
                  <i class="bx bx-phone-off text-muted" style="font-size:2.4rem;"></i>
                  <p class="text-muted mb-1 mt-2">No calls in this window.</p>
                  <p class="text-muted small mb-0">
                    If you expected some, check the
                    <a href="{{ route('user.telephony.admin.webhook-log') }}">Webhook Log</a>
                    to see whether CentroVision is actually pushing events.
                  </p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($calls->hasPages())
        <div class="p-3 border-top">{{ $calls->links() }}</div>
      @endif
    </div>

    @unless($unmasked)
      <p class="text-muted small mt-3 mb-0">
        <i class="bx bx-lock-alt me-1"></i>Customer numbers are partly masked for your role.
      </p>
    @endunless

  </div>
</div>
@endsection
