@extends('layouts/layoutMaster')

@section('title', __('Login & Security'))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')
<style>
  .sec-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1.1rem; }
  .sec-tabs a { font-size:.82rem; font-weight:600; color:#51665f; background:#fff;
    border:1px solid #e6ecec; border-radius:10px; padding:.5rem .9rem; text-decoration:none; }
  .sec-tabs a.active { background:#0d7377; border-color:#0d7377; color:#fff; }
  .sec-stat { background:#fff; border:1px solid #eef2f2; border-radius:14px; padding:.9rem 1.15rem;
    min-width:150px; flex:1 1 0; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .sec-stat .lbl { font-size:.66rem; letter-spacing:.6px; text-transform:uppercase; color:#8a9a9a; font-weight:700; }
  .sec-stat .val { font-size:1.45rem; font-weight:800; line-height:1.25; color:#0f766e; }
  .sec-stat.warn .val { color:#b45309; } .sec-stat.danger .val { color:#b02a37; }
  .sec-filters { background:#fff; border:1px solid #eef2f2; border-radius:14px; padding:1rem 1.15rem; }
  .sec-filters .form-control, .sec-filters .form-select { border-radius:9px; font-size:.85rem; height:38px; }
  .sec-table { width:100%; border-collapse:separate; border-spacing:0; }
  .sec-table thead th { background:#f6f8f9; color:#6b7a7a; font-size:.68rem; font-weight:700; letter-spacing:.5px;
    text-transform:uppercase; white-space:nowrap; padding:.7rem .85rem; border-bottom:1px solid #e6ecec; }
  .sec-table tbody td { padding:.65rem .85rem; font-size:.83rem; vertical-align:middle; border-bottom:1px solid #f2f5f5; }
  .sec-table tbody tr:hover { background:#fafbfb; }
  .sec-name { font-weight:600; color:#22333b; line-height:1.2; }
  .sec-sub { font-size:.72rem; color:#94a3a3; }
  .sec-ip { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.76rem; color:#475569; }
  .sec-when { white-space:nowrap; } .sec-when small { color:#94a3a3; }
  .ev { display:inline-block; font-size:.67rem; font-weight:700; letter-spacing:.3px; text-transform:uppercase;
    padding:.26rem .5rem; border-radius:6px; white-space:nowrap; }
  .ev.ok { background:#e7f7ee; color:#15803d; } .ev.bad { background:#fdecea; color:#b02a37; }
  .ev.warn { background:#fff4e5; color:#b45309; }
  .res-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:.35rem; }
  .res-dot.ok { background:#16a34a; } .res-dot.bad { background:#dc2626; }
</style>

<div class="container-fluid py-4">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h4 class="fw-bold mb-0">🔐 Login &amp; Security</h4>
    <span class="text-muted" style="font-size:.8rem">Every sign-in attempt is recorded with its IP and device.</span>
  </div>

  <div class="sec-tabs">
    <a href="{{ route('security.index') }}" class="active">Login activity</a>
    <a href="{{ route('security.sessions') }}">Active sessions</a>
    <a href="{{ route('auditLogs.index') }}">Audit log</a>
  </div>

  <div class="d-flex gap-2 flex-wrap mb-3">
    <div class="sec-stat"><div class="lbl">Attempts today</div><div class="val">{{ number_format($stats['attempts_today']) }}</div></div>
    <div class="sec-stat warn"><div class="lbl">Failed today</div><div class="val">{{ number_format($stats['failures_today']) }}</div></div>
    <div class="sec-stat"><div class="lbl">Distinct IPs today</div><div class="val">{{ number_format($stats['ips_today']) }}</div></div>
  </div>

  <form method="GET" class="sec-filters mb-3">
    <div class="row g-2">
      <div class="col-md-3"><input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="User name / email typed"></div>
      <div class="col-md-2">
        <select name="event" class="form-select">
          <option value="">All events</option>
          @foreach($eventLabels as $code => $label)
            <option value="{{ $code }}" @selected(request('event')===$code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <select name="result" class="form-select">
          <option value="">Any result</option>
          <option value="success" @selected(request('result')==='success')>Success</option>
          <option value="fail" @selected(request('result')==='fail')>Failure</option>
        </select>
      </div>
      <div class="col-md-2"><input type="text" name="ip" value="{{ request('ip') }}" class="form-control" placeholder="IP address"></div>
      <div class="col-md-3 d-flex gap-2">
        <input type="date" name="from" value="{{ request('from') }}" class="form-control" title="From">
        <input type="date" name="to" value="{{ request('to') }}" class="form-control" title="To">
      </div>
    </div>
    <div class="d-flex gap-2 mt-2">
      <button class="btn btn-sm btn-primary" style="background:#0d7377;border-color:#0d7377">Filter</button>
      <a href="{{ route('security.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
    </div>
  </form>

  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden">
    <div class="table-responsive">
      <table class="sec-table">
        <thead>
          <tr>
            <th>When</th><th>Account / typed</th><th>Event</th><th>Result</th><th>IP address</th><th>Device</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
            @php
              $ok = $log->successful;
              $cls = in_array($log->event, ['password_failed','unknown_user','brute_force_lock','otp_locked']) ? 'bad'
                   : (in_array($log->event, ['otp_failed','account_locked','account_inactive']) ? 'warn' : 'ok');
            @endphp
            <tr>
              <td class="sec-when">{{ $log->created_at?->format('d M, H:i') }}<br><small>{{ $log->created_at?->diffForHumans() }}</small></td>
              <td>
                @if($log->user)
                  <div class="sec-name">{{ $log->user->first_name }} {{ $log->user->last_name }}</div>
                  <div class="sec-sub">{{ $log->user->email }}</div>
                @else
                  <div class="sec-name text-muted">— no match —</div>
                @endif
                @if($log->email_input && (!$log->user || $log->email_input !== $log->user->email))
                  <div class="sec-sub">typed: {{ $log->email_input }}</div>
                @endif
              </td>
              <td><span class="ev {{ $cls }}">{{ $eventLabels[$log->event] ?? $log->event }}</span></td>
              <td><span class="res-dot {{ $ok ? 'ok' : 'bad' }}"></span>{{ $ok ? 'OK' : 'Failed' }}</td>
              <td class="sec-ip">{{ $log->ip_address ?: '—' }}</td>
              <td class="sec-sub" title="{{ $log->user_agent }}">{{ \Illuminate\Support\Str::limit($log->user_agent, 48) ?: '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No login activity recorded yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
