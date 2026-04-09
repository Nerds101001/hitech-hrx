@extends('layouts.layoutMaster')

@section('title', 'Manage Interview Schedules')

@section('vendor-style')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/quill/editor.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
  <style>
    .schedule-list-item {
        border-radius: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    .schedule-list-item:hover {
        transform: translateY(-3px);
        background: #fff;
        border-color: var(--hitech-primary);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    }
    .schedule-list-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--hitech-primary);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .schedule-list-item:hover::before { opacity: 1; }
    
    #calendar {
        font-family: inherit;
    }
    .fc .fc-toolbar-title { font-weight: 700; color: #1e293b; }
    .fc .fc-button-primary { 
        background-color: #f1f5f9; 
        border-color: #e2e8f0; 
        color: #64748b; 
        font-weight: 600;
        text-transform: capitalize;
    }
    .fc .fc-button-primary:hover { background-color: #e2e8f0; border-color: #cbd5e1; color: #334155; }
    .fc .fc-button-active { background-color: var(--hitech-primary) !important; border-color: var(--hitech-primary) !important; color: #fff !important; }
    
    .schedule-count-badge {
        background: rgba(0, 128, 128, 0.1);
        color: var(--hitech-primary);
        font-weight: 700;
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
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
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/moment/moment.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js'
  ])
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    
    let calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      headerToolbar: {
        start: 'prev,next today',
        center: 'title',
        end: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      events: {
        url: "{{ route('interview-schedule.data') }}",
        method: 'GET'
      },
      eventClick: function(info) {
        info.jsEvent.preventDefault();
        window.location.href = info.event.url;
      },
      height: 'auto'
    });

    calendar.render();
  });
</script>
@endsection

@section('content')
<div class="animate__animated animate__fadeIn">
  <div class="d-flex justify-content-between align-items-center mb-6 px-4">
    <div>
        <h4 class="fw-bold mb-1 text-heading" style="font-size: 1.5rem;">Interview Calendar</h4>
        <p class="text-muted small mb-0">Manage screening calls and interview schedules.</p>
    </div>
    @can('Create Interview Schedule')
      <a href="#" data-url="{{ route('interview-schedule.create') }}" data-ajax-popup="true" data-size="md" data-title="Create New Interview Schedule" class="btn-hitech shadow-sm rounded-pill px-5">
        <i class="bx bx-plus-circle me-1"></i> Add Interview
      </a>
    @endcan
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="hitech-card-white h-100 border-0 shadow-sm overflow-hidden">
        <div class="card-body p-4">
          <div id="calendar"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="hitech-card-white h-100 border-0 shadow-sm overflow-hidden d-flex flex-column">
        <div class="card-header d-flex justify-content-between align-items-center border-bottom p-4 bg-transparent">
          <h5 class="mb-0 fw-bold">Active Schedule</h5>
          <span class="schedule-count-badge small">{{ count($current_month_event) }} Total</span>
        </div>
        <div class="card-body p-4 flex-grow-1 overflow-auto" style="max-height: 650px;">
          @forelse($current_month_event as $schedule)
            <div class="schedule-list-item p-4 mb-3">
              <div class="d-flex justify-content-between align-items-start">
                <div class="pe-3">
                  <h6 class="mb-1 fw-bold text-dark">{{ $schedule->applications->jobs->title ?? 'N/A' }}</h6>
                  <div class="d-flex align-items-center">
                      <span class="text-muted small fw-medium">{{ $schedule->applications->name ?? 'Candidate' }}</span>
                  </div>
                </div>
                <div class="dropdown">
                  <button class="btn btn-icon btn-sm rounded-circle" type="button" data-bs-toggle="dropdown">
                    <i class="bx bx-dots-vertical-rounded"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2">
                    @can('Edit Interview Schedule')
                      <a class="dropdown-item rounded-3 d-flex align-items-center mb-1" href="#" data-url="{{ route('interview-schedule.edit', $schedule->id) }}" data-ajax-popup="true" data-size="md" data-title="Edit Schedule">
                        <i class="bx bx-edit-alt me-2 text-warning fs-5"></i> 
                        <span class="fw-medium">Modify</span>
                      </a>
                    @endcan
                    @can('Delete Interview Schedule')
                      <form action="{{ route('interview-schedule.destroy', $schedule->id) }}" method="POST" id="delete-form-{{ $schedule->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="dropdown-item rounded-3 d-flex align-items-center text-danger" onclick="confirmDelete('{{ $schedule->id }}')">
                          <i class="bx bx-trash me-2 fs-5"></i> 
                          <span class="fw-medium">Cancel Interview</span>
                        </button>
                      </form>
                    @endcan
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-center mt-3 pt-3 border-top border-light">
                <div class="badge bg-label-secondary rounded-pill me-2 px-3 small">
                    <i class="bx bx-calendar me-1"></i>{{ auth()->user()->dateFormat($schedule->date) }}
                </div>
                <div class="badge bg-label-info rounded-pill px-3 small">
                    <i class="bx bx-time-five me-1"></i>{{ auth()->user()->timeFormat($schedule->time) }}
                </div>
              </div>
              @if($schedule->comment)
                <div class="mt-3 p-3 bg-white rounded-3 border border-light">
                  <p class="mb-0 small text-muted italic" style="font-size: 0.75rem;">“{{ \Illuminate\Support\Str::limit($schedule->comment, 80) }}”</p>
                </div>
              @endif
            </div>
          @empty
            <div class="text-center py-10 opacity-50">
                <i class="bx bx-calendar-x display-4 text-muted mb-3"></i>
                <h6 class="text-muted fw-bold">Clean Slate</h6>
                <p class="small text-muted mb-0">No interviews this month.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>
    </div>
</div>

<script>
  function confirmDelete(id) {
    Swal.fire({
      title: 'Cancel Interview?',
      text: "This will remove the interview from the calendar and notify the candidate.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, cancel it',
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
