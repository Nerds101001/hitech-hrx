@extends('layouts/layoutMaster')

@section('title', 'Calls Dashboard')

@section('content')
<div class="layout-full-width">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Calls Dashboard</h3>
      <p class="text-muted mb-0 small">Every call HRX has tracked — placed via the click-to-call button, or reported by a vendor for a call placed elsewhere.</p>
    </div>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">Dashboard</a></li>
        <li class="breadcrumb-item active">Calls</li>
      </ol>
    </nav>
  </div>

  <div class="px-4">

    {{-- Stat tiles --}}
    <div class="row g-3 mb-4">
      @php
        $tiles = [
          ['label' => 'Total calls', 'value' => $stats['total'], 'icon' => 'bx-phone', 'color' => '#0f172a'],
          ['label' => 'Via HRX', 'value' => $stats['hrx'], 'icon' => 'bx-mouse', 'color' => '#0e6b62'],
          ['label' => 'Elsewhere', 'value' => $stats['external'], 'icon' => 'bx-phone-call', 'color' => '#b45309'],
          ['label' => 'Connected', 'value' => $stats['connected'], 'icon' => 'bx-phone-incoming', 'color' => '#15803d'],
          ['label' => 'Ended', 'value' => $stats['ended'], 'icon' => 'bx-phone-off', 'color' => '#64748b'],
        ];
      @endphp
      @foreach($tiles as $t)
        <div class="col-6 col-md-4 col-xl-2dot4">
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bx {{ $t['icon'] }}" style="font-size:1.1rem; color:{{ $t['color'] }};"></i>
              <span class="text-muted small">{{ $t['label'] }}</span>
            </div>
            <div style="font-size:1.6rem; font-weight:800; color:{{ $t['color'] }};">{{ number_format($t['value']) }}</div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Filters --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Origin</label>
          <select name="origin" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="hrx" @selected(($filters['origin'] ?? '') === 'hrx')>Via HRX</option>
            <option value="external" @selected(($filters['origin'] ?? '') === 'external')>Elsewhere</option>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['queued','ringing','connected','busy','no_answer','invalid_number','failed','ended'] as $s)
              <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small fw-semibold mb-1">Agent</label>
          <select name="agent_id" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach($agents as $a)
              <option value="{{ $a->id }}" @selected((string)($filters['agent_id'] ?? '') === (string)$a->id)>{{ trim($a->first_name.' '.$a->last_name) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">From</label>
          <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">To</label>
          <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-1 d-flex gap-2">
          <button type="submit" class="btn btn-hitech-primary btn-sm w-100"><i class="bx bx-filter-alt"></i></button>
        </div>
      </form>
    </div>

    {{-- Table --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0" id="callsTable">
          <thead>
            <tr>
              <th>Call</th>
              <th>Agent</th>
              <th>From</th>
              <th>To</th>
              <th>Status</th>
              <th>Origin</th>
              <th>Duration</th>
              <th>Disposition</th>
              <th>Started</th>
            </tr>
          </thead>
          <tbody>
            @forelse($calls as $c)
              @php
                $toneMap = [
                  'connected' => ['#dcfce7', '#15803d'], 'ended' => ['#f1f5f9', '#475569'],
                  'busy' => ['#fef3c7', '#92400e'], 'no_answer' => ['#fef3c7', '#92400e'],
                  'invalid_number' => ['#fee2e2', '#991b1b'], 'failed' => ['#fee2e2', '#991b1b'],
                  'ringing' => ['#e0e7ff', '#3730a3'], 'queued' => ['#e0e7ff', '#3730a3'],
                ];
                $tone = $toneMap[$c->status->value] ?? ['#f1f5f9', '#475569'];
              @endphp
              <tr>
                <td class="small text-muted" title="{{ $c->call_uuid }}">{{ \Illuminate\Support\Str::limit($c->call_uuid, 8, '…') }}</td>
                <td>{{ $c->agent ? trim($c->agent->first_name.' '.$c->agent->last_name) : '—' }}</td>
                <td>{{ $c->from_number }}</td>
                <td>{{ $c->to_number }}</td>
                <td><span class="badge rounded-pill" style="background:{{ $tone[0] }}; color:{{ $tone[1] }};">{{ ucfirst(str_replace('_',' ',$c->status->value)) }}</span></td>
                <td>
                  @if($c->origin?->value === 'hrx')
                    <span class="badge badge-brand-soft rounded-pill">HRX</span>
                  @else
                    <span class="badge bg-label-warning rounded-pill">Elsewhere</span>
                  @endif
                </td>
                <td>{{ $c->duration !== null ? gmdate('i:s', $c->duration) : '—' }}</td>
                <td>{{ $c->disposition_code ?? '—' }}</td>
                <td class="small text-muted">{{ $c->created_at->format('d M, h:i A') }}</td>
              </tr>
            @empty
              <tr><td colspan="9" class="text-center py-5 text-muted">No calls match these filters.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-3">{{ $calls->links() }}</div>

  </div>
</div>

<style>
  .col-xl-2dot4 { flex: 0 0 auto; width: 100%; }
  @media (min-width: 1200px) { .col-xl-2dot4 { width: 20%; } }
  /* hitech-portal.scss has a later .table thead th rule with !important that
     silently wins over a plain inline style — matched at the same tier here
     with an ID-scoped selector, learned the hard way on the KPI table. */
  #callsTable thead th {
    background-color: #0f766e !important;
    color: #fff !important;
    border: none !important;
    font-weight: 700 !important;
    font-size: .78rem !important;
    text-transform: uppercase !important;
    letter-spacing: .03em !important;
    padding: .85rem 1rem !important;
  }
</style>
@endsection
