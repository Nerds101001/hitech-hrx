@extends('layouts/layoutMaster')

@section('title', __('Employee Lifecycle'))

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.full.min.js',
    'resources/assets/vendor/js/bootstrap.js',
  ])
@endsection

@php
  /*
   * One page for the whole lifecycle. It used to be eight sidebar entries and
   * eight page loads (promotions, transfers, resignations, terminations,
   * complaints, trips, warnings, overview), each one a tall hero banner above
   * a single table. The tabs below carry all of it in one place.
   *
   * The old page also used `ti ti-*` (Tabler) icons in twelve places, but that
   * icon font is never loaded in this app — which is why those tiles rendered
   * as blank coloured squares. Everything here uses Boxicons (`bx`).
   */
  $tabs = [
    ['key' => 'promotions',   'label' => 'Promotions',   'icon' => 'bx-trending-up',         'rows' => $tabPromotions],
    ['key' => 'transfers',    'label' => 'Transfers',    'icon' => 'bx-transfer',            'rows' => $tabTransfers],
    ['key' => 'resignations', 'label' => 'Resignations', 'icon' => 'bx-log-out',             'rows' => $tabResignations],
    ['key' => 'terminations', 'label' => 'Terminations', 'icon' => 'bx-user-x',              'rows' => $tabTerminations],
    ['key' => 'warnings',     'label' => 'Warnings',     'icon' => 'bx-error',               'rows' => $tabWarnings],
    ['key' => 'complaints',   'label' => 'Complaints',   'icon' => 'bx-message-square-dots', 'rows' => $tabComplaints],
    ['key' => 'trips',        'label' => 'Trips',        'icon' => 'bx-briefcase-alt',       'rows' => $tabTrips],
  ];

  $active = request()->get('tab', 'promotions');
  if (!collect($tabs)->pluck('key')->contains($active)) { $active = 'promotions'; }

  $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d M Y') : '—';

  $who = function ($r) {
    $u = $r->user ?? null;
    if (!$u) return '<span class="text-muted">—</span>';
    return '<div class="fw-bold text-dark">' . e(trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')))
         . '</div><small class="text-muted">' . e($u->code ?? '') . '</small>';
  };

  $pill = function ($status) {
    $s = strtolower((string) $status);
    $cls = match (true) {
      in_array($s, ['approved', 'completed', 'accepted', 'resolved', 'closed']) => 'bg-label-success',
      in_array($s, ['pending', 'in_progress', 'open'])                          => 'bg-label-warning',
      in_array($s, ['rejected', 'cancelled'])                                   => 'badge-muted',
      default                                                                   => 'badge-brand-soft',
    };
    return '<span class="badge ' . $cls . ' rounded-pill">'
         . e($s ? ucwords(str_replace('_', ' ', $s)) : '—') . '</span>';
  };
@endphp

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="px-4">

    <x-hero-banner
      title="Employee Lifecycle"
      subtitle="Promotions, transfers, exits, warnings, complaints and trips — all in one place"
      icon="bx-git-branch"
      breadcrumb="Employee Lifecycle"
    />

    {{-- Stat strip: each card jumps to its tab --}}
    <div class="row g-6 mb-6">
      @php
        $cards = [
          ['bx-trending-up',          'icon-teal',  $stats['pending_promotions'],   'Pending Promotions', 'promotions'],
          ['bx-transfer',             'icon-blue',  $stats['pending_transfers'],    'Pending Transfers',  'transfers'],
          ['bx-log-out',              'icon-amber', $stats['pending_resignations'], 'Pending Exits',      'resignations'],
          ['bx-error',                'icon-red',   $stats['active_warnings'],      'Active Warnings',    'warnings'],
          ['bx-message-square-dots',  'icon-amber', $stats['open_complaints'],      'Open Complaints',    'complaints'],
          ['bx-briefcase-alt',        'icon-blue',  $stats['pending_trips'],        'Pending Trips',      'trips'],
        ];
      @endphp
      @foreach($cards as [$icon, $iconCls, $value, $label, $tabKey])
        <div class="col-xl-2 col-md-4 col-6">
          <div class="hitech-stat-card dashboard-variant uniform-card clickable-stat"
               onclick="showLifecycleTab('{{ $tabKey }}')">
            <div class="stat-card-header">
              <div class="stat-icon-wrap {{ $iconCls }}"><i class="bx {{ $icon }}"></i></div>
            </div>
            <div>
              <h3 class="stat-value mb-0">{{ $value }}</h3>
              <span class="stat-label">{{ $label }}</span>
            </div>
          </div>
        </div>
      @endforeach
    </div>

    {{-- Tabs --}}
    <div class="hitech-card-white overflow-hidden">
      <div class="card-body p-0">

        <ul class="nav nav-tabs lifecycle-tabs px-3 pt-2" role="tablist">
          @foreach($tabs as $t)
            <li class="nav-item" role="presentation">
              <button class="nav-link {{ $active === $t['key'] ? 'active' : '' }}"
                      data-bs-toggle="tab" data-bs-target="#tab-{{ $t['key'] }}"
                      data-tabkey="{{ $t['key'] }}" type="button" role="tab">
                <i class="bx {{ $t['icon'] }} me-1"></i>{{ $t['label'] }}
                <span class="badge badge-brand-soft rounded-pill ms-1">{{ $t['rows']->count() }}</span>
              </button>
            </li>
          @endforeach
        </ul>

        <div class="tab-content">

          {{-- PROMOTIONS --}}
          <div class="tab-pane fade {{ $active === 'promotions' ? 'show active' : '' }}" id="tab-promotions" role="tabpanel">
            <div class="d-flex justify-content-end px-3 pt-3">
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#promotionModal">
                <i class="bx bx-plus me-1"></i>New Promotion
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>From</th><th>To</th><th>Type</th><th>Date</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabPromotions as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td>{{ $r->previousDesignation->name ?? '—' }}</td>
                    <td>{{ $r->newDesignation->name ?? '—' }}</td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $r->promotion_type ?? '—') }}</td>
                    <td>{{ $fmt($r->promotion_date ?? null) }}</td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-5">
                    <i class="bx bx-trending-up d-block mb-2" style="font-size:2rem;"></i>No promotions recorded yet
                  </td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- TRANSFERS --}}
          <div class="tab-pane fade {{ $active === 'transfers' ? 'show active' : '' }}" id="tab-transfers" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>From</th><th>To</th><th>Effective</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabTransfers as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td>{{ $r->fromDepartment->name ?? '—' }}</td>
                    <td>{{ $r->toDepartment->name ?? '—' }}</td>
                    <td>{{ $fmt($r->transfer_date ?? $r->effective_date ?? null) }}</td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-5">
                    <i class="bx bx-transfer d-block mb-2" style="font-size:2rem;"></i>No transfers recorded yet
                  </td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- RESIGNATIONS --}}
          <div class="tab-pane fade {{ $active === 'resignations' ? 'show active' : '' }}" id="tab-resignations" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Resigned</th><th>Last Working Day</th><th>Reason</th><th>Type</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabResignations as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td>{{ $fmt($r->resignation_date ?? null) }}</td>
                    <td>{{ $fmt($r->relieving_date ?? null) }}</td>
                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?: '—', 40) }}</small></td>
                    <td class="text-capitalize"><small>{{ str_replace('_', ' ', $r->reason_type ?? '—') }}</small></td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-5">No resignations recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- TERMINATIONS --}}
          <div class="tab-pane fade {{ $active === 'terminations' ? 'show active' : '' }}" id="tab-terminations" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Type</th><th>Effective</th><th>Reason</th><th>Rehireable</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabTerminations as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $r->termination_type ?? $r->type ?? '—') }}</td>
                    <td>{{ $fmt($r->termination_date ?? null) }}</td>
                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?: '—', 40) }}</small></td>
                    <td>
                      @if(!is_null($r->is_rehireable ?? null))
                        <span class="badge {{ $r->is_rehireable ? 'bg-label-success' : 'badge-muted' }} rounded-pill">
                          {{ $r->is_rehireable ? 'Yes' : 'No' }}
                        </span>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-5">No terminations recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- WARNINGS --}}
          <div class="tab-pane fade {{ $active === 'warnings' ? 'show active' : '' }}" id="tab-warnings" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Type</th><th>Issued</th><th>Reason</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabWarnings as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $r->warningType->name ?? $r->warning_type ?? '—') }}</td>
                    <td>{{ $fmt($r->issued_date ?? $r->created_at ?? null) }}</td>
                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->reason ?: ($r->description ?: '—'), 40) }}</small></td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-5">No warnings recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- COMPLAINTS --}}
          <div class="tab-pane fade {{ $active === 'complaints' ? 'show active' : '' }}" id="tab-complaints" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Subject</th><th>Category</th><th>Filed</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabComplaints as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->subject ?: ($r->title ?: '—'), 40) }}</td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $r->category ?? '—') }}</td>
                    <td>{{ $fmt($r->created_at ?? null) }}</td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-5">No complaints recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- TRIPS --}}
          <div class="tab-pane fade {{ $active === 'trips' ? 'show active' : '' }}" id="tab-trips" role="tabpanel">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Destination</th><th>Purpose</th><th>From</th><th>To</th><th>Status</th>
                </tr></thead>
                <tbody>
                @forelse($tabTrips as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td>{{ $r->destination ?? '—' }}</td>
                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($r->purpose ?: '—', 34) }}</small></td>
                    <td>{{ $fmt($r->start_date ?? null) }}</td>
                    <td>{{ $fmt($r->end_date ?? null) }}</td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-5">No trips recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

{{-- New Promotion --}}
<div class="modal fade" id="promotionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-trending-up"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">New Promotion</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.promotions.store') }}" method="POST">
          @csrf
          <div class="row g-4">
            <div class="col-12">
              <label class="form-label-hitech">Employee <span class="text-danger">*</span></label>
              <select name="user_id" class="form-select form-select-hitech lc-select" required>
                <option value="">Select employee</option>
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ $e->first_name }} {{ $e->last_name }} ({{ $e->code }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">New Designation <span class="text-danger">*</span></label>
              <select name="new_designation_id" class="form-select form-select-hitech lc-select" required>
                <option value="">Select designation</option>
                @foreach($designations as $d)
                  <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Promotion Date <span class="text-danger">*</span></label>
              <input type="date" name="promotion_date" class="form-control form-control-hitech"
                     value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Type</label>
              <select name="promotion_type" class="form-select form-select-hitech">
                <option value="regular">Regular</option>
                <option value="performance">Performance</option>
                <option value="restructure">Restructure</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">New Salary</label>
              <input type="number" step="0.01" name="new_salary" class="form-control form-control-hitech" placeholder="Optional">
            </div>
            <div class="col-12">
              <label class="form-label-hitech">Reason</label>
              <textarea name="reason" rows="2" class="form-control form-control-hitech"
                        placeholder="Why is this promotion being given?"></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save Promotion</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
  .lifecycle-tabs {
    border-bottom: 1px solid rgba(0, 90, 90, .1);
    gap: .25rem;
    flex-wrap: wrap;
  }
  .lifecycle-tabs .nav-link {
    border: 0;
    border-radius: 10px 10px 0 0;
    padding: .5rem .9rem;
    font-size: .82rem;
    font-weight: 700;
    color: #64748b;
    background: transparent;
  }
  .lifecycle-tabs .nav-link:hover { color: #005a5a; background: #f0f7f6; }
  .lifecycle-tabs .nav-link.active {
    color: #005a5a;
    background: #e6f4f1;
    box-shadow: inset 0 -3px 0 #007a7a;
  }
</style>

<script>
  // Clicking a stat card opens its tab; the chosen tab is written to the URL
  // so a refresh or a shared link lands back on it.
  function showLifecycleTab(key) {
    const btn = document.querySelector('[data-tabkey="' + key + '"]');
    if (btn && window.bootstrap) new bootstrap.Tab(btn).show();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-tabkey]').forEach(function (b) {
      b.addEventListener('shown.bs.tab', function () {
        const u = new URL(window.location);
        u.searchParams.set('tab', b.dataset.tabkey);
        window.history.replaceState({}, '', u);
      });
    });

    if (window.jQuery && jQuery.fn.select2) {
      jQuery('#promotionModal .lc-select').select2({
        dropdownParent: jQuery('#promotionModal'),
        width: '100%'
      });
    }
  });
</script>
@endsection
