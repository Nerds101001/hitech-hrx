@extends('layouts/layoutMaster')

@section('title', 'Telephony webhook log')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Webhook Log',
    'subtitle' => 'Every payload CentroVision has sent, exactly as it arrived. This is what settles "we sent it" against "we never got it".',
    'crumb' => 'Webhook Log',
  ])

  <div class="px-4">

    {{-- Silence is the failure mode that matters. An integration rarely breaks
         loudly -- it stops, and nobody notices for days. So the age of the last
         delivery gets its own prominent tile. --}}
    @php
      $last = $lastReceived ? \Carbon\Carbon::parse($lastReceived) : null;
      $minutes = $last ? $last->diffInMinutes(now()) : null;
      $silent = $minutes !== null && $minutes > 30;
    @endphp

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100"
             style="{{ $silent || !$last ? 'border-left:4px solid #b32020 !important;' : 'border-left:4px solid #15803d !important;' }}">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bx {{ $last && !$silent ? 'bx-check-circle' : 'bx-error-circle' }}"
               style="font-size:1.1rem; color:{{ $last && !$silent ? '#15803d' : '#b32020' }};"></i>
            <span class="text-muted small">Last event received</span>
          </div>
          @if(!$last)
            <div style="font-size:1.2rem; font-weight:800; color:#b32020;">Nothing yet</div>
            <p class="text-muted small mb-0 mt-1">
              CentroVision has never successfully posted to this endpoint. Start with the
              <code>GET /health</code> request in the Postman collection.
            </p>
          @else
            <div style="font-size:1.4rem; font-weight:800; color:{{ $silent ? '#b32020' : '#15803d' }};">
              {{ $last->diffForHumans() }}
            </div>
            <p class="text-muted small mb-0 mt-1">
              {{ $last->format('d M Y, H:i:s') }}
              @if($silent) — nothing for over 30 minutes. Check the gateway. @endif
            </p>
          @endif
        </div>
      </div>

      @foreach(['processed' => ['Processed', '#15803d', 'bx-check'],
                'queued' => ['Waiting', '#b45309', 'bx-time'],
                'failed' => ['Failed', '#b32020', 'bx-x-circle'],
                'rejected' => ['Rejected', '#9f1239', 'bx-block']] as $key => $meta)
        <div class="col-6 col-xl-2">
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bx {{ $meta[2] }}" style="font-size:1.1rem; color:{{ $meta[1] }};"></i>
              <span class="text-muted small">{{ $meta[0] }}</span>
            </div>
            <div style="font-size:1.5rem; font-weight:800; color:{{ $meta[1] }};">
              {{ number_format($counts[$key] ?? 0) }}
            </div>
          </div>
        </div>
      @endforeach
    </div>

    <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 mb-4">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Status</label>
          <select name="status" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['queued','processed','failed','rejected'] as $s)
              <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold mb-1">Endpoint</label>
          <select name="endpoint" class="form-select form-select-sm">
            <option value="">All</option>
            @foreach(['events','recordings.ready','agent-sessions','cdr.import'] as $e)
              <option value="{{ $e }}" @selected(($filters['endpoint'] ?? '') === $e)>{{ $e }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small fw-semibold mb-1">Call id, event id or event type</label>
          <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. CV-20260819-000512">
        </div>
        <div class="col-6 col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-hitech-primary btn-sm flex-grow-1"><i class="bx bx-search me-1"></i>Search</button>
          <a href="{{ route('user.telephony.admin.webhook-log') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
      </form>
    </div>

    <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead>
            <tr>
              <th>Received</th>
              <th>Endpoint</th>
              <th>Event</th>
              <th>Call</th>
              <th>Source IP</th>
              <th>Signature</th>
              <th>Status</th>
              <th class="text-end">Tries</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($deliveries as $d)
              @php
                $statusTone = match ($d->status) {
                  'processed' => ['#dcfce7', '#15803d'],
                  'queued'    => ['#e0e7ff', '#3730a3'],
                  'failed'    => ['#fee2e2', '#991b1b'],
                  'rejected'  => ['#ffe4e6', '#9f1239'],
                  default     => ['#f1f5f9', '#475569'],
                };
              @endphp
              <tr>
                <td class="small text-muted text-nowrap">{{ $d->received_at?->format('d M, H:i:s') }}</td>
                <td class="small"><code>{{ $d->endpoint }}</code></td>
                <td class="small">{{ $d->event_type ?: '—' }}</td>
                <td class="small text-muted">{{ $d->call_uid ?: '—' }}</td>
                <td class="small text-muted">{{ $d->source_ip ?: '—' }}</td>
                <td>
                  @if($d->signature_valid)
                    <i class="bx bx-shield-quarter text-success" title="Signature verified"></i>
                  @else
                    <i class="bx bx-shield-x text-danger" title="Signature not verified"></i>
                  @endif
                </td>
                <td>
                  <span class="badge rounded-pill" style="background:{{ $statusTone[0] }}; color:{{ $statusTone[1] }};">
                    {{ ucfirst($d->status) }}
                  </span>
                  @if($d->error)
                    <div class="text-danger" style="font-size:.7rem;">{{ \Illuminate\Support\Str::limit($d->error, 60) }}</div>
                  @endif
                </td>
                <td class="text-end small">{{ $d->attempts }}</td>
                <td class="text-end text-nowrap">
                  <a href="{{ route('user.telephony.admin.delivery', $d->id) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Payload</a>
                  @if(in_array($d->status, ['failed', 'queued'], true))
                    <form method="POST" action="{{ route('user.telephony.admin.delivery.replay', $d->id) }}" class="d-inline">
                      @csrf
                      <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit">Replay</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center py-5">
                  <i class="bx bx-transfer text-muted" style="font-size:2.4rem;"></i>
                  <p class="text-muted mb-1 mt-2">No deliveries yet.</p>
                  <p class="text-muted small mb-0">
                    Rejected requests never reach this table — a bad signature is refused by the
                    middleware before the controller runs, and is recorded in the application log instead.
                  </p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($deliveries->hasPages())
        <div class="p-3 border-top">{{ $deliveries->links() }}</div>
      @endif
    </div>

  </div>
</div>
@endsection
