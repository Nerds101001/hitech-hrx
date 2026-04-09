@extends('layouts.layoutMaster')

@section('title', 'Manage Job Stages')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/sortablejs/sortablejs.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .stage-item {
        cursor: grab;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
    }
    .stage-item:active { cursor: grabbing; }
    .stage-item:hover {
        transform: translateX(8px);
        border-color: var(--hitech-primary);
        background: #f8fafc;
        box-shadow: -5px 5px 20px rgba(0,0,0,0.02);
    }
    .ghost {
        opacity: 0.4;
        background: rgba(0, 128, 128, 0.05) !important;
        border: 2px dashed var(--hitech-primary) !important;
    }
    .handle-icon {
        color: #94a3b8;
        padding: 8px;
        border-radius: 8px;
        transition: color 0.3s;
    }
    .stage-item:hover .handle-icon { color: var(--hitech-primary); }
    
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
    'resources/assets/vendor/libs/sortablejs/sortablejs.js'
  ])
@endsection

@section('page-script')
<script>
  $(document).ready(function() {
    const el = document.getElementById('sortable-stages');
    if (el) {
      Sortable.create(el, {
        animation: 150,
        ghostClass: 'ghost',
        handle: '.handle',
        onEnd: function() {
          let order = [];
          $('.stage-item').each(function() {
            order.push($(this).data('id'));
          });

          $.ajax({
            url: "{{ route('job.stage.order') }}",
            type: 'POST',
            data: {
              order: order,
              _token: '{{ csrf_token() }}'
            },
            success: function(data) {},
            error: function(data) {
              alert('Error updating order');
            }
          });
        }
      });
    }
  });
</script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
      <h4 class="fw-bold mb-1 text-heading" style="font-size: 1.5rem;">Recruitment Workflow</h4>
      <p class="text-muted small mb-0">Drag and drop to reorder the pipeline stages.</p>
    </div>
    @can('Create Job Stage')
      <a href="#" data-url="{{ route('job-stage.create') }}" data-ajax-popup="true" data-size="md" data-title="Create New Job Stage" class="btn-hitech shadow-sm rounded-pill px-5">
        <i class="bx bx-plus-circle me-1"></i> Add Stage
      </a>
    @endcan
  </div>

  <div class="hitech-card-white border-0 shadow-sm overflow-hidden mb-4">
    <div class="card-body p-4">
      <div id="sortable-stages" class="row g-3">
        @forelse ($stages as $stage)
          <div class="col-12 stage-item p-4 mb-2 d-flex align-items-center justify-content-between" data-id="{{ $stage->id }}">
            <div class="d-flex align-items-center">
              <div class="handle me-4">
                  <i class="bx bx-grid-vertical handle-icon fs-3"></i>
              </div>
              <div>
                <h6 class="mb-0 fw-bold text-dark">{{ $stage->title }}</h6>
                <span class="text-muted x-small" style="font-size: 0.7rem;">STAGE #{{ $loop->iteration }}</span>
              </div>
            </div>
            <div class="d-flex gap-2">
              @can('Edit Job Stage')
                <a href="#" data-url="{{ route('job-stage.edit', $stage->id) }}" data-ajax-popup="true" data-title="Edit Job Stage" class="hitech-action-icon btn-label-info" data-bs-toggle="tooltip" title="Edit Stage">
                  <i class="bx bx-edit-alt fs-5"></i>
                </a>
              @endcan

              @can('Delete Job Stage')
                <form action="{{ route('job-stage.destroy', $stage->id) }}" method="POST" class="d-inline" id="delete-form-{{ $stage->id }}">
                  @csrf
                  @method('DELETE')
                  <button type="button" class="hitech-action-icon btn-label-danger" onclick="confirmDelete('{{ $stage->id }}')" data-bs-toggle="tooltip" title="Remove Stage">
                    <i class="bx bx-trash fs-5"></i>
                  </button>
                </form>
              @endcan
            </div>
          </div>
        @empty
          <div class="col-12 text-center py-10 opacity-50">
            <i class="bx bx-category display-4 text-muted mb-3"></i>
            <h6 class="text-muted fw-bold">Empty Pipeline</h6>
            <p class="small text-muted">No recruitment stages found. Please create one to begin.</p>
          </div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="alert alert-soft-teal border-0 rounded-4 p-4 d-flex align-items-center shadow-sm" style="background: rgba(0, 128, 128, 0.05);">
    <div class="avatar avatar-sm bg-teal-emerald me-3 rounded-3 flex-shrink-0">
        <i class="bx bx-info-circle text-white fs-4"></i>
    </div>
    <div class="flex-grow-1">
        <p class="mb-0 text-dark small fw-medium">
            <strong>System Sync:</strong> Changes to stage order are saved instantly and will automatically reflect in the Candidate Kanban board layout.
        </p>
    </div>
  </div>
</div>

<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Delete Stage?',
      text: "This will remove the stage from the recruitment pipeline. Candidates in this stage will be redirected.",
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
