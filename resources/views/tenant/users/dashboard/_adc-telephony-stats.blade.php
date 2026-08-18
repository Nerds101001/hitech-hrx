{{-- Telephony call tracking — HRX-initiated (click-to-call) plus calls the
     vendor webhook reports that HRX never placed (manual dialing, or any
     path outside the click-to-call button). See App\Models\TelephonyCall
     and App\Http\Controllers\Api\TelephonyWebhookController. --}}
<div class="adc-card mb-4">
  <div class="adc-card-head">
    <div><h6 class="adc-card-title">Calls Tracked</h6><span class="adc-card-sub">{{ $telephonyStats['today'] ?? 0 }} today</span></div>
  </div>
  @if(($telephonyStats['total'] ?? 0) === 0)
    <div class="adc-empty adc-empty-sm"><i class="bx bx-phone-off"></i><span>No calls tracked yet.</span></div>
  @else
    <div class="d-flex align-items-baseline gap-2 mb-3">
      <span style="font-size:1.8rem; font-weight:800; color:#0f172a;">{{ number_format($telephonyStats['total']) }}</span>
      <span class="text-muted small">total calls</span>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <span class="adc-badge adc-tone-primary"><i class="bx bx-mouse me-1"></i>{{ $telephonyStats['hrx'] }} via HRX</span>
      <span class="adc-badge adc-tone-warning"><i class="bx bx-phone-call me-1"></i>{{ $telephonyStats['external'] }} elsewhere</span>
      <span class="adc-badge adc-tone-success"><i class="bx bx-phone-incoming me-1"></i>{{ $telephonyStats['connected'] }} connected</span>
    </div>
  @endif
</div>
