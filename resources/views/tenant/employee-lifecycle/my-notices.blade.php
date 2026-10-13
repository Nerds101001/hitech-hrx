@extends('layouts/layoutMaster')

@section('title', __('My Warnings & Complaints'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('page-style')
<style>
  .mt-empty{background:#fff;border:1px solid #e6ecf1;border-radius:16px;padding:56px 20px;text-align:center}
  .mt-empty i{font-size:3rem;color:#0d9488;opacity:.5}
  .mt-empty h5{margin:14px 0 4px;font-weight:800;color:#0f172a}
  .mt-empty p{margin:0;color:#64748b;font-size:.86rem}
  .ln-team-group{margin-bottom:28px}
  .ln-team-group h6{font-weight:800;color:#0f172a;margin-bottom:10px}
</style>
@endsection

@php
  $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '—';

  $statusBadge = function ($status) {
    $s = strtolower((string) $status);
    $cls = match (true) {
      in_array($s, ['resolved', 'closed', 'dismissed']) => 'badge-hitech-success',
      in_array($s, ['active', 'open', 'investigating'])  => 'badge-hitech-danger',
      default                                            => 'badge-muted',
    };
    return '<span class="badge-hitech ' . $cls . '">' . e($s ? ucwords(str_replace('_', ' ', $s)) : '—') . '</span>';
  };

  $severityBadge = function ($severity) {
    $s = strtolower((string) $severity);
    $cls = match ($s) {
      'critical', 'high' => 'badge-hitech-danger',
      'medium'            => 'badge-brand-soft',
      default             => 'badge-muted',
    };
    return '<span class="badge-hitech ' . $cls . '">' . e($s ? ucfirst($s) : '—') . '</span>';
  };

  $ackBadge = fn ($at) => $at
    ? '<span class="badge-hitech badge-hitech-success">Acknowledged ' . e(\Carbon\Carbon::parse($at)->format('d M Y')) . '</span>'
    : '<span class="badge-hitech badge-muted">Pending</span>';

  $who = function ($u) {
    if (!$u) return '<span class="text-muted">—</span>';
    return '<div class="fw-medium">' . e(trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')))
         . '</div><small class="text-muted">' . e($u->code ?? '') . '</small>';
  };

  $total = $myWarnings->count() + $myComplaints->count();
  $hasTeam = $teamWarnings->isNotEmpty() || $teamComplaints->isNotEmpty();
@endphp

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">My Warnings & Complaints</h3>
      <p class="text-muted mb-0 small">Records issued to you by HR/Admin — visible only to you{{ $hasTeam ? ', plus a read-only view of your team' : '' }}.</p>
    </div>
    <span class="badge {{ $total ? 'bg-label-warning' : 'bg-label-success' }} fs-6 px-3 py-2">
      {{ $total ? $total . ' on record' : 'Nothing on record' }}
    </span>
  </div>

  <div class="px-4">

    @if($total || $hasTeam)
      <ul class="nav nav-pills hitech-tabs-sm mb-4" id="noticeTabs">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#noticeWarnings" type="button">
            <i class="bx bx-error"></i> Warnings <span class="badge badge-brand-soft badge-hitech ms-1">{{ $myWarnings->count() }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#noticeComplaints" type="button">
            <i class="bx bx-message-square-dots"></i> Complaints <span class="badge badge-brand-soft badge-hitech ms-1">{{ $myComplaints->count() }}</span>
          </button>
        </li>
        @if($hasTeam)
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#noticeTeam" type="button">
              <i class="bx bx-group"></i> My Team <span class="badge badge-brand-soft badge-hitech ms-1">{{ $teamWarnings->count() + $teamComplaints->count() }}</span>
            </button>
          </li>
        @endif
      </ul>

    <div class="tab-content p-0 border-0">

      {{-- WARNINGS --}}
      <div class="tab-pane fade show active" id="noticeWarnings">
        @if($myWarnings->isNotEmpty())
          <div class="hitech-card">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Type</th><th>Severity</th><th>Date</th><th>Reason</th><th>Status</th><th>Acknowledgement</th><th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($myWarnings as $w)
                  <tr>
                    <td>
                      <div class="fw-medium text-capitalize">{{ str_replace('_', ' ', $w->warning_type ?? '—') }}</div>
                      @if($w->attachment_path)
                        <a href="{{ route('employee-lifecycle.warnings.attachment', $w->id) }}" target="_blank" class="small text-decoration-none d-block">
                          <i class="bx bx-paperclip"></i> View attachment
                        </a>
                      @endif
                    </td>
                    <td>{!! $severityBadge($w->severity ?? null) !!}</td>
                    <td class="text-nowrap">{{ $fmt($w->warning_date ?? $w->created_at) }}</td>
                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($w->description ?: '—', 70) }}</small></td>
                    <td>{!! $statusBadge($w->status ?? null) !!}</td>
                    <td>{!! $ackBadge($w->acknowledged_at) !!}</td>
                    <td class="text-end">
                      @unless($w->acknowledged_at)
                        <form method="POST" action="{{ route('employee-lifecycle.warnings.acknowledge', $w->id) }}">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-hitech">Acknowledge</button>
                        </form>
                      @endunless
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="mt-empty py-4">
            <i class="bx bx-check-circle" style="font-size:2rem"></i>
            <p class="mb-0">No warnings on record.</p>
          </div>
        @endif
      </div>

      {{-- COMPLAINTS --}}
      <div class="tab-pane fade" id="noticeComplaints">
        @if($myComplaints->isNotEmpty())
          <div class="hitech-card">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Type</th><th>Severity</th><th>Date</th><th>Details</th><th>Status</th><th>Acknowledgement</th><th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                @foreach($myComplaints as $c)
                  <tr>
                    <td>
                      <div class="fw-medium text-capitalize">{{ str_replace('_', ' ', $c->complaint_type ?? '—') }}</div>
                      @if($c->attachment_path)
                        <a href="{{ route('employee-lifecycle.complaints.attachment', $c->id) }}" target="_blank" class="small text-decoration-none d-block">
                          <i class="bx bx-paperclip"></i> View attachment
                        </a>
                      @endif
                    </td>
                    <td>{!! $severityBadge($c->severity ?? null) !!}</td>
                    <td class="text-nowrap">{{ $fmt($c->complaint_date ?? $c->created_at) }}</td>
                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($c->description ?: '—', 70) }}</small></td>
                    <td>{!! $statusBadge($c->status ?? null) !!}</td>
                    <td>{!! $ackBadge($c->acknowledged_at) !!}</td>
                    <td class="text-end">
                      @unless($c->acknowledged_at)
                        <form method="POST" action="{{ route('employee-lifecycle.complaints.acknowledge', $c->id) }}">
                          @csrf
                          <button type="submit" class="btn btn-sm btn-hitech">Acknowledge</button>
                        </form>
                      @endunless
                    </td>
                  </tr>
                @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @else
          <div class="mt-empty py-4">
            <i class="bx bx-check-circle" style="font-size:2rem"></i>
            <p class="mb-0">No complaints on record.</p>
          </div>
        @endif
      </div>

      {{-- MY TEAM (read-only, managers only) --}}
      @if($hasTeam)
        <div class="tab-pane fade" id="noticeTeam">
          @if($teamWarnings->isNotEmpty())
            <div class="ln-team-group">
              <h6><i class="bx bx-error me-1"></i>Team Warnings</h6>
              <div class="hitech-card">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Employee</th><th>Type</th><th>Severity</th><th>Date</th><th>Status</th><th class="text-end">Acknowledgement</th>
                      </tr>
                    </thead>
                    <tbody>
                    @foreach($teamWarnings as $w)
                      <tr>
                        <td>{!! $who($w->user) !!}</td>
                        <td class="text-capitalize">{{ str_replace('_', ' ', $w->warning_type ?? '—') }}</td>
                        <td>{!! $severityBadge($w->severity ?? null) !!}</td>
                        <td class="text-nowrap">{{ $fmt($w->warning_date ?? $w->created_at) }}</td>
                        <td>{!! $statusBadge($w->status ?? null) !!}</td>
                        <td class="text-end">{!! $ackBadge($w->acknowledged_at) !!}</td>
                      </tr>
                    @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          @endif

          @if($teamComplaints->isNotEmpty())
            <div class="ln-team-group">
              <h6><i class="bx bx-message-square-dots me-1"></i>Team Complaints</h6>
              <div class="hitech-card">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Employee</th><th>Type</th><th>Severity</th><th>Date</th><th>Status</th><th class="text-end">Acknowledgement</th>
                      </tr>
                    </thead>
                    <tbody>
                    @foreach($teamComplaints as $c)
                      <tr>
                        <td>{!! $who($c->respondent) !!}</td>
                        <td class="text-capitalize">{{ str_replace('_', ' ', $c->complaint_type ?? '—') }}</td>
                        <td>{!! $severityBadge($c->severity ?? null) !!}</td>
                        <td class="text-nowrap">{{ $fmt($c->complaint_date ?? $c->created_at) }}</td>
                        <td>{!! $statusBadge($c->status ?? null) !!}</td>
                        <td class="text-end">{!! $ackBadge($c->acknowledged_at) !!}</td>
                      </tr>
                    @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          @endif
        </div>
      @endif

    </div>
    @else
      <div class="mt-empty">
        <i class="bx bx-check-circle"></i>
        <h5>Nothing on record</h5>
        <p>Any warning or complaint HR/Admin records about you will appear here.</p>
      </div>
    @endif

  </div>
</div>
@endsection
