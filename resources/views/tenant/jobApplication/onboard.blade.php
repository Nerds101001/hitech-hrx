@extends('layouts/layoutMaster')

@section('title', 'Manage Job On-Boarding')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
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
          paginate: {
            next: '<i class="bx bx-chevron-right"></i>',
            previous: '<i class="bx bx-chevron-left"></i>'
          }
        }
      });
      $('#customSearchInput').on('keyup', function() {
        table.search(this.value).draw();
      });
      $('#customSearchBtn').on('click', function() {
        table.search($('#customSearchInput').val()).draw();
      });
      $('#customLengthMenu').on('change', function() {
        table.page.len($(this).val()).draw();
      });
    });
  </script>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <h3 class="mb-0 fw-bold text-heading" style="font-size: 1.5rem;">Job On-Boarding</h3>
    @can('Create Interview Schedule')
      <a href="#" data-url="{{ route('job.on.board.create', 0) }}" data-ajax-popup="true" data-size="md" data-title="Create New On-Boarding" class="btn btn-hitech-primary shadow-sm">
        <i class="bx bx-plus me-1"></i>Create On-Boarding
      </a>
    @endcan
  </div>

  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden">
      <div class="card-body p-sm-5 p-4">
        <div class="row align-items-center g-4">
          <div class="col-md-9 d-flex align-items-center gap-3 w-100">
            <div class="search-wrapper-hitech flex-grow-1">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" class="form-control border-0 bg-transparent shadow-none" placeholder="Search On-Boarding..." id="customSearchInput">
            </div>
            <button class="btn btn-primary btn-sm px-3 shadow-sm d-flex align-items-center gap-1" id="customSearchBtn" style="background-color: #0f766e; border-color: #0f766e;">
              <i class="bx bx-search"></i> Search
            </button>
          </div>
          <div class="col-md-3 d-flex align-items-center justify-content-end gap-3 mt-0">
            <span class="text-muted fw-semibold small text-nowrap">Per Page:</span>
            <select class="form-select flex-shrink-0 w-px-80 rounded text-center border-light shadow-none fw-bold" id="customLengthMenu" style="background-color: #f8f9fa;">
              <option value="10">10</option>
              <option value="25">25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="hitech-card-white p-0 overflow-hidden">
        <div class="card-datatable table-responsive">
          <table class="table datatable table-hover">
            <thead>
              <tr class="text-muted small text-uppercase">
                <th class="border-bottom">Candidate</th>
                <th class="border-bottom">Job Posting</th>
                <th class="border-bottom">Branch/Site</th>
                <th class="border-bottom text-center">Applied Date</th>
                <th class="border-bottom text-center">Joining Date</th>
                <th class="border-bottom text-center">Status</th>
                <th class="border-bottom text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($jobOnBoards as $job)
                <tr>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar avatar-sm me-3 bg-label-primary rounded-circle d-flex align-items-center justify-content-center">
                        <i class="bx bx-user"></i>
                      </div>
                      <span class="fw-bold text-heading">{{ $job->applications->name ?? '-' }}</span>
                    </div>
                  </td>
                  <td><span class="text-muted small">{{ $job->applications->jobs->title ?? '-' }}</span></td>
                  <td><span class="badge bg-label-secondary small">{{ $job->applications->jobs->branches->name ?? '-' }}</span></td>
                  <td class="text-center small">{{ $job->applications ? auth()->user()->dateFormat($job->applications->created_at) : '-' }}</td>
                  <td class="text-center fw-bold text-primary">{{ auth()->user()->dateFormat($job->joining_date) }}</td>
                  <td class="text-center">
                    @php
                      $statusClass = [
                        'pending' => 'bg-label-warning',
                        'cancel' => 'bg-label-danger',
                        'confirm' => 'bg-label-success'
                      ][$job->status] ?? 'bg-label-info';
                    @endphp
                    <span class="badge {{ $statusClass }} rounded-pill px-3">{{ ucfirst($job->status) }}</span>
                  </td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-2">
                      @if ($job->status == 'confirm')
                        @if ($job->convert_to_employee == 0)
                          <a href="{{ route('job.on.board.convert', $job->id) }}" class="btn btn-icon btn-label-dark btn-sm" data-ajax-popup="true" data-title="Convert to Employee" data-bs-toggle="tooltip" title="Convert to Employee">
                            <i class="bx bx-repost"></i>
                          </a>
                        @else
                          <a href="{{ route('employee.show', \Crypt::encrypt($job->convert_to_employee)) }}" class="btn btn-icon btn-label-warning btn-sm" data-bs-toggle="tooltip" title="View Employee">
                            <i class="bx bx-show"></i>
                          </a>
                        @endif
                        
                        <a href="{{ route('offerlatter.download.pdf', $job->id) }}" class="btn btn-icon btn-label-primary btn-sm" data-bs-toggle="tooltip" title="Offer Letter (PDF)" target="_blank">
                          <i class="bx bxs-file-pdf"></i>
                        </a>
                      @endif

                      <a href="#" data-url="{{ route('job.on.board.edit', $job->id) }}" data-ajax-popup="true" data-title="Edit On-Boarding" class="btn btn-icon btn-label-info btn-sm" data-bs-toggle="tooltip" title="Edit">
                        <i class="bx bx-edit-alt"></i>
                      </a>

                      <form action="{{ route('job.on.board.delete', $job->id) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-icon btn-label-danger btn-sm" data-bs-toggle="tooltip" title="Delete">
                          <i class="bx bx-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
