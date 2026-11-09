@extends('layouts/layoutMaster')

@section('title', __('Backups'))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')
@php
  $fmt = function ($bytes) {
    $b = (int) $bytes;
    if ($b >= 1073741824) return number_format($b / 1073741824, 2) . ' GB';
    if ($b >= 1048576)   return number_format($b / 1048576, 1) . ' MB';
    if ($b >= 1024)      return number_format($b / 1024, 0) . ' KB';
    return $b . ' B';
  };
@endphp
<style>
  .sec-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-bottom:1.1rem; }
  .sec-tabs a { font-size:.82rem; font-weight:600; color:#51665f; background:#fff;
    border:1px solid #e6ecec; border-radius:10px; padding:.5rem .9rem; text-decoration:none; }
  .sec-tabs a.active { background:#0d7377; border-color:#0d7377; color:#fff; }
  .sec-stat { background:#fff; border:1px solid #eef2f2; border-radius:14px; padding:.9rem 1.15rem;
    min-width:150px; flex:1 1 0; box-shadow:0 1px 3px rgba(0,0,0,.04); }
  .sec-stat .lbl { font-size:.66rem; letter-spacing:.6px; text-transform:uppercase; color:#8a9a9a; font-weight:700; }
  .sec-stat .val { font-size:1.35rem; font-weight:800; line-height:1.25; color:#0f766e; }
  .sec-table { width:100%; border-collapse:separate; border-spacing:0; }
  .sec-table thead th { background:#f6f8f9; color:#6b7a7a; font-size:.68rem; font-weight:700; letter-spacing:.5px;
    text-transform:uppercase; white-space:nowrap; padding:.7rem .85rem; border-bottom:1px solid #e6ecec; }
  .sec-table tbody td { padding:.7rem .85rem; font-size:.85rem; vertical-align:middle; border-bottom:1px solid #f2f5f5; }
  .sec-table tbody tr:hover { background:#fafbfb; }
  .sec-file { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.82rem; color:#22333b; font-weight:600; }
  .sec-sub { font-size:.74rem; color:#94a3a3; }
  .dl-btn { display:inline-flex; align-items:center; gap:6px; font-size:.76rem; font-weight:600;
    border:1px solid #0d7377; color:#0d7377; background:#fff; border-radius:9px; padding:.35rem .8rem; text-decoration:none; }
  .dl-btn:hover { background:#0d7377; color:#fff; }
  .latest { display:inline-block; font-size:.62rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px;
    background:#e7f7ee; color:#15803d; border-radius:5px; padding:.12rem .4rem; margin-left:.4rem; }
</style>

<div class="container-fluid py-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <h4 class="fw-bold mb-0">🔐 Login &amp; Security</h4>
    <form method="POST" action="{{ route('security.backups.run') }}" onsubmit="return confirm('Take a fresh database backup now?');">
      @csrf
      <button class="btn btn-sm" style="background:#0d7377;border-color:#0d7377;color:#fff;font-weight:600">
        <i class="bx bx-cloud-download me-1"></i> Back up now
      </button>
    </form>
  </div>

  <div class="sec-tabs">
    <a href="{{ route('security.index') }}">Login activity</a>
    <a href="{{ route('security.sessions') }}">Active sessions</a>
    <a href="{{ route('security.backups') }}" class="active">Backups</a>
    <a href="{{ route('auditLogs.index') }}">Audit log</a>
  </div>

  @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
  @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif

  <div class="d-flex gap-2 flex-wrap mb-3">
    <div class="sec-stat"><div class="lbl">Last backup</div><div class="val">{{ $lastAt ? $lastAt->format('d M Y, H:i') : '—' }}</div></div>
    <div class="sec-stat"><div class="lbl">Backups kept</div><div class="val">{{ $count }}</div></div>
    <div class="sec-stat"><div class="lbl">Total size</div><div class="val">{{ $fmt($totalSize) }}</div></div>
    <div class="sec-stat"><div class="lbl">Schedule</div><div class="val" style="font-size:1rem">Daily · 01:30</div></div>
  </div>

  <div class="alert alert-light border" style="font-size:.8rem">
    Database backups (spatie/laravel-backup), stored on the server and listed newest-first. Restores are intentionally
    not available here — restoring overwrites the live database, so it is done deliberately. Download an archive to keep
    an off-server copy.
  </div>

  <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden">
    <div class="table-responsive">
      <table class="sec-table">
        <thead>
          <tr><th>Archive</th><th>Taken</th><th>Size</th><th class="text-end">Download</th></tr>
        </thead>
        <tbody>
          @forelse($items as $i => $b)
            <tr>
              <td><span class="sec-file">{{ $b->name }}</span>@if($i === 0)<span class="latest">Latest</span>@endif</td>
              <td>{{ \Carbon\Carbon::createFromTimestamp($b->modified)->format('d M Y, H:i') }}<br><span class="sec-sub">{{ \Carbon\Carbon::createFromTimestamp($b->modified)->diffForHumans() }}</span></td>
              <td>{{ $fmt($b->size) }}</td>
              <td class="text-end"><a class="dl-btn" href="{{ route('security.backups.download', $b->name) }}"><i class="bx bx-download"></i> Download</a></td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No backups yet. Use “Back up now”, or the nightly 1:30 job will create one.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
