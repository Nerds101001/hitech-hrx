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
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search promotion..."
                         data-filter-for="tab-promotions">
                </div>
                <span class="toolbar-count" data-count-for="tab-promotions"></span>
              </div>
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
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search transfer..."
                         data-filter-for="tab-transfers">
                </div>
                <span class="toolbar-count" data-count-for="tab-transfers"></span>
              </div>
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#transferModal">
                <i class="bx bx-plus me-1"></i>New Transfer
              </button>
            </div>
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
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search resignation..."
                         data-filter-for="tab-resignations">
                </div>
                <span class="toolbar-count" data-count-for="tab-resignations"></span>
              </div>
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#resignationModal">
                <i class="bx bx-plus me-1"></i>Record Resignation
              </button>
            </div>
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
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search termination..."
                         data-filter-for="tab-terminations">
                </div>
                <span class="toolbar-count" data-count-for="tab-terminations"></span>
              </div>
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#terminationModal">
                <i class="bx bx-plus me-1"></i>Record Termination
              </button>
            </div>
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
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search warning..."
                         data-filter-for="tab-warnings">
                </div>
                <span class="toolbar-count" data-count-for="tab-warnings"></span>
              </div>
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#warningModal">
                <i class="bx bx-plus me-1"></i>Issue Warning
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Type</th><th>Issued</th><th>Reason</th><th>Status</th><th>Acknowledged</th><th>Attachment</th>
                </tr></thead>
                <tbody>
                @forelse($tabWarnings as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $r->warning_type ?? '—') }}</td>
                    <td>{{ $fmt($r->warning_date ?? $r->created_at ?? null) }}</td>
                    <td>
                      <small class="text-muted">{{ \Illuminate\Support\Str::limit($r->description ?: '—', 40) }}</small>
                      <button type="button" class="btn btn-sm btn-link p-0 ms-1 lc-view-detail"
                        data-bs-toggle="modal" data-bs-target="#warningDetailModal"
                        data-employee="{{ e(trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? ''))) }}"
                        data-type="{{ e(str_replace('_', ' ', ucfirst($r->warning_type ?? '—'))) }}"
                        data-severity="{{ e(ucfirst($r->severity ?? '—')) }}"
                        data-date="{{ $fmt($r->warning_date ?? $r->created_at ?? null) }}"
                        data-description="{{ e($r->description ?? '') }}"
                        data-action-taken="{{ e($r->action_taken ?: '—') }}"
                        data-notes="{{ e($r->notes ?: '—') }}"
                        data-issued-by="{{ e(optional($r->givenBy)->getFullName() ?? 'System') }}"
                        data-attachment="{{ $r->attachment_path ? route('employee-lifecycle.warnings.attachment', $r->id) : '' }}"
                        data-remarks="{{ e($r->employee_remarks ?? '') }}"
                        data-remarks-at="{{ $r->employee_remarks_at ? $fmt($r->employee_remarks_at) : '' }}"
                      >Read more</button>
                    </td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                    <td>
                      @if($r->acknowledged_at)
                        <span class="badge bg-label-success rounded-pill">{{ \Carbon\Carbon::parse($r->acknowledged_at)->format('d M Y') }}</span>
                      @else
                        <span class="badge badge-muted rounded-pill">Pending</span>
                      @endif
                    </td>
                    <td>
                      @if($r->attachment_path)
                        <a href="{{ route('employee-lifecycle.warnings.attachment', $r->id) }}" target="_blank" class="btn btn-sm btn-hitech-modal-cancel"><i class="bx bx-paperclip"></i></a>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted py-5">No warnings recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- COMPLAINTS --}}
          <div class="tab-pane fade {{ $active === 'complaints' ? 'show active' : '' }}" id="tab-complaints" role="tabpanel">
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search complaint..."
                         data-filter-for="tab-complaints">
                </div>
                <span class="toolbar-count" data-count-for="tab-complaints"></span>
              </div>
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#complaintModal">
                <i class="bx bx-plus me-1"></i>File Complaint
              </button>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead><tr>
                  <th>Employee</th><th>Details</th><th>Type</th><th>Filed</th><th>Status</th><th>Acknowledged</th><th>Attachment</th>
                </tr></thead>
                <tbody>
                @forelse($tabComplaints as $r)
                  <tr>
                    <td>{!! $who($r) !!}</td>
                    <td>
                      <small class="text-muted">{{ \Illuminate\Support\Str::limit($r->description ?: '—', 40) }}</small>
                      <button type="button" class="btn btn-sm btn-link p-0 ms-1 lc-view-detail"
                        data-bs-toggle="modal" data-bs-target="#complaintDetailModal"
                        data-employee="{{ e(trim(($r->user->first_name ?? '') . ' ' . ($r->user->last_name ?? ''))) }}"
                        data-type="{{ e(str_replace('_', ' ', ucfirst($r->complaint_type ?? '—'))) }}"
                        data-severity="{{ e(ucfirst($r->severity ?? '—')) }}"
                        data-date="{{ $fmt($r->complaint_date ?? $r->created_at ?? null) }}"
                        data-description="{{ e($r->description ?? '') }}"
                        data-action-taken="{{ e($r->resolution ?: '—') }}"
                        data-notes="{{ e($r->notes ?: '—') }}"
                        data-issued-by="{{ e(optional($r->complainant)->getFullName() ?? '—') }}"
                        data-attachment="{{ $r->attachment_path ? route('employee-lifecycle.complaints.attachment', $r->id) : '' }}"
                        data-remarks="{{ e($r->employee_remarks ?? '') }}"
                        data-remarks-at="{{ $r->employee_remarks_at ? $fmt($r->employee_remarks_at) : '' }}"
                      >Read more</button>
                    </td>
                    <td class="text-capitalize">{{ str_replace('_', ' ', $r->complaint_type ?? '—') }}</td>
                    <td>{{ $fmt($r->complaint_date ?? $r->created_at ?? null) }}</td>
                    <td>{!! $pill($r->status ?? null) !!}</td>
                    <td>
                      @if($r->acknowledged_at)
                        <span class="badge bg-label-success rounded-pill">{{ \Carbon\Carbon::parse($r->acknowledged_at)->format('d M Y') }}</span>
                      @else
                        <span class="badge badge-muted rounded-pill">Pending</span>
                      @endif
                    </td>
                    <td>
                      @if($r->attachment_path)
                        <a href="{{ route('employee-lifecycle.complaints.attachment', $r->id) }}" target="_blank" class="btn btn-sm btn-hitech-modal-cancel"><i class="bx bx-paperclip"></i></a>
                      @else
                        <span class="text-muted">—</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted py-5">No complaints recorded</td></tr>
                @endforelse
                </tbody>
              </table>
            </div>
          </div>

          {{-- TRIPS --}}
          <div class="tab-pane fade {{ $active === 'trips' ? 'show active' : '' }}" id="tab-trips" role="tabpanel">
            <div class="table-toolbar">
              <div class="d-flex align-items-center gap-2">
                <div class="toolbar-search">
                  <i class="bx bx-search"></i>
                  <input type="text" placeholder="Search trip..."
                         data-filter-for="tab-trips">
                </div>
                <span class="toolbar-count" data-count-for="tab-trips"></span>
              </div>
              <button class="btn-hitech rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#tripModal">
                <i class="bx bx-plus me-1"></i>New Trip
              </button>
            </div>
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


{{-- New Transfer --}}
<div class="modal fade" id="transferModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-transfer"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">New Transfer</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.transfers.store') }}" method="POST">
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
              <label class="form-label-hitech">To Department <span class="text-danger">*</span></label>
              <select name="to_department_id" class="form-select form-select-hitech" required><option value="">Select department</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Transfer Date <span class="text-danger">*</span></label>
              <input type="date" name="transfer_date" class="form-control form-control-hitech" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Reason</label>
              <textarea name="reason" rows="2" class="form-control form-control-hitech"></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Record Resignation --}}
<div class="modal fade" id="resignationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-log-out"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">Record Resignation</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.resignations.store') }}" method="POST">
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
              <label class="form-label-hitech">Resignation Date <span class="text-danger">*</span></label>
              <input type="date" name="resignation_date" class="form-control form-control-hitech" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Last Working Day <span class="text-danger">*</span></label>
              <input type="date" name="last_working_day" class="form-control form-control-hitech" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Type <span class="text-danger">*</span></label>
              <select name="reason_type" class="form-select form-select-hitech" required><option value="voluntary">Voluntary</option><option value="involuntary">Involuntary</option><option value="retirement">Retirement</option></select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Rehireable</label>
              <select name="is_rehireable" class="form-select form-select-hitech"><option value="1">Yes</option><option value="0">No</option></select>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Reason</label>
              <textarea name="reason" rows="2" class="form-control form-control-hitech"></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Record Termination --}}
<div class="modal fade" id="terminationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-user-x"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">Record Termination</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.terminations.store') }}" method="POST">
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
              <label class="form-label-hitech">Termination Type <span class="text-danger">*</span></label>
              <select name="termination_type" class="form-select form-select-hitech" required><option value="layoff">Layoff</option><option value="misconduct">Misconduct</option><option value="performance">Performance</option><option value="violation">Violation</option><option value="contract_end">Contract End</option></select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Effective Date <span class="text-danger">*</span></label>
              <input type="date" name="termination_date" class="form-control form-control-hitech" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Reason</label>
              <textarea name="reason" rows="2" class="form-control form-control-hitech"></textarea>
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Issue Warning --}}
<div class="modal fade" id="warningModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-error"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">Issue Warning</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.warnings.store') }}" method="POST" enctype="multipart/form-data">
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
              <label class="form-label-hitech">Warning Type <span class="text-danger">*</span></label>
              <select name="warning_type" class="form-select form-select-hitech" required>
                <option value="verbal">Verbal</option>
                <option value="written">Written</option>
                <option value="performance">Performance</option>
                <option value="attendance">Attendance</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Severity <span class="text-danger">*</span></label>
              <select name="severity" class="form-select form-select-hitech" required>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Issued Date <span class="text-danger">*</span></label>
              <input type="date" name="warning_date" class="form-control form-control-hitech" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Reason <span class="text-danger">*</span></label>
              <textarea name="description" rows="2" class="form-control form-control-hitech" required></textarea>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Attachment</label>
              <input type="file" name="attachment" class="form-control form-control-hitech">
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- File Complaint --}}
<div class="modal fade" id="complaintModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-message-square-dots"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">File Complaint</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.complaints.store') }}" method="POST" enctype="multipart/form-data">
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
              <label class="form-label-hitech">Complaint Type <span class="text-danger">*</span></label>
              <select name="complaint_type" class="form-select form-select-hitech" required>
                <option value="harassment">Harassment</option>
                <option value="discrimination">Discrimination</option>
                <option value="safety">Safety</option>
                <option value="policy">Policy</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Severity <span class="text-danger">*</span></label>
              <select name="severity" class="form-select form-select-hitech" required>
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Date <span class="text-danger">*</span></label>
              <input type="date" name="complaint_date" class="form-control form-control-hitech" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Details <span class="text-danger">*</span></label>
              <textarea name="description" rows="2" class="form-control form-control-hitech" required></textarea>
            </div>
            <div class="col-md-12">
              <label class="form-label-hitech">Attachment</label>
              <input type="file" name="attachment" class="form-control form-control-hitech">
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- New Trip --}}
<div class="modal fade" id="tripModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content modal-content-hitech">
      <div class="modal-header modal-header-hitech">
        <div class="d-flex align-items-center">
          <div class="modal-icon-header me-3"><i class="bx bx-briefcase-alt"></i></div>
          <h5 class="modal-title modal-title-hitech mb-0">New Trip</h5>
        </div>
        <button type="button" class="btn-close-hitech" data-bs-dismiss="modal"><i class="bx bx-x"></i></button>
      </div>
      <div class="modal-body modal-body-hitech">
        <form action="{{ route('employee-lifecycle.trips.store') }}" method="POST">
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
              <label class="form-label-hitech">Destination <span class="text-danger">*</span></label>
              <input type="text" name="destination" class="form-control form-control-hitech" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Purpose</label>
              <input type="text" name="purpose" class="form-control form-control-hitech">
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">From <span class="text-danger">*</span></label>
              <input type="date" name="start_date" class="form-control form-control-hitech" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">To <span class="text-danger">*</span></label>
              <input type="date" name="end_date" class="form-control form-control-hitech" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-hitech">Estimated Cost</label>
              <input type="number" name="estimated_cost" class="form-control form-control-hitech" step="0.01">
            </div>
          </div>
          <div class="modal-footer border-0 px-0 pb-0 pt-4 d-flex justify-content-end gap-3">
            <button type="button" class="btn btn-hitech-modal-cancel" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-hitech-modal-submit">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

{{-- Warning full detail, filled in by JS from the "Read more" button's data-* attributes --}}
<div class="modal fade" id="warningDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title modal-title-hitech mb-0">Warning Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6"><small class="text-muted d-block">Employee</small><span class="lc-detail-employee fw-bold"></span></div>
          <div class="col-md-6"><small class="text-muted d-block">Issued By</small><span class="lc-detail-issuedby fw-bold"></span></div>
          <div class="col-md-4"><small class="text-muted d-block">Type</small><span class="lc-detail-type"></span></div>
          <div class="col-md-4"><small class="text-muted d-block">Severity</small><span class="lc-detail-severity"></span></div>
          <div class="col-md-4"><small class="text-muted d-block">Date</small><span class="lc-detail-date"></span></div>
        </div>
        <div class="mb-3">
          <small class="text-muted d-block">Incident Description</small>
          <p class="lc-detail-description mb-0" style="white-space: pre-wrap;"></p>
        </div>
        <div class="mb-3">
          <small class="text-muted d-block">Corrective Action Taken</small>
          <p class="lc-detail-action mb-0" style="white-space: pre-wrap;"></p>
        </div>
        <div class="mb-3 lc-detail-notes-wrap">
          <small class="text-muted d-block">Notes</small>
          <p class="lc-detail-notes mb-0" style="white-space: pre-wrap;"></p>
        </div>
        <div class="lc-detail-attachment-wrap mb-3">
          <a href="#" target="_blank" class="btn btn-sm btn-hitech-modal-cancel lc-detail-attachment"><i class="bx bx-paperclip me-1"></i>View Attachment</a>
        </div>
        <div class="lc-detail-remarks-wrap" style="display:none;">
          <hr>
          <small class="text-muted d-block">Employee's Reply <span class="lc-detail-remarks-at"></span></small>
          <p class="lc-detail-remarks mb-0" style="white-space: pre-wrap;"></p>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

{{-- Complaint full detail, same pattern as the warning one above --}}
<div class="modal fade" id="complaintDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content modal-content-hitech border-0 shadow-lg">
      <div class="modal-header">
        <h5 class="modal-title modal-title-hitech mb-0">Complaint Detail</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6"><small class="text-muted d-block">Against (Employee)</small><span class="lc-detail-employee fw-bold"></span></div>
          <div class="col-md-6"><small class="text-muted d-block">Filed By</small><span class="lc-detail-issuedby fw-bold"></span></div>
          <div class="col-md-4"><small class="text-muted d-block">Type</small><span class="lc-detail-type"></span></div>
          <div class="col-md-4"><small class="text-muted d-block">Severity</small><span class="lc-detail-severity"></span></div>
          <div class="col-md-4"><small class="text-muted d-block">Date</small><span class="lc-detail-date"></span></div>
        </div>
        <div class="mb-3">
          <small class="text-muted d-block">Complaint Description</small>
          <p class="lc-detail-description mb-0" style="white-space: pre-wrap;"></p>
        </div>
        <div class="mb-3">
          <small class="text-muted d-block">Resolution</small>
          <p class="lc-detail-action mb-0" style="white-space: pre-wrap;"></p>
        </div>
        <div class="mb-3 lc-detail-notes-wrap">
          <small class="text-muted d-block">Notes</small>
          <p class="lc-detail-notes mb-0" style="white-space: pre-wrap;"></p>
        </div>
        <div class="lc-detail-attachment-wrap mb-3">
          <a href="#" target="_blank" class="btn btn-sm btn-hitech-modal-cancel lc-detail-attachment"><i class="bx bx-paperclip me-1"></i>View Attachment</a>
        </div>
        <div class="lc-detail-remarks-wrap" style="display:none;">
          <hr>
          <small class="text-muted d-block">Employee's Reply <span class="lc-detail-remarks-at"></span></small>
          <p class="lc-detail-remarks mb-0" style="white-space: pre-wrap;"></p>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
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

    // per-tab search: hides non-matching rows and keeps a live count
    function refreshCount(paneId) {
      const pane = document.getElementById(paneId);
      if (!pane) return;
      const rows = pane.querySelectorAll('tbody tr');
      let shown = 0, total = 0;
      rows.forEach(function (tr) {
        if (tr.querySelector('td[colspan]')) return;   // the empty-state row
        total++;
        if (tr.style.display !== 'none') shown++;
      });
      const label = pane.parentElement.parentElement
        .querySelector('[data-count-for="' + paneId + '"]');
      if (label) {
        label.textContent = shown === total
          ? total + (total === 1 ? ' record' : ' records')
          : shown + ' of ' + total;
      }
    }

    document.querySelectorAll('[data-filter-for]').forEach(function (input) {
      const paneId = input.dataset.filterFor;
      input.addEventListener('input', function () {
        const q = input.value.trim().toLowerCase();
        const pane = document.getElementById(paneId);
        pane.querySelectorAll('tbody tr').forEach(function (tr) {
          if (tr.querySelector('td[colspan]')) return;
          tr.style.display = (!q || tr.innerText.toLowerCase().includes(q)) ? '' : 'none';
        });
        refreshCount(paneId);
      });
      refreshCount(paneId);
    });

    // "Read more" on a warning/complaint row: fill the shared detail modal
    // from the button's own data-* attributes, picked up right before Bootstrap
    // opens it (data-bs-toggle="modal" already handles the opening).
    document.querySelectorAll('.lc-view-detail').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const modal = document.querySelector(btn.dataset.bsTarget);
        if (!modal) return;
        const set = function (cls, value, isLink) {
          const el = modal.querySelector('.' + cls);
          if (!el) return;
          if (isLink) { el.href = value || '#'; }
          else { el.textContent = value || '—'; }
        };
        set('lc-detail-employee', btn.dataset.employee);
        set('lc-detail-issuedby', btn.dataset.issuedBy);
        set('lc-detail-type', btn.dataset.type);
        set('lc-detail-severity', btn.dataset.severity);
        set('lc-detail-date', btn.dataset.date);
        set('lc-detail-description', btn.dataset.description);
        set('lc-detail-action', btn.dataset.actionTaken);
        set('lc-detail-notes', btn.dataset.notes);
        const attachmentUrl = btn.dataset.attachment;
        modal.querySelector('.lc-detail-attachment-wrap').style.display = attachmentUrl ? '' : 'none';
        if (attachmentUrl) set('lc-detail-attachment', attachmentUrl, true);

        const remarks = btn.dataset.remarks;
        modal.querySelector('.lc-detail-remarks-wrap').style.display = remarks ? '' : 'none';
        if (remarks) {
          set('lc-detail-remarks', remarks);
          set('lc-detail-remarks-at', btn.dataset.remarksAt ? '(' + btn.dataset.remarksAt + ')' : '');
        }
      });
    });

    if (window.jQuery && jQuery.fn.select2) {
      jQuery('.modal .lc-select').select2({ width: '100%' });
      jQuery('#promotionModal .lc-select').select2({
        dropdownParent: jQuery('#promotionModal'),
        width: '100%'
      });
    }
  });
</script>
@endsection
