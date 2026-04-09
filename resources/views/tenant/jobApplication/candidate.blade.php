@extends('layouts.layoutMaster')

@section('title', 'Manage Archive Applications')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .voted { color: #f59e0b; }
    .avatar-ring {
        padding: 2px;
        background: linear-gradient(135deg, var(--hitech-primary) 0%, #3b82f6 100%);
        border-radius: 50%;
    }

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
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
  ])
@endsection

@section('page-script')
  <script>
    $(document).ready(function() {
      var table = $('.datatable').DataTable({
        dom: 't<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
        language: {
          info: 'Showing _START_ to _END_ of _TOTAL_ candidates',
          paginate: {
            next: '<i class="bx bx-chevron-right"></i>',
            previous: '<i class="bx bx-chevron-left"></i>'
          }
        }
      });
      $('#customSearchInput').on('keyup', function() { table.search(this.value).draw(); });
      $('#customLengthMenu').on('change', function() { table.page.len($(this).val()).draw(); });
    });
  </script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-4 px-2">
    <div>
        <h4 class="fw-bold mb-1">Archived Applications</h4>
        <p class="text-muted small mb-0">View and manage candidates in the archive.</p>
    </div>
    <a href="{{ route('job-application.index') }}" class="btn btn-label-secondary shadow-sm rounded-pill px-4">
      <i class="bx bx-left-arrow-alt me-1"></i> Back to Board
    </a>
  </div>

  <div class="hitech-card-white mb-4 border-0 shadow-sm overflow-hidden">
    <div class="card-body p-4">
      <div class="row align-items-center g-4">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-3">
                <div class="search-wrapper-hitech w-px-400 mw-100">
                  <i class="bx bx-search text-muted ms-3"></i>
                  <input type="text" class="form-control" placeholder="Search..." id="customSearchInput">
                  <button class="btn-search d-none d-sm-flex" id="customSearchBtn">
                    <i class="bx bx-search fs-5"></i>Search
                  </button>
                </div>
                
                <form action="{{ route('job.application.candidate') }}" method="GET" class="d-flex align-items-center gap-3 m-0">
                   <select class="form-select w-px-200 rounded-pill border-light shadow-none fw-bold" name="job">
                        <option value="">All Positions</option>
                        @foreach($jobs as $key => $val)
                            <option value="{{ $key }}" {{ $filter['job'] == $key ? 'selected' : '' }}>{{ $val }}</option>
                        @endforeach
                   </select>
                   <button type="submit" class="btn-hitech shadow-sm px-4">
                     <i class="bx bx-filter-alt me-1"></i>Filter
                   </button>
                </form>
            </div>
        </div>
        <div class="col-lg-4 d-flex align-items-center justify-content-end gap-3">
          <span class="text-muted small fw-bold text-uppercase">Page Size:</span>
          <select class="form-select w-px-80 rounded-pill border-light shadow-none fw-bold" id="customLengthMenu">
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="hitech-card-white border-0 shadow-sm overflow-hidden">
    <div class="table-responsive">
      <table class="datatable table m-0">
          <thead>
            <tr>
              <th class="ps-4">Candidate</th>
              <th>Applied For</th>
              <th>Expertise</th>
              <th>Applied Date</th>
              <th>CV/Resume</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($archive_application as $application)
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    @php
                      $avatarPath = !empty($application->profile) ? 'uploads/job/profile/' . $application->profile : 'uploads/avatar/avatar.png';
                    @endphp
                    <div class="avatar avatar-sm me-3 avatar-ring">
                      <img src="{{ Utility::get_file($avatarPath) }}" alt="Avatar" class="rounded-circle border-2 border-white shadow-sm">
                    </div>
                    <div class="d-flex flex-column">
                        <a href="#" data-url="{{ route('job-application.show', \Crypt::encrypt($application->id)) }}" data-ajax-popup="true" data-size="xl" data-title="{{ __('Application Details') }}" class="fw-bold text-dark">{{ $application->name }}</a>
                        <span class="text-muted x-small" style="font-size: 0.7rem;">ID: #APP-{{ str_pad($application->id, 4, '0', STR_PAD_LEFT) }}</span>
                    </div>
                  </div>
                </td>
                <td>
                    <span class="badge bg-label-info rounded-pill px-3">{{ !empty($application->jobs) ? $application->jobs->title : '-' }}</span>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    @for ($i = 1; $i <= 5; $i++)
                      <i class="bx bxs-star {{ $i <= $application->rating ? 'voted' : 'text-light' }}" style="font-size: 0.85rem;"></i>
                    @endfor
                  </div>
                </td>
                <td class="small text-muted">{{ auth()->user()->dateFormat($application->created_at) }}</td>
                <td>
                  @if (!empty($application->resume))
                    <div class="d-flex gap-2">
                      <a href="{{ Utility::get_file('uploads/job/resume/' . $application->resume) }}" class="btn btn-icon btn-label-primary btn-sm rounded-circle" data-bs-toggle="tooltip" title="Download CV" download>
                        <i class="bx bx-download"></i>
                      </a>
                      <a href="{{ Utility::get_file('uploads/job/resume/' . $application->resume) }}" target="_blank" class="btn btn-icon btn-label-info btn-sm rounded-circle" data-bs-toggle="tooltip" title="View CV">
                        <i class="bx bx-show"></i>
                      </a>
                    </div>
                  @else
                    <span class="badge bg-label-secondary rounded-pill small">NO RESUME</span>
                  @endif
                </td>
                <td class="text-end">
                  <div class="dropdown">
                    <button type="button" class="btn btn-icon btn-label-secondary btn-sm rounded-circle hide-arrow" data-bs-toggle="dropdown">
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2">
                      <a class="dropdown-item rounded-3 d-flex align-items-center mb-1" href="#" data-url="{{ route('job-application.show', \Crypt::encrypt($application->id)) }}" data-ajax-popup="true" data-size="xl" data-title="{{ __('Application Details') }}">
                        <i class="bx bx-id-card me-2 text-primary fs-5"></i> 
                        <span class="fw-medium">View Profile</span>
                      </a>
                      <form action="{{ route('job.application.archive', $application->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item rounded-3 d-flex align-items-center text-success">
                          <i class="bx bx-undo me-2 fs-5"></i> 
                          <span class="fw-medium">Restore Application</span>
                        </button>
                      </form>
                    </div>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
</div>
@endsection
