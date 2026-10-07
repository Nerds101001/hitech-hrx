@extends('layouts/layoutMaster')

@section('title', 'Tally Push — Request #' . $request->id)

@section('content')
@php $badge = fn($s) => match($s){ 'accepted' => 'bg-label-success', 'rejected' => 'bg-label-danger', 'failed' => 'bg-label-warning', default => 'bg-label-secondary' }; @endphp
<div class="layout-full-width">
  <div class="px-4 pt-3">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="fw-bold mb-0"><i class="bx bx-detail me-1"></i> Request #{{ $request->id }}</h4>
      <a href="{{ route('user.tally-push.admin.request-log') }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-arrow-back me-1"></i> Back to log</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <div class="row g-3 small">
          <div class="col-md-3"><div class="text-muted">Received</div><div class="fw-semibold">{{ $request->received_at?->format('d M Y, H:i:s') }}</div></div>
          <div class="col-md-3"><div class="text-muted">Source IP</div><div class="fw-semibold font-monospace">{{ $request->source_ip ?: '—' }}</div></div>
          <div class="col-md-3"><div class="text-muted">Endpoint</div><div class="fw-semibold font-monospace">{{ $request->endpoint }}</div></div>
          <div class="col-md-3"><div class="text-muted">Outcome</div><div><span class="badge {{ $badge($request->status) }}">{{ $request->status }}</span> <span class="text-muted">HTTP {{ $request->http_status ?: '—' }}</span></div></div>
          <div class="col-md-3"><div class="text-muted">Signature</div><div>@if($request->signature_valid)<span class="text-success">valid</span>@else<span class="text-danger">not verified</span>@endif</div></div>
          <div class="col-md-3"><div class="text-muted">Body size</div><div class="fw-semibold">{{ $request->bytes !== null ? number_format($request->bytes) . ' bytes' : '—' }}</div></div>
          <div class="col-md-6"><div class="text-muted">Credential</div><div class="fw-semibold">{{ $request->credential_label ?: '—' }}</div></div>
          @if($request->error)<div class="col-12"><div class="text-muted">Reason</div><div class="fw-semibold text-danger">{{ $request->error }}</div></div>@endif
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-header bg-transparent fw-bold"><i class="bx bx-code-alt me-1"></i> Headers (as received)</div>
      <div class="card-body"><pre class="bg-light rounded-3 p-3 mb-0" style="font-size:.8rem; max-height:220px; overflow:auto;">{{ json_encode($request->headers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-header bg-transparent fw-bold"><i class="bx bx-file me-1"></i> Body excerpt</div>
      <div class="card-body">
        @if($pretty === null)
          <p class="text-muted small mb-0">No body was stored. Accepted pushes keep only the size, not the content; refused requests keep a bounded excerpt.</p>
        @else
          <pre class="bg-light rounded-3 p-3 mb-0" style="font-size:.8rem; max-height:420px; overflow:auto;">{{ $pretty }}</pre>
          <div class="form-text mt-2">Refused requests store at most the first ~2 KB — an unauthenticated caller does not get to write unlimited data.</div>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection
