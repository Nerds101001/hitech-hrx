@extends('layouts.layoutMaster')

@section('title', 'Manage Job')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .icon-teal { background: rgba(0, 128, 128, 0.1); color: #008080; }
    .icon-blue { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .icon-red { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
    
    .hitech-action-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        transition: all 0.3s ease;
        border: none;
    }
    .hitech-action-icon:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
  </style>
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  <script>
    $(document).ready(function() {
      $('.copy_link').click(function(e) {
        e.preventDefault();
        var copyText = $(this).attr('href');
        navigator.clipboard.writeText(copyText).then(function() {
             Swal.fire({
                title: 'Success!',
                text: 'Url copied to clipboard',
                icon: 'success',
                customClass: {
                  confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
              });
        });
      });

      var table = $('.datatables-jobs').DataTable({
        dom: 't<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
        language: {
          info: 'Showing _START_ to _END_ of _TOTAL_ jobs',
          paginate: {
            next: '<i class="bx bx-chevron-right"></i>',
            previous: '<i class="bx bx-chevron-left"></i>'
          }
        },
        scrollX: true
      });
      $('#customSearchInput').on('keyup', function() { table.search(this.value).draw(); });
      $('#customLengthMenu').on('change', function() { table.page.len($(this).val()).draw(); });
    });
  </script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
        <h4 class="fw-bold mb-1 text-heading" style="font-size: 1.5rem;">Manage Jobs</h4>
        <p class="text-muted small mb-0">Overview of all active and inactive job vacancies.</p>
    </div>
    @can('Create Job')
      <a href="{{ route('job.create') }}" class="btn-hitech shadow-sm rounded-pill px-5">
        <i class="bx bx-plus-circle me-1"></i> Create New Job
      </a>
    @endcan
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-4 col-md-6">
      <div class="card hitech-stat-card border-0">
        <div class="card-body p-4">
          <div class="stat-icon-wrap icon-teal">
            <i class="bx bx-briefcase"></i>
          </div>
          <span class="stat-label text-muted small fw-bold text-uppercase d-block mb-1">Total Jobs</span>
          <h3 class="stat-value fw-bold mb-0">{{$data['total']}}</h3>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6">
      <div class="card hitech-stat-card border-0">
        <div class="card-body p-4">
          <div class="stat-icon-wrap icon-blue">
            <i class="bx bx-check-circle"></i>
          </div>
          <span class="stat-label text-muted small fw-bold text-uppercase d-block mb-1">Active Jobs</span>
          <h3 class="stat-value fw-bold mb-0 text-primary">{{$data['active']}}</h3>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6">
      <div class="card hitech-stat-card border-0">
        <div class="card-body p-4">
          <div class="stat-icon-wrap icon-red">
            <i class="bx bx-x-circle"></i>
          </div>
          <span class="stat-label text-muted small fw-bold text-uppercase d-block mb-1">Inactive Jobs</span>
          <h3 class="stat-value fw-bold mb-0 text-danger">{{$data['in_active']}}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="hitech-card-white mb-6 border-0 shadow-sm overflow-hidden animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
    <div class="card-body p-4">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
        <div class="search-wrapper-hitech w-px-400 mw-100">
          <i class="bx bx-search text-muted ms-3"></i>
          <input type="text" class="form-control" placeholder="Search..." id="customSearchInput">
          <button class="btn-search d-none d-sm-flex" id="customSearchBtn">
            <i class="bx bx-search fs-5"></i>Search
          </button>
        </div>
        <div class="d-flex align-items-center gap-3">
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

  <div class="hitech-card-white border-0 shadow-sm overflow-hidden animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
    <div class="table-responsive">
      <table class="datatables-jobs table m-0">
        <thead>
          <tr>
            <th class="ps-4">{{ __('Branch') }}</th>
            <th>{{ __('Job Title') }}</th>
            <th>{{ __('Timeline') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Created At') }}</th>
            <th class="text-center pe-4">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($jobs as $job)
            <tr>
              <td>
                <span class="badge bg-label-secondary rounded-pill small fw-bold">
                    {{ !empty($job->branches) ? $job->branches->name : __('Global') }}
                </span>
              </td>
              <td>
                <div class="d-flex flex-column">
                    <span class="fw-bold text-dark">{{ $job->title }}</span>
                    <span class="text-muted x-small" style="font-size: 0.75rem;">ID: #JB-{{ str_pad($job->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>
              </td>
              <td>
                <div class="d-flex align-items-center x-small" style="font-size: 0.8rem;">
                    <i class="bx bx-calendar-event me-1 text-muted"></i>
                    {{ auth()->user()->dateFormat($job->start_date) }} - {{ auth()->user()->dateFormat($job->end_date) }}
                </div>
              </td>
              <td>
                @if ($job->status == 'active')
                  <span class="badge badge-hitech bg-label-success">ACTIVE</span>
                @else
                  <span class="badge badge-hitech bg-label-danger">INACTIVE</span>
                @endif
              </td>
              <td class="small text-muted">{{ auth()->user()->dateFormat($job->created_at) }}</td>
              <td class="text-center">
                <div class="d-flex align-items-center justify-content-center gap-2">
                  @can('Show Job')
                    <a href="{{ route('job.show', $job->id) }}" class="hitech-action-icon btn-label-info" data-bs-toggle="tooltip" title="{{ __('Detail') }}">
                      <i class="bx bx-show fs-5"></i>
                    </a>
                  @endcan
                  @can('Edit Job')
                    <a href="{{ route('job.edit', $job->id) }}" class="hitech-action-icon btn-label-warning" data-bs-toggle="tooltip" title="{{ __('Edit') }}">
                      <i class="bx bx-edit fs-5"></i>
                    </a>
                  @endcan
                  @can('Delete Job')
                    <form action="{{ route('job.destroy', $job->id) }}" method="POST" class="d-inline" id="delete-form-{{ $job->id }}">
                      @csrf
                      @method('DELETE')
                      <button type="button" class="hitech-action-icon btn-label-danger" onclick="confirmDelete('{{ $job->id }}')" data-bs-toggle="tooltip" title="{{ __('Delete') }}">
                        <i class="bx bx-trash fs-5"></i>
                      </button>
                    </form>
                  @endcan
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Delete Job Post?',
      text: "This will remove the job vacancy and all associated applications.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it',
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
