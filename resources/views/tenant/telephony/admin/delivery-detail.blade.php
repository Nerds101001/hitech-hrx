@extends('layouts/layoutMaster')

@section('title', 'Webhook payload')

@section('content')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Webhook payload',
    'subtitle' => 'Delivery #' . $delivery->id . ' — the exact bytes CentroVision sent, before HRX interpreted anything.',
    'crumb' => 'Payload',
    'tabs' => false,
  ])

  <div class="px-4">

    <a href="{{ route('user.telephony.admin.webhook-log') }}" class="btn btn-sm btn-outline-secondary rounded-pill mb-3">
      <i class="bx bx-arrow-back me-1"></i>Back to the log
    </a>

    <div class="row g-4">
      <div class="col-12 col-xl-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4">
          <h6 class="fw-bold mb-3">Delivery</h6>
          <table class="table table-sm mb-0">
            <tbody>
              @php
                $meta = [
                  'Received' => $delivery->received_at?->format('d M Y, H:i:s'),
                  'Processed' => $delivery->processed_at?->format('d M Y, H:i:s') ?? 'Not yet',
                  'Endpoint' => $delivery->endpoint,
                  'Event type' => $delivery->event_type ?: '—',
                  'Event id' => $delivery->event_id ?: '—',
                  'Call id' => $delivery->call_uid ?: '—',
                  'Source IP' => $delivery->source_ip ?: '—',
                  'Signature verified' => $delivery->signature_valid ? 'Yes' : 'No',
                  'Status' => ucfirst($delivery->status),
                  'Attempts' => $delivery->attempts,
                ];
              @endphp
              @foreach($meta as $label => $value)
                <tr>
                  <td class="text-muted small" style="width:45%;">{{ $label }}</td>
                  <td class="small fw-semibold" style="word-break:break-all;">{{ $value }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>

          @if($delivery->error)
            <div class="alert alert-danger mt-3 mb-0 small">
              <strong>Error</strong><br>{{ $delivery->error }}
            </div>
          @endif

          @if($delivery->call_uid)
            <a href="{{ route('user.telephony.reports.call', $delivery->call_uid) }}"
               class="btn btn-sm btn-hitech-primary w-100 mt-3">Open the call</a>
          @endif

          @if(in_array($delivery->status, ['failed', 'queued'], true))
            <form method="POST" action="{{ route('user.telephony.admin.delivery.replay', $delivery->id) }}" class="mt-2">
              @csrf
              <button class="btn btn-sm btn-outline-primary w-100" type="submit">Replay this delivery</button>
            </form>
            <p class="text-muted mt-2 mb-0" style="font-size:.72rem;">
              Replays the original bytes. Normalisation is idempotent, so this is safe to press twice.
            </p>
          @endif
        </div>

        @if($delivery->headers)
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mt-4">
            <h6 class="fw-bold mb-3">Headers</h6>
            <table class="table table-sm mb-0">
              <tbody>
                @foreach($delivery->headers as $k => $v)
                  <tr>
                    <td class="text-muted small" style="width:45%;">{{ $k }}</td>
                    <td class="small" style="word-break:break-all;">{{ $v ?: '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      <div class="col-12 col-xl-8">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4">
          <h6 class="fw-bold mb-3">Payload</h6>
          <pre class="mb-0 p-3 rounded-3" style="background:#0f172a; color:#e2e8f0; font-size:.78rem; max-height:70vh; overflow:auto;">{{ $pretty }}</pre>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
