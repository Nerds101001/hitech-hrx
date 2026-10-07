@extends('layouts/layoutMaster')

@section('title', 'Tally Push — Request log')

@section('content')
@php
  $fixes = [
    'ip_not_allowed' => 'This IP is not on the allowlist. Add it on the Connection page if it is really the office PC.',
    'no_allowlist_configured' => 'No allowlist is set, and one is required. Add the office PC IP on the Connection page.',
    'invalid_api_key' => 'Wrong or missing API key. Check TALLY_PUSH_API_KEY on the office PC matches an active credential.',
    'invalid_signature' => 'Signature did not match. The office PC secret (TALLY_PUSH_SECRET) differs from the server credential.',
    'stale_timestamp' => 'The office PC clock is off by more than the allowed skew. Sync its time.',
    'missing_timestamp' => 'The push did not send X-HRX-Timestamp. The puller is out of date.',
    'missing_nonce' => 'The push did not send X-HRX-Nonce. The puller is out of date.',
    'replayed_nonce' => 'A nonce was reused — usually a retry sent too fast, or a captured request replayed.',
    'missing_signature' => 'The push did not send X-HRX-Signature. The puller is out of date.',
    'payload_too_large' => 'Body exceeded the size cap. Push a smaller date range.',
    'locked_out' => 'This IP sent too many bad requests and is temporarily locked out.',
  ];
  $badge = fn($s) => match($s){ 'accepted' => 'bg-label-success', 'rejected' => 'bg-label-danger', 'failed' => 'bg-label-warning', default => 'bg-label-secondary' };
@endphp
<div class="layout-full-width">
  <div class="px-4 pt-3">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
      <div>
        <h4 class="fw-bold mb-0"><i class="bx bx-list-ul me-1"></i> Tally Push — Request log</h4>
        <div class="text-muted small">Every inbound hit on the push endpoint, accepted or rejected, with time, source IP and reason.</div>
      </div>
      <div class="btn-group">
        <a href="{{ route('user.tally-push.admin.connection') }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-cog me-1"></i> Connection</a>
        <a href="{{ route('user.tally-push.admin.request-log') }}" class="btn btn-sm btn-primary"><i class="bx bx-list-ul me-1"></i> Request log</a>
      </div>
    </div>
    <hr class="mt-2 mb-4">

    {{-- Status chips --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
      @foreach(['accepted' => '#15803d', 'rejected' => '#b32020', 'failed' => '#b45309'] as $st => $col)
        <a href="{{ route('user.tally-push.admin.request-log', ['status' => $st]) }}" class="text-decoration-none">
          <span class="badge {{ $badge($st) }}" style="font-size:.8rem;">{{ ucfirst($st) }}: {{ number_format($counts[$st] ?? 0) }}</span>
        </a>
      @endforeach
      <a href="{{ route('user.tally-push.admin.request-log') }}" class="text-decoration-none"><span class="badge bg-label-dark" style="font-size:.8rem;">All: {{ number_format(collect($counts)->sum()) }}</span></a>
    </div>

    {{-- 7-day refusals panel --}}
    @if($rejections->isNotEmpty())
      <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-left:4px solid #b32020 !important;">
        <div class="card-body">
          <h6 class="fw-bold mb-3" style="color:#b32020;"><i class="bx bx-shield-x me-1"></i> Requests refused in the last 7 days</h6>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr class="small text-muted text-uppercase"><th>Source IP</th><th>Reason</th><th>Count</th><th>Last seen</th><th>What to do</th></tr></thead>
              <tbody>
              @foreach($rejections as $r)
                <tr>
                  <td class="font-monospace small">{{ $r->source_ip ?: '—' }}</td>
                  <td><span class="badge bg-label-danger">{{ $r->reason }}</span></td>
                  <td class="fw-bold">{{ number_format($r->n) }}</td>
                  <td class="small">{{ \Illuminate\Support\Carbon::parse($r->last_seen)->diffForHumans() }}</td>
                  <td class="small text-muted">{{ $fixes[trim((string)$r->reason)] ?? 'Open the request to see the full reason.' }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="row g-2 align-items-end mb-3">
      <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Outcome</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          @foreach(['accepted','rejected','failed'] as $st)<option value="{{ $st }}" {{ ($filters['status'] ?? '') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Source IP</label><input name="ip" class="form-control form-control-sm font-monospace" value="{{ $filters['ip'] ?? '' }}" placeholder="exact IP"></div>
      <div class="col-md-4"><label class="form-label small fw-semibold mb-1">Search (IP / endpoint / reason)</label><input name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}"></div>
      <div class="col-md-2 d-flex gap-2"><button class="btn btn-sm btn-primary w-100">Filter</button><a href="{{ route('user.tally-push.admin.request-log') }}" class="btn btn-sm btn-outline-secondary">Reset</a></div>
    </form>

    {{-- The log --}}
    <div class="card border-0 shadow-sm rounded-4">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr class="small text-muted text-uppercase"><th class="ps-3">Received</th><th>Endpoint</th><th>Source IP</th><th>Sig</th><th>Outcome</th><th>HTTP</th><th>Bytes</th><th></th></tr></thead>
          <tbody>
          @forelse($requests as $req)
            <tr>
              <td class="ps-3 small">{{ $req->received_at?->format('d M, H:i:s') }}<br><span class="text-muted">{{ $req->received_at?->diffForHumans() }}</span></td>
              <td class="small font-monospace">{{ $req->endpoint }}</td>
              <td class="small font-monospace">{{ $req->source_ip ?: '—' }}</td>
              <td>@if($req->signature_valid)<i class="bx bxs-shield text-success" title="Signature valid"></i>@else<i class="bx bx-shield-x text-danger" title="Not verified"></i>@endif</td>
              <td><span class="badge {{ $badge($req->status) }}">{{ $req->status }}</span>@if($req->error)<div class="small text-muted" style="max-width:320px;">{{ \Illuminate\Support\Str::limit($req->error, 80) }}</div>@endif</td>
              <td class="small">{{ $req->http_status ?: '—' }}</td>
              <td class="small">{{ $req->bytes !== null ? number_format($req->bytes) : '—' }}</td>
              <td class="text-end pe-3"><a href="{{ route('user.tally-push.admin.request', $req) }}" class="btn btn-sm btn-outline-secondary">Open</a></td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No requests logged yet. Once the office PC starts pushing, every attempt shows here.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      @if($requests->hasPages())<div class="card-footer bg-transparent">{{ $requests->links() }}</div>@endif
    </div>

  </div>
</div>
@endsection
