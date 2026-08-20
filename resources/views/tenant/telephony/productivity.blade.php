@extends('layouts/layoutMaster')

@section('title', 'Telephony productivity')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Productivity',
    'subtitle' => 'Login time, active time and occupancy — taken from CentroVision\'s own session totals, not derived here.',
    'crumb' => 'Productivity',
  ])

  <div class="px-4">

    <div class="row g-3 mb-4">
      @php
        $tiles = [
          ['label' => 'Login time', 'value' => telephony_hms($totals['login']), 'icon' => 'bx-log-in-circle', 'color' => '#0f172a'],
          ['label' => 'Active time', 'value' => telephony_hms($totals['active']), 'icon' => 'bx-run', 'color' => '#0e6b62'],
          ['label' => 'Talk time', 'value' => telephony_hms($totals['talk']), 'icon' => 'bx-phone-call', 'color' => '#15803d'],
          ['label' => 'Wrap-up', 'value' => telephony_hms($totals['wrap']), 'icon' => 'bx-edit', 'color' => '#4d6cf0'],
          ['label' => 'Break', 'value' => telephony_hms($totals['break']), 'icon' => 'bx-coffee', 'color' => '#b45309'],
          ['label' => 'Occupancy', 'value' => $totals['occupancy'] . '%', 'icon' => 'bx-pie-chart-alt-2', 'color' => $totals['occupancy'] >= 40 ? '#15803d' : '#b45309'],
        ];
      @endphp
      @foreach($tiles as $t)
        <div class="col-6 col-md-4 col-xl-2">
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bx {{ $t['icon'] }}" style="font-size:1.1rem; color:{{ $t['color'] }};"></i>
              <span class="text-muted small">{{ $t['label'] }}</span>
            </div>
            <div style="font-size:1.4rem; font-weight:800; color:{{ $t['color'] }};">{{ $t['value'] }}</div>
          </div>
        </div>
      @endforeach
    </div>

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
        <div class="col-6 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-hitech-primary btn-sm flex-grow-1"><i class="bx bx-filter-alt me-1"></i>Filter</button>
          <a href="{{ route('user.telephony.reports.productivity') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>

    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Employee</th>
              <th>Logged in</th>
              <th>Logged out</th>
              <th class="text-end">Login</th>
              <th class="text-end">Active</th>
              <th class="text-end">Talk</th>
              <th class="text-end">Hold</th>
              <th class="text-end">Wrap</th>
              <th class="text-end">Idle</th>
              <th class="text-end">Break</th>
              <th class="text-end">Occupancy</th>
              <th class="text-end">Handled</th>
              <th class="text-end">Missed</th>
            </tr>
          </thead>
          <tbody>
            @forelse($sessions as $s)
              <tr>
                <td class="small text-muted text-nowrap">{{ $s->login_at?->format('d M Y') ?? '—' }}</td>
                <td>
                  {{ $s->user ? trim($s->user->first_name.' '.$s->user->last_name) : '—' }}
                  @unless($s->user)
                    <div class="text-muted" style="font-size:.72rem;">agent {{ $s->agent_id ?: $s->extension ?: '?' }}</div>
                  @endunless
                </td>
                <td class="small">{{ $s->login_at?->format('H:i') ?? '—' }}</td>
                <td class="small">
                  @if($s->is_open)
                    <span class="badge bg-label-success rounded-pill">Still logged in</span>
                  @else
                    {{ $s->logout_at?->format('H:i') ?? '—' }}
                  @endif
                </td>
                <td class="text-end small">{{ telephony_hms($s->login_seconds) }}</td>
                <td class="text-end small fw-semibold">{{ telephony_hms($s->activeSeconds()) }}</td>
                <td class="text-end small">{{ telephony_hms($s->talk_seconds) }}</td>
                <td class="text-end small">{{ telephony_hms($s->hold_seconds) }}</td>
                <td class="text-end small">{{ telephony_hms($s->wrap_seconds) }}</td>
                <td class="text-end small">{{ telephony_hms($s->idle_seconds) }}</td>
                <td class="text-end small">{{ telephony_hms($s->break_seconds) }}</td>
                <td class="text-end small">{{ $s->occupancy_percent !== null ? $s->occupancy_percent . '%' : '—' }}</td>
                <td class="text-end small">{{ $s->calls_handled }}</td>
                <td class="text-end small {{ $s->calls_missed > 0 ? 'text-danger fw-semibold' : '' }}">{{ $s->calls_missed }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="14" class="text-center py-5">
                  <i class="bx bx-time-five text-muted" style="font-size:2.4rem;"></i>
                  <p class="text-muted mb-1 mt-2">No agent sessions in this window.</p>
                  <p class="text-muted small mb-0">
                    These arrive from CentroVision's <code>agent.login</code> and <code>agent.logout</code> events.
                    If calls are appearing but sessions are not, that half of the integration is not sending yet.
                  </p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($sessions->hasPages())
        <div class="p-3 border-top">{{ $sessions->links() }}</div>
      @endif
    </div>

    <p class="text-muted small mt-3 mb-0">
      Occupancy is (talk + hold + wrap) divided by (login &minus; break). Totals come from CentroVision so
      this page and the dialer's own User Session report cannot disagree.
    </p>

  </div>
</div>
@endsection
