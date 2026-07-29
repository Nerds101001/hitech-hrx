@extends('layouts/layoutMaster')

@section('title', 'Attendance Setup Queue')

{{-- hitech-portal.scss defines hitech-card-white and the rest of the portal
     styling. Without it this page renders as unstyled text. --}}
@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('content')
<div class="layout-full-width">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">Attendance Setup</h3>
      <p class="text-muted mb-0 small">
        Record each new joiner's employee ID and biometric ID so payroll and attendance can begin.
      </p>
    </div>
    @if($pendingCount)
      <span class="badge bg-label-warning fs-6 px-3 py-2">{{ $pendingCount }} pending</span>
    @else
      <span class="badge bg-label-success fs-6 px-3 py-2">All caught up</span>
    @endif
  </div>

  <div class="px-4">
    <div class="hitech-card-white p-0 overflow-hidden">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Employee</th>
              <th>Department</th>
              <th>Joined</th>
              <th>Required</th>
              <th>Status</th>
              <th class="text-end">Recorded</th>
            </tr>
          </thead>
          <tbody>
            @forelse($tasks as $t)
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <span class="avatar avatar-sm">
                      @if(optional($t->user)->getProfilePicture())
                        <img src="{{ $t->user->getProfilePicture() }}" class="rounded-circle" alt="">
                      @else
                        <span class="avatar-initial rounded-circle bg-label-primary">
                          {{ optional($t->user)->getInitials() ?? '?' }}
                        </span>
                      @endif
                    </span>
                    <span>
                      <span class="fw-semibold d-block">{{ optional($t->user)->getFullName() ?? '—' }}</span>
                      <small class="text-muted">{{ optional(optional($t->user)->designation)->name ?? '—' }}</small>
                    </span>
                  </div>
                </td>
                <td>{{ optional(optional($t->user)->department)->name ?? '—' }}</td>
                <td>
                  {{ optional($t->user)->date_of_joining
                      ? \Carbon\Carbon::parse($t->user->date_of_joining)->format('d M Y') : '—' }}
                </td>
                <td>
                  @if($t->needs_emp_id)<span class="badge bg-label-info me-1">Employee ID</span>@endif
                  @if($t->needs_biometric)
                    <span class="badge bg-label-warning">Biometric</span>
                  @else
                    <span class="badge bg-label-secondary">Biometric exempt</span>
                  @endif
                </td>
                <td>
                  @if($t->status === 'pending')
                    <span class="badge bg-label-warning">Pending</span>
                  @else
                    <span class="badge bg-label-success">Completed</span>
                  @endif
                </td>
                <td class="text-end">
                  @if($t->status === 'completed')
                    <small class="text-muted d-block">
                      {{ $t->emp_id_value ? 'ID ' . $t->emp_id_value : '' }}
                      {{ $t->biometric_id_value ? ' · Bio ' . $t->biometric_id_value : '' }}
                    </small>
                    <small class="text-muted">
                      by {{ optional($t->completedBy)->getFullName() ?? '—' }},
                      {{ optional($t->completed_at)->format('d M Y') }}
                    </small>
                  @else
                    <button type="button" class="btn btn-sm btn-primary js-setup-task"
                            data-id="{{ $t->id }}"
                            data-name="{{ optional($t->user)->getFullName() }}"
                            data-meta="{{ trim(implode(' · ', array_filter([
                                optional(optional($t->user)->designation)->name,
                                optional(optional($t->user)->department)->name,
                            ]))) }}"
                            data-emp="{{ optional($t->user)->code }}"
                            data-needs-emp="{{ $t->needs_emp_id ? 1 : 0 }}"
                            data-needs-bio="{{ $t->needs_biometric ? 1 : 0 }}">
                      <i class="bx bx-edit-alt me-1"></i>Complete
                    </button>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-5">No setup tasks yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if($tasks->hasPages())
        <div class="p-3">{{ $tasks->links() }}</div>
      @endif
    </div>
  </div>
</div>

@include('tenant.employees._setup-modal')
@endsection
