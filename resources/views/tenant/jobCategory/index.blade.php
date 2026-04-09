@extends('layouts.layoutMaster')

@section('title', 'Manage Job Categories')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
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
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
  ])
@endsection

@section('page-script')
  <script>
    $(document).ready(function() {
      var table = $('.datatable').DataTable({
        dom: 't<"d-flex justify-content-between align-items-center mx-3 mt-4 mb-2" <"small text-muted" i> <"pagination-wrapper" p>>',
        language: {
          info: 'Showing _START_ to _END_ of _TOTAL_ categories',
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
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
        <h4 class="fw-bold mb-1 text-heading" style="font-size: 1.5rem;">Job Categories</h4>
        <p class="text-muted small mb-0">Organize and classify your job vacancies.</p>
    </div>
    @can('Create Job Category')
      <a href="#" data-url="{{ route('job-category.create') }}" data-ajax-popup="true" data-size="md" data-title="Create New Job Category" class="btn-hitech shadow-sm rounded-pill px-5">
        <i class="bx bx-plus-circle me-1"></i> New Category
      </a>
    @endcan
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
      <table class="datatable table m-0">
          <thead>
            <tr>
              <th class="ps-4">Category Name</th>
              <th class="text-end pe-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($categories as $category)
              <tr>
                <td><span class="fw-bold text-dark">{{ $category->title }}</span></td>
                <td class="text-end">
                  <div class="d-flex justify-content-end gap-2 text-end">
                    @can('Edit Job Category')
                      <a href="#" data-url="{{ route('job-category.edit', $category->id) }}" data-ajax-popup="true" data-title="Edit Job Category" class="hitech-action-icon btn-label-info" data-bs-toggle="tooltip" title="Edit">
                        <i class="bx bx-edit-alt fs-5"></i>
                      </a>
                    @endcan

                    @can('Delete Job Category')
                      <form action="{{ route('job-category.destroy', $category->id) }}" method="POST" class="d-inline" id="delete-form-{{ $category->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="hitech-action-icon btn-label-danger" onclick="confirmDelete('{{ $category->id }}')" data-bs-toggle="tooltip" title="Delete">
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
      title: 'Delete Category?',
      text: "This category will be removed from all associated job postings.",
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
