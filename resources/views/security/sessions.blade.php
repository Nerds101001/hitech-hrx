@extends('layouts/layoutMaster')

@section('title', __('Active Sessions'))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')
<style>
  .sec-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1.1rem; }
  .sec-tabs a { font-size:.82rem; font-weight:600; color:#51665f; background:#fff;
    border:1px solid #e6ecec; border-radius:10px; padding:.5rem .9rem; text-decoration:none; }
  .sec-tabs a.active { background:#0d7377; border-color:#0d7377; color:#fff; }
  .sec-table { width:100%; border-collapse:separate; border-spacing:0; }
  .sec-table thead th { background:#f6f8f9; color:#6b7a7a; font-size:.68rem; font-weight:700; letter-spacing:.5px;
    text-transform:uppercase; white-space:nowrap; padding:.7rem .85rem; border-bottom:1px solid #e6ecec; }
  .sec-table tbody td { padding:.65rem .85rem; font-size:.83rem; vertical-align:middle; border-bottom:1px solid #f2f5f5; }
  .sec-name { font-weight:600; color:#22333b; line-height:1.2; }
  .sec-sub { font-size:.72rem; color:#94a3a3; }
  .sec-ip { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.76rem; color:#475569; }
  .cur { display:inline-block; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
    background:#e7f7ee; color:#15803d; border-radius:5px; padding:.12rem .4rem; margin-left:.35rem; }
</style>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h4 class="fw-bold mb-0">🔐 Login &amp; Security</h4>
    <span class="text-muted" style="font-size:.8rem">Everyone currently signed in on the web, and from where.</span>
  </div>

  <div class="sec-tabs">
    <a href="{{ route('security.index') }}">Login activity</a>
    <a href="{{ route('security.sessions') }}" class="active">Active sessions</a>
    <a href="{{ route('security.backups') }}">Backups</a>
    <a href="{{ route('auditLogs.index') }}">Audit log</a>
  </div>

  @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

  <div class="alert alert-light border" style="font-size:.8rem">
    Showing active <strong>web</strong> sessions ({{ $sessions->count() }}). Mobile-app logins use stateless tokens and
    aren’t listed here — revoke those from the person’s device record. “Revoke” signs that browser out immediately.
  </div>

  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden">
    <div class="table-responsive">
      <table class="sec-table">
        <thead>
          <tr><th>User</th><th>IP address</th><th>Device</th><th>Last active</th><th class="text-end">Action</th></tr>
        </thead>
        <tbody>
          @forelse($sessions as $s)
            <tr>
              <td>
                @if($s->user)
                  <div class="sec-name">{{ $s->user->first_name }} {{ $s->user->last_name }}@if($s->is_current)<span class="cur">This is you</span>@endif</div>
                  <div class="sec-sub">{{ $s->user->email }}</div>
                @else
                  <div class="sec-name text-muted">User #{{ $s->id }}</div>
                @endif
              </td>
              <td class="sec-ip">{{ $s->ip ?: '—' }}</td>
              <td><div class="sec-name" style="font-weight:600">{{ $s->device }}</div><div class="sec-sub" title="{{ $s->agent }}">{{ \Illuminate\Support\Str::limit($s->agent, 46) }}</div></td>
              <td class="sec-sub">{{ $s->last ? $s->last->diffForHumans() : '—' }}<br><small>{{ $s->last?->format('d M, H:i') }}</small></td>
              <td class="text-end">
                <form method="POST" action="{{ route('security.sessions.revoke', $s->id) }}" onsubmit="return confirm('Sign this device out now?');" class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger" style="font-size:.74rem">{{ $s->is_current ? 'Sign me out' : 'Revoke' }}</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No active web sessions.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
