@extends('layouts/layoutMaster')

@section('title', __('Audit Logs'))

@section('vendor-style')
  @vite(['resources/assets/vendor/scss/pages/hitech-portal.scss'])
@endsection

@section('content')
<style>
  .al-stat {
    background:#fff; border:1px solid #eef2f2; border-radius:14px;
    padding:.9rem 1.15rem; min-width:150px; flex:1 1 0;
    box-shadow:0 1px 3px rgba(0,0,0,.04);
  }
  .al-stat .lbl { font-size:.66rem; letter-spacing:.6px; text-transform:uppercase; color:#8a9a9a; font-weight:700; }
  .al-stat .val { font-size:1.45rem; font-weight:800; color:#0f766e; line-height:1.25; }

  .al-filters { background:#fff; border:1px solid #eef2f2; border-radius:14px; padding:1rem 1.15rem; }
  .al-filters .form-control, .al-filters .form-select { border-radius:9px; font-size:.85rem; height:38px; }

  .al-table { width:100%; border-collapse:separate; border-spacing:0; }
  .al-table thead th {
    background:#f6f8f9; color:#6b7a7a; font-size:.68rem; font-weight:700;
    letter-spacing:.5px; text-transform:uppercase; white-space:nowrap;
    padding:.7rem .85rem; border-bottom:1px solid #e6ecec;
  }
  .al-table tbody td { padding:.7rem .85rem; font-size:.83rem; vertical-align:middle; border-bottom:1px solid #f2f5f5; }
  .al-table tbody tr:hover { background:#fafbfb; }

  .al-avatar {
    width:34px; height:34px; border-radius:50%; object-fit:cover; flex-shrink:0;
    background:#e6f3f2; color:#0f766e; font-weight:700; font-size:.75rem;
    display:inline-flex; align-items:center; justify-content:center;
  }
  .al-name { font-weight:600; color:#22333b; line-height:1.25; }
  .al-mail { font-size:.72rem; color:#94a3a3; line-height:1.25; }

  .al-ev { display:inline-block; min-width:74px; text-align:center; font-size:.68rem;
           font-weight:700; letter-spacing:.3px; text-transform:uppercase;
           padding:.28rem .5rem; border-radius:6px; }
  .al-ev.created { background:#e7f7ee; color:#15803d; }
  .al-ev.updated { background:#e8f1fd; color:#1d4ed8; }
  .al-ev.deleted { background:#fdecea; color:#b02a37; }
  .al-ev.blocked { background:#fff4e5; color:#b45309; }
  .al-ev.restored{ background:#f3e8ff; color:#6d28d9; }

  .al-chip { display:inline-block; background:#f1f5f5; color:#4b5a5a; border-radius:6px;
             padding:.16rem .45rem; font-size:.68rem; font-weight:600; margin:1px 2px 1px 0; }
  .al-record { color:#22333b; font-weight:600; }
  .al-ip { font-family:ui-monospace,Menlo,Consolas,monospace; font-size:.75rem; color:#64748b; }
  .al-when { white-space:nowrap; }
  .al-when small { color:#94a3a3; }

  .al-view { width:32px; height:32px; border-radius:8px; border:1px solid #e2e8e8;
             display:inline-flex; align-items:center; justify-content:center;
             color:#0f766e; background:#fff; transition:all .15s; }
  .al-view:hover { background:#0d7377; border-color:#0d7377; color:#fff; }

  /* These repeat per log row -- the 70 sub-12px elements flagged here. */
  @media (max-width: 480px) {
    .al-stat .lbl, .al-table thead th, .al-mail, .al-ev, .al-chip {
      font-size: .75rem;
    }
  }
</style>

<div class="container-fluid py-4">

  <div class="mb-3">
    <h4 class="fw-bold mb-0" style="color:#0f766e;">Audit Logs</h4>
    <div class="text-muted" style="font-size:.85rem;">Every change made in the system — who, what, when and from where.</div>
  </div>

  {{-- Summary --}}
  <div class="d-flex gap-3 flex-wrap mb-3">
    <div class="al-stat"><div class="lbl">Entries</div><div class="val">{{ number_format($stats['total']) }}</div></div>
    <div class="al-stat"><div class="lbl">Today</div><div class="val">{{ number_format($stats['today']) }}</div></div>
    <div class="al-stat"><div class="lbl">People</div><div class="val">{{ number_format($stats['people']) }}</div></div>
    <div class="al-stat"><div class="lbl">Showing</div><div class="val">{{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }}</div></div>
  </div>

  {{-- Filters --}}
  <form method="GET" class="al-filters mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-lg-3 col-md-6">
        <label class="form-label small fw-semibold mb-1">Search person</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, email or employee code…">
      </div>
      <div class="col-lg-2 col-md-6">
        <label class="form-label small fw-semibold mb-1">Department</label>
        <select name="dept" class="form-select">
          <option value="">All departments</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}" {{ (string)request('dept') === (string)$d->id ? 'selected' : '' }}>{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 col-md-6">
        <label class="form-label small fw-semibold mb-1">What changed</label>
        <select name="model" class="form-select">
          <option value="">All records</option>
          @foreach($models as $class => $label)
            <option value="{{ $class }}" {{ request('model') === $class ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-2 col-md-6">
        <label class="form-label small fw-semibold mb-1">Change type</label>
        <select name="event" class="form-select">
          <option value="">All types</option>
          @foreach($events as $e)
            <option value="{{ $e }}" {{ request('event') === $e ? 'selected' : '' }}>{{ ucfirst($e) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-3 col-md-6">
        <label class="form-label small fw-semibold mb-1">IP address</label>
        <input type="text" name="ip" value="{{ request('ip') }}" class="form-control" placeholder="e.g. 103.100.">
      </div>

      <div class="col-lg-2 col-md-4">
        <label class="form-label small fw-semibold mb-1">From</label>
        <input type="date" name="from" value="{{ request('from') }}" class="form-control">
      </div>
      <div class="col-lg-2 col-md-4">
        <label class="form-label small fw-semibold mb-1">To</label>
        <input type="date" name="to" value="{{ request('to') }}" class="form-control">
      </div>
      <div class="col-lg-2 col-md-4">
        <label class="form-label small fw-semibold mb-1">Rows</label>
        <select name="per" class="form-select">
          @foreach([25,50,100,200] as $p)
            <option value="{{ $p }}" {{ $per === $p ? 'selected' : '' }}>{{ $p }} per page</option>
          @endforeach
        </select>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="form-check mt-2">
          <input class="form-check-input" type="checkbox" name="system" value="1" id="sysChk" {{ request()->boolean('system') ? 'checked' : '' }}>
          <label class="form-check-label small" for="sysChk">
            Include automated entries
            <i class="bx bx-info-circle text-muted" title="OTP and token writes made without a signed-in user"></i>
          </label>
        </div>
      </div>
      <div class="col-lg-3 col-md-6 text-end">
        <button class="btn btn-primary btn-sm px-3" type="submit"><i class="bx bx-search"></i> Apply</button>
        <a href="{{ url()->current() }}" class="btn btn-outline-secondary btn-sm px-3"><i class="bx bx-x"></i> Clear</a>
      </div>
    </div>
  </form>

  {{-- Table --}}
  <div class="card border-0 shadow-sm" style="border-radius:14px; overflow:hidden;">
    <div class="table-responsive">
      <table class="al-table">
        <thead>
          <tr>
            <th style="width:60px;">ID</th>
            <th style="width:240px;">Name</th>
            <th style="width:150px;">Department</th>
            <th style="width:110px;">Change type</th>
            <th>What changed</th>
            <th style="width:130px;">IP address</th>
            <th style="width:150px;">Created at</th>
            <th style="width:70px;" class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $log)
            <tr>
              <td class="text-muted">{{ $log->id }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  @if($log->user && $log->user->getProfilePicture())
                    <img src="{{ $log->user->getProfilePicture() }}" class="al-avatar" alt="">
                  @else
                    <span class="al-avatar">{{ $log->user ? $log->user->getInitials() : 'SY' }}</span>
                  @endif
                  <div style="min-width:0;">
                    <div class="al-name text-truncate">{{ $log->user ? $log->user->name : 'System' }}</div>
                    <div class="al-mail text-truncate">{{ $log->user->email ?? 'automated — no signed-in user' }}</div>
                  </div>
                </div>
              </td>
              <td class="text-truncate">{{ optional(optional($log->user)->department)->name ?? '—' }}</td>
              <td><span class="al-ev {{ $log->event }}">{{ $log->event }}</span></td>
              <td>
                <div class="al-record text-truncate" style="max-width:340px;">
                  {{ $log->model_label }}: {{ $log->record_label }}
                </div>
                @if(count($log->changed_fields))
                  <div style="max-width:340px;" class="text-truncate">
                    @foreach(array_slice($log->changed_fields, 0, 3) as $f)
                      <span class="al-chip">{{ $f }}</span>
                    @endforeach
                    @if(count($log->changed_fields) > 3)
                      <span class="al-chip">+{{ count($log->changed_fields) - 3 }} more</span>
                    @endif
                  </div>
                @endif
              </td>
              <td class="al-ip">{{ $log->ip_address ?: '—' }}</td>
              <td class="al-when">
                {{ $log->created_at->format('d M Y') }}<br>
                <small>{{ $log->created_at->format('h:i A') }}</small>
              </td>
              <td class="text-center">
                <a href="{{ route('auditLogs.show', $log->id) }}" class="al-view" title="View details">
                  <i class="bx bx-show"></i>
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-5">No audit entries match these filters.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($logs->hasPages())
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 px-3 py-3 border-top">
        <div class="text-muted" style="font-size:.82rem;">
          Showing {{ $logs->firstItem() }}–{{ $logs->lastItem() }} of {{ number_format($logs->total()) }}
        </div>
        <div>{{ $logs->links() }}</div>
      </div>
    @endif
  </div>
</div>
@endsection
