@extends('layouts/layoutMaster')

@section('title', 'Telephony analytics')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Analytics',
    'subtitle' => 'Dispositions, outcomes, abandons and call flows across the window you choose.',
    'crumb' => 'Analytics',
  ])

  <div class="px-4">

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
        <div class="col-6 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-hitech-primary btn-sm flex-grow-1"><i class="bx bx-filter-alt me-1"></i>Filter</button>
          <a href="{{ route('user.telephony.reports.analytics') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>

    {{-- Daily volume. A plain table rather than a chart: it is exportable,
         copy-pasteable into an email, and readable without JavaScript. --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden mb-4">
      <div class="p-3 border-bottom">
        <h6 class="fw-bold mb-0">Daily volume</h6>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Day</th>
              <th class="text-end">Calls</th>
              <th class="text-end">Answered</th>
              <th class="text-end">Answer rate</th>
              <th class="text-end">Abandoned</th>
              <th class="text-end">Talk time</th>
              <th style="width:22%;">Volume</th>
            </tr>
          </thead>
          <tbody>
            @php $peak = $byDay->max('calls') ?: 1; @endphp
            @forelse($byDay as $d)
              @php $rate = $d->calls > 0 ? round($d->answered / $d->calls * 100, 1) : 0; @endphp
              <tr>
                <td class="small text-nowrap">{{ \Carbon\Carbon::parse($d->day)->format('D, d M') }}</td>
                <td class="text-end small fw-semibold">{{ number_format($d->calls) }}</td>
                <td class="text-end small">{{ number_format($d->answered) }}</td>
                <td class="text-end small" style="color:{{ $rate >= 50 ? '#15803d' : '#b45309' }};">{{ $rate }}%</td>
                <td class="text-end small {{ $d->abandoned > 0 ? 'text-danger' : 'text-muted' }}">{{ number_format($d->abandoned) }}</td>
                <td class="text-end small">{{ telephony_hms($d->talk) }}</td>
                <td>
                  <div style="background:#eef2f6; border-radius:4px; height:8px;">
                    <div style="width:{{ max(2, round($d->calls / $peak * 100)) }}%; height:8px; border-radius:4px; background:var(--hitech-primary, #008080);"></div>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center py-4 text-muted">No calls in this window.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="row g-4">

      {{-- Dispositions --}}
      <div class="col-12 col-xl-6">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden h-100">
          <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Dispositions</h6>
            <p class="text-muted small mb-0">What agents recorded as the outcome.</p>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Disposition</th><th class="text-end">Calls</th><th class="text-end">Share</th><th class="text-end">Talk time</th></tr>
              </thead>
              <tbody>
                @php $dispTotal = $dispositions->sum('calls') ?: 1; @endphp
                @forelse($dispositions as $d)
                  <tr>
                    <td class="small">{{ $d->disposition_name ?: $d->disposition_code }}</td>
                    <td class="text-end small fw-semibold">{{ number_format($d->calls) }}</td>
                    <td class="text-end small text-muted">{{ round($d->calls / $dispTotal * 100, 1) }}%</td>
                    <td class="text-end small">{{ telephony_hms($d->talk) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center py-4 text-muted small">
                    No dispositions recorded. These arrive on <code>call.completed</code>.
                  </td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Outcomes --}}
      <div class="col-12 col-xl-6">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden h-100">
          <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Outcomes</h6>
            <p class="text-muted small mb-0">What the line actually did, as reported by the gateway.</p>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Outcome</th><th class="text-end">Calls</th><th class="text-end">Share</th></tr></thead>
              <tbody>
                @php $statusTotal = $byStatus->sum('calls') ?: 1; @endphp
                @forelse($byStatus as $s)
                  @php $tone = telephony_tone($s->status); @endphp
                  <tr>
                    <td>
                      <span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">
                        {{ ucfirst(str_replace('_',' ', $s->status)) }}
                      </span>
                    </td>
                    <td class="text-end small fw-semibold">{{ number_format($s->calls) }}</td>
                    <td class="text-end small text-muted">{{ round($s->calls / $statusTotal * 100, 1) }}%</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center py-4 text-muted small">No calls in this window.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Abandons --}}
      <div class="col-12 col-xl-6">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden h-100">
          <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Abandoned calls</h6>
            <p class="text-muted small mb-0">Callers who hung up before anyone answered.</p>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Queue</th><th class="text-end">Abandoned</th><th class="text-end">Avg wait</th><th class="text-end">Longest wait</th></tr></thead>
              <tbody>
                @forelse($abandoned as $a)
                  <tr>
                    <td class="small">{{ $a->queue ?: 'No queue recorded' }}</td>
                    <td class="text-end small fw-semibold text-danger">{{ number_format($a->calls) }}</td>
                    <td class="text-end small">{{ telephony_hms(round($a->avg_wait)) }}</td>
                    <td class="text-end small">{{ telephony_hms($a->max_wait) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="4" class="text-center py-4 text-muted small">No abandoned calls. Good.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Call flows --}}
      <div class="col-12 col-xl-6">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden h-100">
          <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Transfers, conferences and forwards</h6>
            <p class="text-muted small mb-0">Calls that moved between people.</p>
          </div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Type</th><th>Kind</th><th class="text-end">Count</th></tr></thead>
              <tbody>
                @forelse($legs as $l)
                  <tr>
                    <td><span class="badge badge-brand-soft rounded-pill">{{ ucfirst($l->leg_type) }}</span></td>
                    <td class="small">{{ $l->sub_type ? ucfirst(str_replace('_',' ', $l->sub_type)) : '—' }}</td>
                    <td class="text-end small fw-semibold">{{ number_format($l->legs) }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center py-4 text-muted small">
                    No transfers, conferences or forwards recorded in this window.
                  </td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
