@extends('layouts/layoutMaster')

@section('title', 'Telephony connection')

@section('content')
@include('tenant.telephony._partials._helpers')
<div class="layout-full-width">

  @include('tenant.telephony._partials._header', [
    'title' => 'Connection',
    'subtitle' => 'The credentials CentroVision uses, and proof of what they have actually sent.',
    'crumb' => 'Connection',
  ])

  <div class="px-4">

    {{-- ── The secret, shown exactly once ────────────────────────────────
         Encrypted at rest and never rendered again, so this is the only
         chance to copy it. Said plainly rather than left to be discovered. --}}
    @if($justIssued)
      <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden mb-4"
           style="border-left:4px solid #15803d !important;">
        <div class="p-4">
          <h6 class="fw-bold mb-1" style="color:#15803d;">
            <i class="bx bx-key me-1"></i>
            {{ $justIssued['rotated'] ? 'New secret issued for' : 'Credentials created for' }}
            "{{ $justIssued['label'] }}"
          </h6>
          <p class="text-danger small fw-semibold mb-3">
            Copy the shared secret now. It is encrypted in the database and cannot be shown again —
            if you lose it, rotate to get a new one.
          </p>

          @foreach([
            ['API key', $justIssued['api_key'], 'Sent by the vendor on every request as Authorization: Bearer', 'apiKeyVal'],
            ['Shared secret', $justIssued['secret'], 'Used to compute the signature. Never sent over the wire.', 'secretVal'],
          ] as [$label, $value, $hint, $id])
            <div class="mb-3">
              <label class="form-label small fw-semibold mb-1">{{ $label }}</label>
              <div class="input-group">
                <input type="text" class="form-control font-monospace" id="{{ $id }}" value="{{ $value }}" readonly
                       style="font-size:.82rem;" onclick="this.select();">
                <button class="btn btn-outline-secondary" type="button"
                        onclick="(function(b){var i=document.getElementById('{{ $id }}');i.select();document.execCommand('copy');var t=b.innerHTML;b.innerHTML='Copied';setTimeout(function(){b.innerHTML=t;},1200);})(this)">
                  Copy
                </button>
              </div>
              <div class="form-text">{{ $hint }}</div>
            </div>
          @endforeach

          <div class="alert alert-warning mb-0 small">
            <strong>Send these in two separate messages.</strong> The key alone cannot write anything —
            a request also needs a valid signature made with the secret. Putting both in one email
            thread means one intercepted mailbox is enough to forge call records into your HR system.
            @if($justIssued['rotated'] && ($justIssued['grace_days'] ?? 0) > 0)
              <br><br>The previous secret keeps working for
              <strong>{{ $justIssued['grace_days'] }} day(s)</strong>, so the vendor can switch over
              on their own schedule without dropping a call.
            @elseif($justIssued['rotated'])
              <br><br>No grace period was set, so <strong>the old secret stopped working immediately</strong>.
              The vendor will be refused until they update it.
            @endif
          </div>
        </div>
      </div>
    @endif

    {{-- ── Is it working? ───────────────────────────────────────────────── --}}
    @php
      $last = $stats['last_accepted'];
      $silent = $last && $last->diffInMinutes(now()) > 30;
      $never = !$last;
    @endphp

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-4">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100"
             style="border-left:4px solid {{ $never || $silent ? '#b32020' : '#15803d' }} !important;">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bx {{ $never || $silent ? 'bx-error-circle' : 'bx-check-circle' }}"
               style="font-size:1.1rem; color:{{ $never || $silent ? '#b32020' : '#15803d' }};"></i>
            <span class="text-muted small">Last accepted push</span>
          </div>
          @if($never)
            <div style="font-size:1.2rem; font-weight:800; color:#b32020;">Never</div>
            <p class="text-muted small mb-0 mt-1">
              Nothing has been accepted yet. Once the vendor has the credentials, ask them to run
              <code>GET /health</code> first.
            </p>
          @else
            <div style="font-size:1.4rem; font-weight:800; color:{{ $silent ? '#b32020' : '#15803d' }};">
              {{ $last->diffForHumans() }}
            </div>
            <p class="text-muted small mb-0 mt-1">
              {{ $last->format('d M Y, H:i:s') }}@if($silent) — quiet for over 30 minutes.@endif
            </p>
          @endif
        </div>
      </div>

      @php
        $tiles = [
          ['Pushes today', number_format($stats['today']), 'bx-upload', '#0f172a'],
          ['Accepted (30d)', number_format($stats['accepted_30d']), 'bx-check', '#15803d'],
          ['Refused (30d)', number_format($stats['rejected_30d']), 'bx-block', $stats['rejected_30d'] > 0 ? '#b32020' : '#64748b'],
          ['Calls recorded', number_format($stats['calls']), 'bx-phone', '#0e6b62'],
        ];
      @endphp
      @foreach($tiles as [$label, $value, $icon, $color])
        <div class="col-6 col-xl-2">
          <div class="hitech-card-white border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bx {{ $icon }}" style="font-size:1.1rem; color:{{ $color }};"></i>
              <span class="text-muted small">{{ $label }}</span>
            </div>
            <div style="font-size:1.5rem; font-weight:800; color:{{ $color }};">{{ $value }}</div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- ── Where they send ──────────────────────────────────────────────── --}}
    <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
      <h6 class="fw-bold mb-3">Endpoint</h6>
      @foreach([
        ['Push call events here', $endpoint, 'epVal'],
        ['Handshake / health check', $healthUrl, 'hcVal'],
      ] as [$label, $value, $id])
        <div class="mb-3">
          <label class="form-label small fw-semibold mb-1">{{ $label }}</label>
          <div class="input-group">
            <input type="text" class="form-control font-monospace" id="{{ $id }}" value="{{ $value }}" readonly
                   style="font-size:.82rem;" onclick="this.select();">
            <button class="btn btn-outline-secondary" type="button"
                    onclick="(function(b){var i=document.getElementById('{{ $id }}');i.select();document.execCommand('copy');var t=b.innerHTML;b.innerHTML='Copied';setTimeout(function(){b.innerHTML=t;},1200);})(this)">
              Copy
            </button>
          </div>
        </div>
      @endforeach
      <p class="text-muted small mb-0">
        The full contract, a runnable Postman collection and working code samples live in
        <code>docs/telephony/</code>.
      </p>
    </div>

    @if($configFallback['in_use'])
      <div class="alert alert-warning">
        <i class="bx bx-info-circle me-1"></i>
        <strong>Currently running on the <code>.env</code> credentials.</strong>
        That still works, but it means rotating the key needs server access. Create a credential
        below and it takes over automatically — no deploy, no config change.
      </div>
    @elseif($configFallback['configured'])
      <div class="alert alert-info">
        <i class="bx bx-info-circle me-1"></i>
        A key is also set in <code>.env</code>, but a credential below is active and
        <strong>takes precedence</strong>. Clearing the env value would avoid confusion later.
      </div>
    @endif

    <div class="row g-4">

      {{-- ── Credentials ──────────────────────────────────────────────── --}}
      <div class="col-12 col-xl-7">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden mb-4">
          <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Credentials</h6>
            <p class="text-muted small mb-0">One per vendor. Deactivating takes effect within a minute.</p>
          </div>

          <div class="table-responsive">
            <table class="table mb-0">
              <thead>
                <tr><th>Vendor</th><th>API key</th><th>Allowlist</th><th class="text-end">Used</th><th>Last seen</th><th></th></tr>
              </thead>
              <tbody>
                @forelse($credentials as $c)
                  <tr>
                    <td>
                      <span class="fw-semibold">{{ $c->label }}</span>
                      @if(!$c->is_active)
                        <span class="badge bg-label-secondary rounded-pill ms-1">Inactive</span>
                      @endif
                      @if($c->isRotating())
                        <div class="text-warning" style="font-size:.72rem;">
                          <i class="bx bx-refresh"></i> old secret valid until
                          {{ $c->previous_secret_expires_at->format('d M, H:i') }}
                        </div>
                      @endif
                    </td>
                    <td class="font-monospace small">{{ $c->maskedKey() }}</td>
                    <td class="small">{{ $c->allowed_ips ?: 'Any (no restriction)' }}</td>
                    <td class="text-end small fw-semibold">{{ number_format($c->use_count) }}</td>
                    <td class="small text-muted">
                      {{ $c->last_used_at ? $c->last_used_at->diffForHumans() : 'Never' }}
                      @if($c->last_used_ip)
                        <div style="font-size:.72rem;">from {{ $c->last_used_ip }}</div>
                      @endif
                    </td>
                    <td class="text-end text-nowrap">
                      <button class="btn btn-sm btn-outline-secondary rounded-pill"
                              data-bs-toggle="collapse" data-bs-target="#cred{{ $c->id }}">Manage</button>
                    </td>
                  </tr>
                  <tr class="collapse" id="cred{{ $c->id }}">
                    <td colspan="6" style="background:#f8fafc;">
                      <form method="POST" action="{{ route('user.telephony.admin.connection.update', $c->id) }}"
                            class="row g-2 align-items-end mb-3">
                        @csrf @method('PUT')
                        <div class="col-12 col-md-3">
                          <label class="form-label small fw-semibold mb-1">Vendor name</label>
                          <input type="text" name="label" value="{{ $c->label }}" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 col-md-4">
                          <label class="form-label small fw-semibold mb-1">Allowed IPs (comma separated, blank = any)</label>
                          <input type="text" name="allowed_ips" value="{{ $c->allowed_ips }}"
                                 class="form-control form-control-sm" placeholder="45.120.40.194">
                        </div>
                        <div class="col-6 col-md-2">
                          <label class="form-label small fw-semibold mb-1">Active</label>
                          <select name="is_active" class="form-select form-select-sm">
                            <option value="1" @selected($c->is_active)>Yes</option>
                            <option value="0" @selected(!$c->is_active)>No</option>
                          </select>
                        </div>
                        <div class="col-6 col-md-2">
                          <button class="btn btn-hitech-primary btn-sm w-100" type="submit">Save</button>
                        </div>
                      </form>

                      <div class="d-flex flex-wrap gap-2 align-items-end pt-3 border-top">
                        <form method="POST" action="{{ route('user.telephony.admin.connection.rotate', $c->id) }}"
                              class="d-flex gap-2 align-items-end"
                              onsubmit="return confirm('Issue a new secret for {{ $c->label }}? You must send it to the vendor.');">
                          @csrf
                          <div>
                            <label class="form-label small fw-semibold mb-1">Keep old secret working for</label>
                            <select name="grace_days" class="form-select form-select-sm" style="width:150px;">
                              <option value="14" selected>14 days</option>
                              <option value="7">7 days</option>
                              <option value="30">30 days</option>
                              <option value="0">No grace — cut over now</option>
                            </select>
                          </div>
                          <button class="btn btn-outline-primary btn-sm" type="submit">
                            <i class="bx bx-refresh me-1"></i>Rotate secret
                          </button>
                        </form>

                        <form method="POST" action="{{ route('user.telephony.admin.connection.destroy', $c->id) }}"
                              onsubmit="return confirm('Delete this credential? Any request using it will immediately be refused.');">
                          @csrf @method('DELETE')
                          <button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
                        </form>
                      </div>

                      @if($c->notes)
                        <p class="text-muted small mb-0 mt-3">{{ $c->notes }}</p>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center py-5">
                      <i class="bx bx-key text-muted" style="font-size:2.4rem;"></i>
                      <p class="text-muted mb-1 mt-2">No credentials yet.</p>
                      <p class="text-muted small mb-0">Generate one on the right, then send it to the vendor.</p>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        {{-- ── Volume ─────────────────────────────────────────────────── --}}
        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="p-3 border-bottom">
            <h6 class="fw-bold mb-0">Pushes over the last 30 days</h6>
            <p class="text-muted small mb-0">Green accepted, red refused. Counts requests, not calls —
              a vendor can be pushing steadily while every payload is rejected.</p>
          </div>
          <div class="p-3">
            @php $peak = max(1, collect($stats['series'])->max(fn ($d) => $d['accepted'] + $d['rejected'])); @endphp
            <div class="d-flex align-items-end gap-1" style="height:120px;">
              @foreach($stats['series'] as $d)
                @php
                  $total = $d['accepted'] + $d['rejected'];
                  $h = $total > 0 ? max(3, round($total / $peak * 110)) : 1;
                  $rejShare = $total > 0 ? round($d['rejected'] / $total * $h) : 0;
                @endphp
                <div class="flex-fill d-flex flex-column justify-content-end" style="height:110px;"
                     title="{{ $d['day']->format('D d M') }} — {{ $d['accepted'] }} accepted, {{ $d['rejected'] }} refused">
                  @if($rejShare > 0)
                    <div style="height:{{ $rejShare }}px; background:#b32020; border-radius:2px 2px 0 0;"></div>
                  @endif
                  <div style="height:{{ max(1, $h - $rejShare) }}px; background:{{ $total > 0 ? '#15803d' : '#e2e8f0' }}; border-radius:{{ $rejShare > 0 ? '0' : '2px 2px 0 0' }};"></div>
                </div>
              @endforeach
            </div>
            <div class="d-flex justify-content-between text-muted mt-2" style="font-size:.7rem;">
              <span>{{ $stats['series'][0]['day']->format('d M') }}</span>
              <span>today</span>
            </div>
          </div>
        </div>
      </div>

      {{-- ── Generate + breakdown ─────────────────────────────────────── --}}
      <div class="col-12 col-xl-5">
        <div class="hitech-card-white border-0 shadow-sm rounded-4 p-4 mb-4">
          <h6 class="fw-bold mb-1">Generate credentials</h6>
          <p class="text-muted small mb-3">
            Creates an API key and a shared secret. The secret is shown once and then encrypted —
            copy it before leaving this page.
          </p>

          <form method="POST" action="{{ route('user.telephony.admin.connection.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label small fw-semibold mb-1">Vendor name <span class="text-danger">*</span></label>
              <input type="text" name="label" value="{{ old('label', 'CentroVision') }}"
                     class="form-control form-control-sm" required>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold mb-1">Allowed IPs</label>
              <input type="text" name="allowed_ips" value="{{ old('allowed_ips') }}"
                     class="form-control form-control-sm" placeholder="45.120.40.194">
              <div class="form-text">
                Comma separated, CIDR accepted. <strong>Leave blank for the first handshake</strong> —
                if the address is wrong the vendor is refused before they can prove the credentials
                work, and it looks to them like a dead endpoint. Set it once you see their real IP
                on the Webhook Log.
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label small fw-semibold mb-1">Notes</label>
              <textarea name="notes" rows="2" class="form-control form-control-sm"
                        placeholder="Contact, ticket reference, anything worth remembering">{{ old('notes') }}</textarea>
            </div>
            <button type="submit" class="btn btn-hitech-primary btn-sm w-100">
              <i class="bx bx-key me-1"></i>Generate
            </button>
          </form>
        </div>

        <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden mb-4">
          <div class="p-3 border-bottom"><h6 class="fw-bold mb-0">What they are sending</h6></div>
          <div class="table-responsive">
            <table class="table mb-0">
              <thead><tr><th>Endpoint</th><th class="text-end">Requests</th><th>Last</th></tr></thead>
              <tbody>
                @forelse($stats['by_endpoint'] as $e)
                  <tr>
                    <td class="small"><code>{{ $e->endpoint }}</code></td>
                    <td class="text-end small fw-semibold">{{ number_format($e->n) }}</td>
                    <td class="small text-muted">{{ \Carbon\Carbon::parse($e->last_seen)->diffForHumans() }}</td>
                  </tr>
                @empty
                  <tr><td colspan="3" class="text-center py-4 text-muted small">Nothing received yet.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>

        @if($stats['by_event']->isNotEmpty())
          <div class="hitech-card-white border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="p-3 border-bottom">
              <h6 class="fw-bold mb-0">Event types received</h6>
              <p class="text-muted small mb-0">If <code>manual_dial</code> events never appear here, UAT-05 has not really passed.</p>
            </div>
            <div class="table-responsive">
              <table class="table mb-0">
                <thead><tr><th>Event</th><th class="text-end">Count</th></tr></thead>
                <tbody>
                  @foreach($stats['by_event'] as $e)
                    <tr>
                      <td class="small"><code>{{ $e->event_type }}</code></td>
                      <td class="text-end small fw-semibold">{{ number_format($e->n) }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
