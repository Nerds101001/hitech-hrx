@extends('layouts.layoutMaster')

@section('title', 'Manage Job Applications')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dragula/3.7.3/dragula.min.css">
  <style>
    .kanban-wrapper {
        display: flex;
        gap: 1.5rem;
        overflow-x: auto;
        padding: 0.5rem 0.5rem 1.5rem 0.5rem;
        min-height: calc(100vh - 320px);
        scroll-behavior: smooth;
    }
    .kanban-column {
        flex: 0 0 340px;
        background: #F8FAFC;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        max-height: 100%;
        border: 1px solid #E2E8F0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        transition: all 0.3s ease;
    }
    .kanban-header {
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        border-radius: 16px 16px 0 0;
        border-bottom: 1px solid #E2E8F0;
    }
    .kanban-header h6 {
        color: #127464 !important;
        font-weight: 800 !important;
        font-size: 0.95rem;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .kanban-header .count {
        background-color: #E8F5E9 !important;
        color: #127464 !important;
        border: 1px solid #C8E6C9;
        font-weight: 700;
    }
    .kanban-items {
        padding: 1.25rem;
        flex-grow: 1;
        min-height: 200px;
    }
    .kanban-card {
        background: #fff;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        margin-bottom: 1rem;
        cursor: grab;
        border: 1px solid #E2E8F0;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .kanban-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px rgba(18,116,100,0.08);
        border-color: #127464;
    }
    .kanban-card:active { cursor: grabbing; }
    
    .gu-mirror {
        position: fixed !important;
        margin: 0 !important;
        z-index: 9999 !important;
        opacity: 0.95;
        transform: rotate(3deg);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        border: 2px solid #127464 !important;
        background: #fff !important;
        border-radius: 12px;
    }
    .gu-transit { background: rgba(18, 116, 100, 0.05) !important; border: 2px dashed #127464 !important; opacity: 0.4; border-radius: 12px; }
    
    .voted { color: #f59e0b; }

    .hitech-action-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.3s ease;
        border: none;
        background: transparent;
    }
    .hitech-action-icon:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .kanban-card-title {
        color: #1E293B;
        font-size: 1rem;
        transition: color 0.2s;
    }
    .kanban-card:hover .kanban-card-title {
        color: #127464;
    }
    .kanban-card-badge {
        background-color: #F8FAFC !important;
        color: #475569 !important;
        border: 1px solid #E2E8F0;
        font-weight: 600;
        padding: 0.35em 0.65em;
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/flatpickr/flatpickr.js'
  ])
@endsection

@section('page-script')
  <script>
    $(document).ready(function() {
      $('.flatpickr-date').flatpickr({ dateFormat: 'Y-m-d' });
    });
  </script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
        <h4 class="fw-bold mb-1 text-heading" style="font-size: 1.5rem;">Job Applications</h4>
        <p class="text-muted small mb-0">Track and manage candidates through recruitment stages.</p>
    </div>
    @can('Create Job Application')
      <a href="#" data-url="{{ route('job-application.create') }}" data-ajax-popup="true" data-size="xl" data-title="{{ __('Create New Job Application') }}" class="btn-hitech shadow-sm rounded-pill px-5">
        <i class="bx bx-plus-circle me-1"></i> New Application
      </a>
    @endcan
  </div>

  {{-- New Filter Section matching Employees --}}
  <div class="hitech-card-white mb-6 border-0 shadow-sm overflow-hidden animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
    <div class="card-body p-4">
      <form action="{{ route('job-application.index') }}" method="GET" id="application_filter">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
          <div class="search-wrapper-hitech w-px-400 mw-100">
            <i class="bx bx-search text-muted ms-3"></i>
            <input type="text" class="form-control" placeholder="Search..." id="customSearchInput">
            <button class="btn-search d-none d-sm-flex" id="customSearchBtn">
              <i class="bx bx-search fs-5"></i>Search
            </button>
          </div>
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-white border shadow-sm rounded-pill px-4" type="button" data-bs-toggle="collapse" data-bs-target="#advancedFilters" aria-expanded="false">
              <i class="bx bx-filter-alt me-1"></i> Advanced Filters
            </button>
            <a href="{{ route('job-application.index') }}" class="btn btn-white border shadow-sm rounded-pill p-2" data-bs-toggle="tooltip" title="Reset Filters">
              <i class="bx bx-refresh fs-4"></i>
            </a>
          </div>
        </div>

        <div class="collapse mt-4" id="advancedFilters">
          <div class="p-4 rounded-4 bg-light bg-opacity-50 border border-dashed">
            <div class="row g-4 align-items-end">
              <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase">Start Date</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bx bx-calendar text-muted"></i></span>
                    <input class="form-control form-control-hitech flatpickr-date border-start-0 ps-0" name="start_date" type="text" value="{{ $filter['start_date'] }}" placeholder="YYYY-MM-DD">
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase">End Date</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bx bx-calendar text-muted"></i></span>
                    <input class="form-control form-control-hitech flatpickr-date border-start-0 ps-0" name="end_date" type="text" value="{{ $filter['end_date'] }}" placeholder="YYYY-MM-DD">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold small text-muted text-uppercase">Job Posting</label>
                <select class="form-select form-select-hitech select2" id="job_id" name="job">
                    <option value="">All Positions</option>
                    @foreach($jobs as $key => $val)
                        <option value="{{ $key }}" {{ $filter['job'] == $key ? 'selected' : '' }}>{{ $val }}</option>
                    @endforeach
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn-hitech-primary w-100 shadow-sm">
                  Apply Filter
                </button>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="hitech-card-white border-0 shadow-sm overflow-hidden animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="applications-table">
          <thead class="table-light">
            <tr>
              <th class="ps-4 py-3 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Candidate</th>
              <th class="py-3 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Job Position</th>
              <th class="py-3 text-uppercase fw-bold" style="font-size: 0.75rem; letter-spacing: 0.5px;">Stage</th>
              <th class="py-3 text-uppercase fw-bold text-center" style="font-size: 0.75rem; letter-spacing: 0.5px;">Rating</th>
              <th class="py-3 text-uppercase fw-bold text-center" style="font-size: 0.75rem; letter-spacing: 0.5px;">Applied Date</th>
              <th class="pe-4 py-3 text-uppercase fw-bold text-end" style="font-size: 0.75rem; letter-spacing: 0.5px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @php $hasApplications = false; @endphp
            @foreach ($stages as $stage)
              @php $applications = $stage->applications($filter) @endphp
              @foreach ($applications as $application)
                @php $hasApplications = true; @endphp
                <tr>
                  <td class="ps-4 py-3">
                    <div class="d-flex align-items-center">
                      <div class="avatar avatar-sm me-3">
                        @php
                          $avatarPath = !empty($application->profile) ? 'uploads/job/profile/' . $application->profile : 'uploads/avatar/avatar.png';
                        @endphp
                        <img src="{{ Utility::get_file($avatarPath) }}" alt="Avatar" class="rounded-circle shadow-sm" style="object-fit: cover;">
                      </div>
                      <div>
                        <a href="{{ route('job-application.show', \Illuminate\Support\Facades\Crypt::encrypt($application->id)) }}" class="fw-bold text-dark text-decoration-none h6 mb-1 d-block" style="color: #127464 !important;">{{ $application->name }}</a>
                        <div class="d-flex flex-column text-muted small">
                          <span><i class="bx bx-envelope me-1"></i> {{ $application->email ?? 'N/A' }}</span>
                          <span><i class="bx bx-phone me-1"></i> {{ $application->phone ?? 'N/A' }}</span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="py-3">
                    <span class="badge bg-label-secondary rounded-pill px-3 py-1 fw-semibold">
                      <i class="bx bx-briefcase-alt-2 me-1"></i>{{ !empty($application->jobs) ? $application->jobs->title : 'Multiple' }}
                    </span>
                  </td>
                  <td class="py-3">
                    <span class="badge bg-label-info rounded-pill px-3 py-1 fw-semibold shadow-sm border border-info border-opacity-25">{{ $stage->title }}</span>
                  </td>
                  <td class="text-center py-3">
                    <div class="d-flex gap-1 justify-content-center bg-light px-2 py-1 rounded-pill d-inline-flex">
                      @for ($i = 1; $i <= 5; $i++)
                        <i class="bx bxs-star {{ $i <= $application->rating ? 'text-warning' : 'text-light border-light' }}" style="font-size: 0.8rem;"></i>
                      @endfor
                    </div>
                  </td>
                  <td class="text-center py-3 text-muted small fw-medium">
                    {{ auth()->user()->dateFormat($application->created_at) }}
                  </td>
                  <td class="pe-4 py-3 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                       @can('Show Job Application')
                          <a href="#" data-url="{{ route('job-application.show', \Illuminate\Support\Facades\Crypt::encrypt($application->id)) }}" data-ajax-popup="true" data-size="xl" data-title="{{ __('Application Details') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="tooltip" title="View Detail">
                            <i class="bx bx-detail me-1"></i> View
                          </a>
                       @endcan
                       @can('Delete Job Application')
                          <form action="{{ route('job-application.destroy', $application->id) }}" method="POST" id="delete-form-{{ $application->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="confirmDelete('{{ $application->id }}')" data-bs-toggle="tooltip" title="Delete">
                              <i class="bx bx-trash"></i>
                            </button>
                          </form>
                       @endcan
                    </div>
                  </td>
                </tr>
              @endforeach
            @endforeach
            @if(!$hasApplications)
              <tr>
                <td colspan="6" class="text-center py-5">
                   <div class="empty-state text-center">
                        <i class="bx bx-folder-open text-muted opacity-50 mb-3" style="font-size: 3rem;"></i>
                        <h6 class="fw-bold mb-1">No Applications Found</h6>
                        <p class="text-muted small mb-0">Try adjusting your filters or wait for new candidates to apply.</p>
                   </div>
                </td>
              </tr>
            @endif
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Remove Application?',
      text: "This will permanently delete the candidate's application and all history.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Delete It',
      customClass: {
        confirmButton: 'btn btn-danger me-3 rounded-pill',
        cancelButton: 'btn btn-label-secondary rounded-pill'
      },
      buttonsStyling: false
    }).then(function(result) {
      if (result.value) {
        document.getElementById('delete-form-' + id).submit();
      }
    });
  }
</script>
@endsection
