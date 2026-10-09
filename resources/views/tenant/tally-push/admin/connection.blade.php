@extends('layouts/layoutMaster')

@section('title', 'Tally Push — Connection')

@section('content')
<div class="layout-full-width">
  <div class="px-4 pt-3">

    {{-- Header + tabs --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
      <div>
        <h4 class="fw-bold mb-0"><i class="bx bx-plug me-1"></i> Tally Push</h4>
        <div class="text-muted small">The office-PC credentials and IP allowlist, and proof the push is arriving. Visible to you only.</div>
      </div>
      <div class="btn-group">
        <a href="{{ route('user.tally-push.admin.connection') }}" class="btn btn-sm btn-primary"><i class="bx bx-cog me-1"></i> Connection</a>
        <a href="{{ route('user.tally-push.admin.request-log') }}" class="btn btn-sm btn-outline-primary"><i class="bx bx-list-ul me-1"></i> Request log</a>
      </div>
    </div>
    <hr class="mt-2 mb-4">

    @if(session('success'))<div class="alert alert-success py-2 small">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger py-2 small">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif

    {{-- One-time secret reveal --}}
    @if($justIssued)
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="border-left:4px solid #15803d !important;">
        <div class="p-4">
          <h6 class="fw-bold mb-1" style="color:#15803d;">
            <i class="bx bx-key me-1"></i>
            {{ $justIssued['rotated'] ? 'New secret issued for' : 'Credentials created for' }} "{{ $justIssued['label'] }}"
          </h6>
          <p class="text-danger small fw-semibold mb-3">
            Copy the shared secret now — it is encrypted in the database and can never be shown again. If you lose it, rotate to get a new one.
          </p>
          @foreach([
            ['API key', $justIssued['api_key'], 'Goes in the office PC .env as TALLY_PUSH_API_KEY. Sent on every push as Authorization: Bearer.', 'tpApiKey'],
            ['Shared secret', $justIssued['secret'], 'Goes in the office PC .env as TALLY_PUSH_SECRET. Signs the request; never sent over the wire.', 'tpSecret'],
          ] as [$label, $value, $hint, $id])
            <div class="mb-3">
              <label class="form-label small fw-semibold mb-1">{{ $label }}</label>
              <div class="input-group">
                <input type="text" class="form-control font-monospace" id="{{ $id }}" value="{{ $value }}" readonly style="font-size:.82rem;" onclick="this.select();">
                <button class="btn btn-outline-secondary" type="button"
                  onclick="(function(b){var i=document.getElementById('{{ $id }}');i.select();document.execCommand('copy');var t=b.innerHTML;b.innerHTML='Copied';setTimeout(function(){b.innerHTML=t;},1200);})(this)">Copy</button>
              </div>
              <div class="form-text">{{ $hint }}</div>
            </div>
          @endforeach
          <div class="alert alert-warning mb-0 small">
            <strong>Put the target URL on the office PC too:</strong>
            <code>TALLY_PUSH_TARGET_URL={{ $endpoint }}</code>.
            @if($justIssued['rotated'] && ($justIssued['grace_days'] ?? 0) > 0)
              <br><br>The previous secret keeps working for <strong>{{ $justIssued['grace_days'] }} day(s)</strong>, so the office PC can switch over without dropping a sync.
            @elseif($justIssued['rotated'])
              <br><br>No grace period was set, so <strong>the old secret stopped working immediately</strong> — the office PC is refused until its .env is updated.
            @endif
          </div>
        </div>
      </div>
    @endif

    {{-- Is it working? --}}
    @php
      $last = $stats['last_accepted'];
      $silent = $last && $last->diffInMinutes(now()) > 180;
      $never = !$last;
    @endphp
    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="border-left:4px solid {{ $never || $silent ? '#b32020' : '#15803d' }} !important;">
          <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bx {{ $never || $silent ? 'bx-error-circle' : 'bx-check-circle' }}" style="font-size:1.1rem;color:{{ $never || $silent ? '#b32020' : '#15803d' }};"></i>
            <span class="text-muted small">Last accepted push</span>
          </div>
          @if($never)
            <div style="font-size:1.2rem;font-weight:800;color:#b32020;">Never</div>
            <p class="text-muted small mb-0 mt-1">Nothing accepted yet. Generate credentials below, put them on the office PC, then watch the request log.</p>
          @else
            <div style="font-size:1.4rem;font-weight:800;color:{{ $silent ? '#b32020' : '#15803d' }};">{{ $last->diffForHumans() }}</div>
            <p class="text-muted small mb-0 mt-1">{{ $last->format('d M Y, H:i:s') }}@if($silent) — quiet for over 3 hours.@endif</p>
          @endif
        </div>
      </div>
      @php
        $tiles = [
          ['Requests today', number_format($stats['today']), 'bx-transfer', '#0f172a'],
          ['Accepted (30d)', number_format($stats['accepted_30d']), 'bx-check', '#15803d'],
          ['Refused (30d)', number_format($stats['rejected_30d']), 'bx-block', $stats['rejected_30d'] > 0 ? '#b32020' : '#64748b'],
          ['Endpoint', $killSwitchOn ? 'LIVE' : 'OFF (404)', $killSwitchOn ? 'bx-power-off' : 'bx-lock-alt', $killSwitchOn ? '#15803d' : '#b32020'],
        ];
      @endphp
      @foreach($tiles as [$label, $value, $icon, $color])
        <div class="col-6 col-xl-2">
          <div class="card border-0 shadow-sm rounded-4 p-3 h-100">
            <div class="d-flex align-items-center gap-2 mb-1"><i class="bx {{ $icon }}" style="font-size:1.1rem;color:{{ $color }};"></i><span class="text-muted small">{{ $label }}</span></div>
            <div style="font-size:1.4rem;font-weight:800;color:{{ $color }};">{{ $value }}</div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Backfill / re-sync a month --}}
    @php
      $activeJobs = $jobs->whereIn('status', ['pending', 'claimed', 'running'])->count();
    @endphp
    <div class="card border-0 shadow-sm rounded-4 mb-4" style="border-left:4px solid #6366f1 !important;">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
          <h6 class="fw-bold mb-0"><i class="bx bx-calendar-event me-1"></i> Sync a month to the live server</h6>
          @if($activeJobs > 0)<span class="badge bg-label-info"><i class="bx bx-loader bx-spin me-1"></i> {{ $activeJobs }} in progress</span>@endif
        </div>
        <p class="text-muted small mb-3">
          Pick a month and the office PC pulls those days from Tally and pushes them here. It's queued, not instant — the PC
          picks it up within a minute or two and this card shows the progress. Re-running a month is safe: it replaces, never duplicates.
        </p>

        {{-- Quick pick: each month of the financial year so far --}}
        <label class="form-label small fw-semibold mb-1">This financial year</label>
        <div class="d-flex flex-wrap gap-2 mb-3">
          @foreach($fyMonths as $mo)
            <form method="POST" action="{{ route('user.tally-push.admin.connection.backfill') }}" class="d-inline">
              @csrf
              <input type="hidden" name="from" value="{{ $mo['from'] }}">
              <input type="hidden" name="to" value="{{ $mo['to'] }}">
              <button class="btn btn-sm btn-outline-primary" type="submit">{{ $mo['label'] }}</button>
            </form>
          @endforeach
          @if(count($fyMonths))
            <form method="POST" action="{{ route('user.tally-push.admin.connection.backfill') }}" class="d-inline"
                  onsubmit="return confirm('Queue every month of this financial year so far? That's {{ count($fyMonths) }} jobs.');">
              @csrf
              <input type="hidden" name="from" value="{{ end($fyMonths)['from'] }}">
              <input type="hidden" name="to" value="{{ $fyMonths[0]['to'] }}">
              <button class="btn btn-sm btn-primary" type="submit"><i class="bx bx-calendar me-1"></i> Whole year so far</button>
            </form>
          @endif
        </div>

        {{-- Custom range --}}
        <form method="POST" action="{{ route('user.tally-push.admin.connection.backfill') }}" class="border rounded-3 p-3" style="background:#f8fafc;">
          @csrf
          <label class="form-label small fw-semibold mb-1">Or a custom range</label>
          <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3"><label class="form-label small text-muted mb-1">From</label><input type="date" name="from" class="form-control form-control-sm" required></div>
            <div class="col-6 col-md-3"><label class="form-label small text-muted mb-1">To</label><input type="date" name="to" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><button class="btn btn-sm btn-success w-100"><i class="bx bx-sync me-1"></i> Queue sync</button></div>
          </div>
          <div class="form-text mt-2">A range spanning several months is split into one job per month so each stays within the push limit.</div>
        </form>

        {{-- Snapshot refreshes: receivables + the ledger master, which aren't carried by the daily voucher push --}}
        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
          <span class="small fw-semibold me-1">Also refresh:</span>
          <form method="POST" action="{{ route('user.tally-push.admin.connection.refresh') }}" class="d-inline">
            @csrf <input type="hidden" name="kind" value="outstanding">
            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bx bx-receipt me-1"></i> Outstanding (receivables)</button>
          </form>
          <form method="POST" action="{{ route('user.tally-push.admin.connection.refresh') }}" class="d-inline">
            @csrf <input type="hidden" name="kind" value="parties">
            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bx bx-id-card me-1"></i> Customer ledgers (credit / GSTIN / contacts)</button>
          </form>
        </div>
        <div class="form-text mt-1">The Outstanding and Finance screens, and Credit &amp; Contacts, come from these snapshots rather than the day-by-day sync — refresh them when the figures look stale.</div>

        {{-- Recent jobs --}}
        @if($jobs->isNotEmpty())
          <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
              <thead><tr class="small text-muted text-uppercase"><th>Job</th><th>Type</th><th>Range</th><th>Status</th><th>Days</th><th>Invoices</th><th>Orders</th><th>Deliveries</th><th>Receipts</th><th>When</th></tr></thead>
              <tbody>
              @foreach($jobs as $j)
                @php
                  $badge = ['pending' => 'bg-label-secondary', 'claimed' => 'bg-label-info', 'running' => 'bg-label-info', 'done' => 'bg-label-success', 'failed' => 'bg-label-danger'][$j->status] ?? 'bg-label-secondary';
                  $spin = in_array($j->status, ['claimed', 'running']);
                @endphp
                <tr>
                  <td class="fw-semibold">{{ $j->label }}</td>
                  <td class="small">{{ $j->kindLabel() }}</td>
                  <td class="small text-muted">@if($j->from_date && $j->to_date){{ \Illuminate\Support\Carbon::parse($j->from_date)->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($j->to_date)->format('d M') }}@else<span class="text-muted">—</span>@endif</td>
                  <td><span class="badge {{ $badge }}">@if($spin)<i class="bx bx-loader bx-spin me-1"></i>@endif{{ $j->statusLabel() }}</span>
                      @if($j->status === 'failed' && $j->message)<i class="bx bx-info-circle text-danger ms-1" title="{{ $j->message }}"></i>@endif</td>
                  <td class="small">{{ $j->days_done }}@if($j->days_total)/{{ $j->days_total }}@endif</td>
                  <td class="small">{{ $j->status === 'done' || $j->invoices ? number_format($j->invoices) : '—' }}</td>
                  <td class="small">{{ $j->status === 'done' || $j->sale_orders ? number_format($j->sale_orders) : '—' }}</td>
                  <td class="small">{{ $j->status === 'done' || $j->deliveries ? number_format($j->deliveries) : '—' }}</td>
                  <td class="small">{{ $j->status === 'done' || $j->receipts ? number_format($j->receipts) : '—' }}</td>
                  <td class="small text-muted">{{ \Illuminate\Support\Carbon::parse($j->requested_at)->diffForHumans() }}@if($j->requested_by_name)<br><span style="font-size:.72rem;">by {{ $j->requested_by_name }}</span>@endif</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
          @if($activeJobs > 0)
            <div class="form-text mt-2"><i class="bx bx-time me-1"></i> This card refreshes on its own while a sync is in progress.</div>
            <script>setTimeout(function(){ location.reload(); }, 20000);</script>
          @endif
        @endif
      </div>
    </div>

    {{-- Endpoint + master switch --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bx bx-link me-1"></i> Where the office PC pushes to</h6>
        <div class="mb-2">
          <label class="form-label small fw-semibold mb-1">Sales sync endpoint</label>
          <input type="text" class="form-control font-monospace" value="{{ $endpoint }}" readonly style="font-size:.82rem;" onclick="this.select();">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold mb-1">Ledgers / parties endpoint</label>
          <input type="text" class="form-control font-monospace" value="{{ $partiesEndpoint }}" readonly style="font-size:.82rem;" onclick="this.select();">
        </div>
        <div class="d-flex flex-wrap gap-4 small">
          <div>
            <span class="text-muted">Master kill switch (TALLY_PUSH_ENABLED):</span>
            @if($killSwitchOn)<span class="badge bg-label-success">ON — endpoint live</span>@else<span class="badge bg-label-danger">OFF — endpoint answers 404 to everyone</span>@endif
          </div>
          <div>
            <span class="text-muted">Can view this data (TALLY_VIEWER_EMAILS):</span>
            @forelse($viewers as $v)<span class="badge bg-label-primary">{{ $v }}</span> @empty<span class="badge bg-label-secondary">nobody set</span>@endforelse
          </div>
        </div>
        @unless($killSwitchOn)
          <div class="alert alert-warning small mt-3 mb-0"><i class="bx bx-info-circle me-1"></i> The endpoint is currently <strong>off</strong>. It stays 404 to everyone until <code>TALLY_PUSH_ENABLED=true</code> is set in the server .env — generate the credentials and set the allowlist first, then flip it on.</div>
        @endunless
        @if($configFallback['in_use'])
          <div class="alert alert-info small mt-3 mb-0"><i class="bx bx-info-circle me-1"></i> No active credential in the table yet — the endpoint is falling back to the <code>.env</code> key. Generate a credential below to manage it from here instead.</div>
        @endif
      </div>
    </div>

    {{-- Credentials --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
      <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bx bx-id-card me-1"></i> Credentials & allowed IPs</h6>
        @if($credentials->isEmpty())
          <p class="text-muted small">No credentials yet. Generate one below — you'll get the key + secret once, to paste into the office PC.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead><tr class="small text-muted text-uppercase"><th>Label</th><th>API key</th><th>Allowed IPs</th><th>Uses</th><th>Last seen</th><th>State</th><th></th></tr></thead>
              <tbody>
              @foreach($credentials as $c)
                <tr>
                  <td class="fw-semibold">{{ $c->label }}@if($c->isRotating())<span class="badge bg-label-warning ms-1" title="A previous secret is still valid">rotating</span>@endif</td>
                  <td class="font-monospace small">{{ $c->maskedKey() }}</td>
                  <td class="small">@if($c->allowlist())@foreach($c->allowlist() as $ip)<span class="badge bg-label-dark">{{ $ip }}</span> @endforeach @else<span class="text-muted">— (config list)</span>@endif</td>
                  <td class="small">{{ number_format($c->use_count) }}</td>
                  <td class="small">{{ $c->last_used_at ? $c->last_used_at->diffForHumans() : '—' }}<br><span class="text-muted">{{ $c->last_used_ip }}</span></td>
                  <td>@if($c->is_active)<span class="badge bg-label-success">active</span>@else<span class="badge bg-label-secondary">disabled</span>@endif</td>
                  <td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mng{{ $c->id }}">Manage</button></td>
                </tr>
                <tr class="collapse" id="mng{{ $c->id }}">
                  <td colspan="7" class="bg-light rounded-3">
                    <div class="row g-3 p-2">
                      <div class="col-md-6">
                        <form method="POST" action="{{ route('user.tally-push.admin.connection.update', $c) }}">
                          @csrf @method('PUT')
                          <div class="mb-2"><label class="form-label small fw-semibold mb-1">Label</label><input name="label" class="form-control form-control-sm" value="{{ $c->label }}" required></div>
                          <div class="mb-2"><label class="form-label small fw-semibold mb-1">Allowed IPs <span class="text-muted">(comma-separated, IPv4/IPv6/CIDR)</span></label><input name="allowed_ips" class="form-control form-control-sm font-monospace" value="{{ $c->allowed_ips }}" placeholder="45.120.40.194, 112.196.44.155"></div>
                          <div class="mb-2"><label class="form-label small fw-semibold mb-1">Notes</label><input name="notes" class="form-control form-control-sm" value="{{ $c->notes }}"></div>
                          <div class="form-check mb-2"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act{{ $c->id }}" {{ $c->is_active ? 'checked' : '' }}><label class="form-check-label small" for="act{{ $c->id }}">Active (a disabled key is refused)</label></div>
                          <button class="btn btn-sm btn-primary">Save</button>
                        </form>
                      </div>
                      <div class="col-md-6">
                        <form method="POST" action="{{ route('user.tally-push.admin.connection.rotate', $c) }}" class="mb-3"
                              onsubmit="return confirm('Rotate the secret? The office PC will need the new one.');">
                          @csrf
                          <label class="form-label small fw-semibold mb-1">Rotate secret — keep the old one valid for</label>
                          <div class="input-group input-group-sm" style="max-width:220px;">
                            <select name="grace_days" class="form-select"><option value="0">0 days (immediate)</option><option value="7">7 days</option><option value="14" selected>14 days</option><option value="30">30 days</option></select>
                            <button class="btn btn-outline-warning" type="submit">Rotate</button>
                          </div>
                          <div class="form-text">You'll get a fresh secret to paste on the office PC.</div>
                        </form>
                        <form method="POST" action="{{ route('user.tally-push.admin.connection.destroy', $c) }}"
                              onsubmit="return confirm('Delete this credential? Any push still using it will be refused.');">
                          @csrf @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger"><i class="bx bx-trash me-1"></i> Delete credential</button>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        @endif

        {{-- Generate --}}
        <div class="border rounded-3 p-3 mt-2" style="background:#f8fafc;">
          <h6 class="fw-bold small mb-2"><i class="bx bx-plus-circle me-1"></i> Generate credentials</h6>
          <form method="POST" action="{{ route('user.tally-push.admin.connection.store') }}">
            @csrf
            <div class="row g-2 align-items-end">
              <div class="col-md-3"><label class="form-label small fw-semibold mb-1">Label</label><input name="label" class="form-control form-control-sm" value="Tally Office PC" required></div>
              <div class="col-md-6"><label class="form-label small fw-semibold mb-1">Allowed IPs</label><input name="allowed_ips" class="form-control form-control-sm font-monospace" value="45.120.40.194, 112.196.44.155" placeholder="comma-separated"></div>
              <div class="col-md-3"><button class="btn btn-sm btn-success w-100"><i class="bx bx-key me-1"></i> Generate key + secret</button></div>
            </div>
            <div class="form-text mt-2">The key + secret are shown once, right after. Paste them into the office PC's <code>C:\HRX-Automation\.env</code> along with the target URL above.</div>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
