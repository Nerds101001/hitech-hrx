@php $configData = Helper::appClasses(); @endphp
@extends('layouts/contentNavbarLayout')

@section('title', 'Task Management')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/select2/select2.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss',
  ])
@endsection

@section('vendor-script')
  {{-- select2.js, NOT select2.full.min.js — that build's UMD wrapper still
       has an unresolved require("jquery") in its compiled chunk, so
       .select2() never actually attaches to window.$ and every call to it
       throws "$(...).select2 is not a function". select2.js explicitly
       imports the shared jquery chunk instead — the Employee Directory page
       uses this exact file and its select2 works correctly. --}}
  @vite([
    'resources/assets/vendor/libs/select2/select2.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
    'resources/assets/vendor/libs/flatpickr/flatpickr.js',
  ])
@endsection

@section('page-style')
<style>
  /* A checkbox in front of each name/department, ticked once picked. select2
     marks a chosen option aria-selected="true" on its own — this only draws
     the box, it does not track state itself, so it can never drift out of
     sync with what is actually selected. */
  .select2-results__option {
    position: relative;
    padding-left: 2.25rem !important;
  }
  .select2-results__option::before {
    content: '';
    position: absolute;
    left: .75rem;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border: 1px solid #94a3b8;
    border-radius: 4px;
    background: #fff;
  }
  .select2-results__option[aria-selected="true"]::before {
    background: #005a5a;
    border-color: #005a5a;
  }
  .select2-results__option[aria-selected="true"]::after {
    content: '\2713';
    position: absolute;
    left: .95rem;
    top: 50%;
    transform: translateY(-50%);
    color: #fff;
    font-size: .7rem;
    font-weight: 700;
    line-height: 1;
  }

  /* hitech-portal.scss puts `overflow: hidden !important` on every
     .modal-content, which on a short mobile viewport can win the fight
     against Bootstrap's own .modal-dialog-scrollable sizing for a form
     this tall (title/description/priority/interval/due-date/two select2
     pickers). Force the body's own scroll explicitly rather than relying
     on that cascade. */
  @media (max-width: 768px) {
    #assignTaskModal .modal-dialog {
      max-height: 100dvh;
      margin: 0;
    }
    #assignTaskModal .modal-content {
      max-height: 100dvh;
    }
    #assignTaskModal .modal-body {
      overflow-y: auto !important;
      max-height: calc(100dvh - 130px); /* header + footer are ~65px each */
      -webkit-overflow-scrolling: touch;
    }

    /* .btn-hitech is forced to width:100% site-wide on mobile (see the
       global @media block in hitech-portal.scss), which stacks Assign
       above Cancel since Assign alone fills the row. Put both back on
       one row here without touching that global rule. */
    #assignTaskModal .modal-footer {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: .75rem;
    }
    #assignTaskModal .modal-footer .btn-hitech,
    #assignTaskModal .modal-footer .btn-hitech-cancel {
      width: 100%;
      margin: 0;
    }
  }

  /* The filter card's .card-body padding comes from a global, !important
     rule (.hitech-card-white .card-body) meant for wide showcase cards -
     appropriate elsewhere, too generous for a plain filter row. Overridden
     here by id, not by editing that shared rule. */
  #taskFilterCard .card-body {
    padding: 1.25rem 1.5rem !important;
  }

  #taskTable td, #taskTable th {
    padding: 0.65rem 0.85rem !important;
  }

  /* "New" needs its own theme-based tint, distinct from badge-brand-soft
     (already In Progress) and badge-muted (this app's "past/inactive"
     grey, wrong connotation for a task that just landed) - reuses the same
     view-icon blue as everywhere else "new/informational" appears. */
  .badge-status-new {
    background: rgba(77, 108, 240, 0.08);
    color: var(--hitech-icon-view, #4d6cf0);
    border: 1px solid rgba(77, 108, 240, 0.18);
  }

  /* Same shape as this theme's .icon-sophisticated everywhere else (slate
     at rest, lifting to its own colour on hover) rather than the flat grey
     Bootstrap default these action buttons were rendering as. */
  .tm-act {
    --tm-act-accent: var(--hitech-primary, #008080);
    background: var(--hitech-bg-soft, #f8fafc);
    color: #64748b;
    border: 1px solid var(--hitech-border, #dee2e6);
  }
  .tm-act:hover {
    background: #fff;
    color: var(--tm-act-accent);
    border-color: var(--tm-act-accent);
  }
  .tm-act-view    { --tm-act-accent: var(--hitech-icon-view, #4d6cf0); }
  .tm-act-edit    { --tm-act-accent: var(--hitech-icon-edit, #f5a623); }
  .tm-act-warning { --tm-act-accent: #f59e0b; }
  .tm-act-danger  { --tm-act-accent: var(--hitech-icon-deactivate, #ef4444); }
</style>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">Task Management</h3>
      <p class="text-muted mb-0 small">
        Assign work to a person, a hand-picked group, or a whole department at once.
        Each person only ever sees and completes their own task.
      </p>
    </div>
    <div class="d-flex gap-3 flex-wrap">
      <button type="button" id="btnAssignTask" class="btn-hitech shadow-sm rounded-pill px-5 d-flex align-items-center gap-2">
        <i class="bx bx-plus-circle fs-5"></i><span class="fw-bold">Assign Task</span>
      </button>
    </div>
  </div>

  {{-- Stats bar --}}
  <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-6 mb-6 mx-0 px-4">
    <div class="col animate__animated animate__fadeInUp" style="animation-delay:.05s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-list-ul"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $stats['total'] }}</h3><span class="stat-label">Total tasks (your scope)</span></div>
      </div>
    </div>
    <div class="col animate__animated animate__fadeInUp" style="animation-delay:.1s">
      <div class="hitech-stat-card dashboard-variant card-teal uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $stats['pending'] }}</h3><span class="stat-label">Pending / in progress</span></div>
      </div>
    </div>
    <div class="col animate__animated animate__fadeInUp" style="animation-delay:.13s">
      <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-calendar-check"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $stats['due_today'] }}</h3><span class="stat-label">Due today</span></div>
      </div>
    </div>
    <div class="col animate__animated animate__fadeInUp" style="animation-delay:.15s">
      <div class="hitech-stat-card dashboard-variant card-red uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-error-circle"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $stats['overdue'] }}</h3><span class="stat-label">Overdue</span></div>
      </div>
    </div>
    <div class="col animate__animated animate__fadeInUp" style="animation-delay:.2s">
      <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
        <div class="stat-card-header mb-2">
          <div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-check-circle"></i></div>
        </div>
        <div><h3 class="stat-value mb-0">{{ $stats['completed'] }}</h3><span class="stat-label">Completed</span></div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="px-4">
    <div class="hitech-card-white mb-6 overflow-hidden" id="taskFilterCard">
      <div class="card-body p-sm-5 p-4">
        <form method="GET" class="row align-items-end g-4">
          <div class="col-lg-3 col-md-6">
            <div class="search-wrapper-hitech mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" name="search" class="form-control" placeholder="Search title or person..." value="{{ request('search') }}">
              <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
            <select class="form-select form-select-hitech" name="department_id" onchange="this.form.submit()">
              <option value="">All Departments</option>
              @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ (string) request('department_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
            <select class="form-select form-select-hitech" name="status" onchange="this.form.submit()">
              <option value="">All statuses</option>
              <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
              <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In progress</option>
              <option value="due_today" {{ request('status') === 'due_today' ? 'selected' : '' }}>Due today</option>
              <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
              <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Priority</label>
            <select class="form-select form-select-hitech" name="priority" onchange="this.form.submit()">
              <option value="">All priorities</option>
              @foreach(\App\Models\Task::PRIORITIES as $p)
                <option value="{{ $p }}" {{ request('priority') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Per Page</label>
            <select class="form-select form-select-hitech" name="per_page" onchange="this.form.submit()">
              @foreach(['10' => '10 per page', '20' => '20 per page', '50' => '50 per page', '1000' => '1000 per page', 'all' => 'All'] as $val => $label)
                <option value="{{ $val }}" {{ (string) request('per_page', '20') === $val ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-lg-1 col-md-6">
            <a href="{{ route('task-management.index') }}" class="btn btn-hitech-cancel w-100">Clear</a>
          </div>
        </form>
      </div>
    </div>

    {{-- Recurring Tasks --}}
    @if($schedules->isNotEmpty())
      <div class="hitech-card mb-6">
        <div class="card-body p-4 pb-2">
          <h6 class="fw-bold mb-0"><i class="bx bx-repeat"></i> Recurring Tasks</h6>
          <p class="text-muted small mb-0">Fires automatically every time its day comes round — no need to reassign it yourself.</p>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:200px">Task</th>
                <th>Repeats</th>
                <th>Priority</th>
                <th>People</th>
                <th class="text-end" style="min-width:160px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($schedules as $s)
                <tr>
                  <td>
                    <div class="fw-medium">{{ $s->title }}</div>
                    @if($s->description)<small class="text-muted">{{ \Illuminate\Support\Str::limit($s->description, 70) }}</small>@endif
                  </td>
                  <td><span class="badge-hitech badge-brand-soft"><i class="bx bx-repeat"></i> {{ $s->recurrenceLabel() }}</span></td>
                  <td><span class="badge-hitech {{ ['low' => 'badge-muted', 'medium' => 'badge-brand-soft', 'high' => 'badge-hitech-danger'][$s->priority] ?? 'badge-muted' }}">{{ ucfirst($s->priority) }}</span></td>
                  <td>{{ count($s->target_user_ids ?? []) }} {{ \Illuminate\Support\Str::plural('person', count($s->target_user_ids ?? [])) }}</td>
                  <td class="text-end">
                    <button type="button" class="btn btn-sm btn-hitech-cancel js-manage-schedule"
                            data-id="{{ $s->id }}" data-title="{{ $s->title }}" data-recurrence="{{ $s->recurrenceLabel() }}"
                            data-targets="{{ json_encode(collect($s->target_user_ids ?? [])->map(fn($uid) => ['id' => $uid, 'name' => optional($targetNames->get($uid))->getFullName() ?? 'Unknown'])->values()) }}">
                      Manage
                    </button>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    {{-- Bulk action bar - hidden until at least one row is checked --}}
    <div class="hitech-card-white mb-3 p-3 d-none align-items-center justify-content-between" id="bulkActionBar">
      <span class="fw-medium"><span id="bulkSelectedCount">0</span> selected</span>
      <button type="button" class="btn btn-sm btn-hitech-danger" id="btnBulkDelete">
        <i class="bx bx-trash"></i> Delete Selected
      </button>
    </div>

    {{-- Task list --}}
    <div class="hitech-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="taskTable">
          <thead class="table-light">
            <tr>
              <th style="width:40px"><input type="checkbox" class="form-check-input" id="checkAllTasks"></th>
              <th style="min-width:200px">Task</th>
              <th style="min-width:180px">Assigned To</th>
              <th>Priority</th>
              <th>Due</th>
              <th>Assigned By</th>
              <th>Status</th>
              <th class="text-end" style="min-width:160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            @php $seenBatches = []; @endphp
            @forelse($tasks as $t)
              @php
                $isBatched = $t->batch_id && ($batchCounts[$t->batch_id]->total ?? 1) > 1;
                // Only the first task of a batch renders a row at all - the
                // other N-1 are reachable by expanding it, not as separate
                // rows repeating the same title down the whole page (which is
                // what this list looked like before: the same bulk-assigned
                // task, once per person, for as many people as it was sent to).
                if ($isBatched) {
                    if (in_array($t->batch_id, $seenBatches, true)) {
                        continue;
                    }
                    $seenBatches[] = $t->batch_id;
                }
              @endphp
              <tr @if($isBatched) data-batch-summary="{{ $t->batch_id }}" @endif>
                <td><input type="checkbox" class="form-check-input js-task-check" value="{{ $t->id }}"></td>
                <td>
                  <div class="d-flex align-items-start gap-2">
                    @if($isBatched)
                      <button type="button" class="btn btn-sm btn-icon icon-sophisticated js-batch-toggle flex-shrink-0"
                              data-batch-id="{{ $t->batch_id }}" title="Show all {{ $batchCounts[$t->batch_id]->total }} people">
                        <i class="bx bx-chevron-right"></i>
                      </button>
                    @endif
                    <div>
                      <div class="fw-medium">{{ $t->title }}</div>
                      @if($isBatched)
                        <small class="text-muted">
                          <i class="bx bx-group"></i> {{ $batchCounts[$t->batch_id]->total }} people
                          &middot; {{ (int) $batchCounts[$t->batch_id]->done }}/{{ $batchCounts[$t->batch_id]->total }} done
                        </small>
                      @endif
                    </div>
                  </div>
                </td>
                <td>
                  @if($isBatched)
                    <div class="fw-medium">{{ $batchCounts[$t->batch_id]->total }} people</div>
                    <small class="text-muted">click <i class="bx bx-chevron-right"></i> to see everyone</small>
                  @else
                    <div class="fw-medium">{{ optional($t->user)->getFullName() ?? '—' }}</div>
                    <small class="text-muted">{{ optional(optional($t->user)->department)->name ?? '—' }}</small>
                  @endif
                </td>
                <td>
                  <span class="badge-hitech {{ ['low' => 'badge-muted', 'medium' => 'badge-brand-soft', 'high' => 'badge-hitech-danger'][$t->priority] ?? 'badge-muted' }}">{{ ucfirst($t->priority) }}</span>
                </td>
                <td class="text-nowrap">
                  {{ $t->due_date ? $t->due_date->format('d/m/Y' . ($t->due_date->format('H:i') !== '00:00' ? ' h:i A' : '')) : '—' }}
                  @if($t->is_overdue)
                    <span class="badge-hitech badge-hitech-danger d-block mt-1" style="width:fit-content;">Overdue</span>
                  @elseif($t->due_date && $t->due_date->isToday() && !$t->is_completed)
                    <span class="badge-hitech badge-brand-soft d-block mt-1" style="width:fit-content;">Due today</span>
                  @endif
                </td>
                <td><small class="text-muted">{{ optional($t->assignedBy)->getFullName() ?? '—' }}</small></td>
                <td>
                  @if($isBatched)
                    <span class="badge-hitech {{ $batchCounts[$t->batch_id]->done == $batchCounts[$t->batch_id]->total ? 'badge-hitech-success' : 'badge-brand-soft' }}">
                      {{ (int) $batchCounts[$t->batch_id]->done }}/{{ $batchCounts[$t->batch_id]->total }} done
                    </span>
                  @elseif($t->status === 'objection')
                    <span class="badge-hitech badge-hitech-danger">Objection Raised</span>
                  @elseif($t->is_completed)
                    <span class="badge-hitech badge-hitech-success">{{ ucfirst(str_replace('_',' ', $t->status)) }}</span>
                  @elseif($t->status === 'in_progress')
                    <span class="badge-hitech badge-brand-soft">In progress</span>
                  @else
                    <span class="badge-hitech badge-status-new">New</span>
                  @endif
                </td>
                <td class="text-end text-nowrap">
                  <button type="button" class="btn btn-sm btn-icon icon-sophisticated tm-act tm-act-view js-view-task" title="View"
                          data-task="{{ json_encode([
                            'title' => $t->title,
                            'description' => $t->description,
                            'priority' => ucfirst($t->priority),
                            'due' => $t->due_date ? $t->due_date->format('d/m/Y h:i A') : '—',
                            'assignee' => optional($t->user)->getFullName() ?? '—',
                            'assignedBy' => optional($t->assignedBy)->getFullName() ?? '—',
                            'status' => ucfirst(str_replace('_', ' ', $t->status)),
                            'objection' => $t->objection_notes,
                            'attachment' => $t->attachment_path ? route('tasks.attachment', $t->id) : null,
                            'updateAttachment' => optional($t->taskUpdates->first())->file_url ? route('task-updates.attachment', $t->taskUpdates->first()->id) : null,
                          ]) }}">
                    <i class="bx bx-show"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-icon icon-sophisticated tm-act tm-act-edit js-edit-task" title="Edit"
                          data-id="{{ $t->id }}" data-title="{{ $t->title }}" data-description="{{ $t->description }}"
                          data-priority="{{ $t->priority }}" data-due="{{ $t->due_date?->format('Y-m-d H:i') }}"
                          data-user-id="{{ $t->user_id }}" data-user-name="{{ optional($t->user)->getFullName() ?? 'Unknown' }}">
                    <i class="bx bx-edit"></i>
                  </button>
                  @if(in_array($t->status, ['completed', 'resolved']))
                    <button type="button" class="btn btn-sm btn-icon icon-sophisticated tm-act tm-act-warning js-object-task" title="Raise Objection"
                            data-id="{{ $t->id }}" data-title="{{ $t->title }}">
                      <i class="bx bx-error"></i>
                    </button>
                  @endif
                  <button class="btn btn-sm btn-icon icon-sophisticated tm-act tm-act-danger js-delete-task" data-id="{{ $t->id }}" title="Delete">
                    <i class="bx bx-trash"></i>
                  </button>
                </td>
              </tr>
              @if($isBatched)
                <tr class="d-none batch-children-row" data-batch-children="{{ $t->batch_id }}">
                  <td></td>
                  <td colspan="7" class="p-0">
                    <div class="bg-light p-3">
                      <table class="table table-sm table-hover align-middle mb-0 bg-white">
                        <thead>
                          <tr>
                            <th style="min-width:200px">Person</th>
                            <th>Status</th>
                            <th>Completed</th>
                            <th style="min-width:220px">Latest remark</th>
                          </tr>
                        </thead>
                        <tbody class="js-batch-children-body">
                          {{-- filled by AJAX on first expand --}}
                        </tbody>
                      </table>
                      <a href="{{ route('task-management.batch', $t->batch_id) }}" class="small text-decoration-none d-inline-block mt-2">
                        <i class="bx bx-link-external"></i> Open full batch page
                      </a>
                    </div>
                  </td>
                </tr>
              @endif
            @empty
              <tr><td colspan="8" class="text-center text-muted py-5">
                <i class="bx bx-task fs-3 d-block mb-2 text-success"></i>
                No tasks match these filters.
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
  </div>
</div>

{{-- Assign Task modal --}}
<div class="modal fade" id="assignTaskModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form id="assignTaskForm">
        <div class="modal-header">
          <h5 class="modal-title">Assign Task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" id="at_title" class="form-control" required placeholder="e.g. Submit Q3 expense reports">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea id="at_description" class="form-control" rows="3" placeholder="Details, instructions, links..."></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Priority</label>
              <select id="at_priority" class="form-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Interval</label>
              <select id="at_interval" class="form-select">
                <option value="">One-time</option>
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
              </select>
              <div class="form-text">Sets the due date for you. Pick "One-time" to set it yourself.</div>
            </div>
            <div class="col-md-4 d-none" id="at_weekday_wrap">
              <label class="form-label">Which day of the week</label>
              <select id="at_weekday" class="form-select">
                <option value="1">Monday</option>
                <option value="2">Tuesday</option>
                <option value="3">Wednesday</option>
                <option value="4">Thursday</option>
                <option value="5">Friday</option>
                <option value="6">Saturday</option>
                <option value="0">Sunday</option>
              </select>
            </div>
            <div class="col-md-4 d-none" id="at_monthday_wrap">
              <label class="form-label">Which date of the month</label>
              <input type="number" id="at_monthday" class="form-control" min="1" max="31" value="1">
              <div class="form-text">31 falls back to the last day in a shorter month.</div>
            </div>
            <div class="col-md-4" id="at_due_date_wrap">
              <label class="form-label">Due date &amp; time</label>
              <input type="text" id="at_due_date" class="form-control" placeholder="dd/mm/yyyy --:-- --">
              <input type="text" id="at_recurrence_display" class="form-control d-none" readonly>
              <div class="form-text d-none" id="at_recurrence_help">Repeats on its own — a task appears for each person every time this day comes round. Stop it any time from the Recurring Tasks list below.</div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-6">
              <label class="form-label">Assign to employees</label>
              <select id="at_employees" class="form-select select2" multiple>
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ trim($e->first_name . ' ' . $e->last_name) }}</option>
                @endforeach
              </select>
              <div class="form-text">Pick specific people, or leave blank and use departments below.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Assign to departments</label>
              <select id="at_departments" class="form-select select2" multiple>
                @foreach($departments as $d)
                  <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
              </select>
              <div class="form-text">Every active person in the picked department(s) gets their own copy of this task.</div>
            </div>

            <div class="col-12">
              <label class="form-label">Attach an image (optional)</label>
              <input type="file" id="at_attachment" class="form-control" accept="image/png,image/jpeg,image/webp">
              <div class="form-text">Reference photo for what's expected — shown to everyone this task is assigned to. JPG/PNG/WEBP, up to 5MB.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-hitech">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Edit Task modal --}}
<div class="modal fade" id="editTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="editTaskForm">
        <input type="hidden" id="et_id">
        <div class="modal-header">
          <h5 class="modal-title">Edit Task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" id="et_title" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea id="et_description" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Priority</label>
              <select id="et_priority" class="form-select">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Due date &amp; time</label>
              <input type="text" id="et_due_date" class="form-control" placeholder="dd/mm/yyyy --:-- --">
            </div>
            <div class="col-12">
              <label class="form-label">Assigned to</label>
              <select id="et_assignee" class="form-select select2-single">
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ trim($e->first_name . ' ' . $e->last_name) }}</option>
                @endforeach
              </select>
              <div class="form-text">Reassigns this one task — only to someone on your own team.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-hitech">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- View Task modal (read-only) --}}
<div class="modal fade" id="viewTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Task Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <dl class="row mb-0">
          <dt class="col-4">Title</dt><dd class="col-8" id="vt_title"></dd>
          <dt class="col-4">Description</dt><dd class="col-8" id="vt_description"></dd>
          <dt class="col-4">Priority</dt><dd class="col-8" id="vt_priority"></dd>
          <dt class="col-4">Due</dt><dd class="col-8" id="vt_due"></dd>
          <dt class="col-4">Assigned To</dt><dd class="col-8" id="vt_assignee"></dd>
          <dt class="col-4">Assigned By</dt><dd class="col-8" id="vt_assignedBy"></dd>
          <dt class="col-4">Status</dt><dd class="col-8" id="vt_status"></dd>
          <dt class="col-4 d-none" id="vt_objection_label">Objection</dt><dd class="col-8 d-none" id="vt_objection"></dd>
        </dl>
        <div class="d-none mt-3" id="vt_attachment_wrap">
          <div class="fw-medium small text-muted mb-1">Attachment</div>
          <a id="vt_attachment_link" href="#" target="_blank"><img id="vt_attachment_img" src="" alt="Attachment" class="rounded" style="max-width:100%;max-height:220px;border:1px solid #e2e8f0;"></a>
        </div>
        <div class="d-none mt-3" id="vt_update_attachment_wrap">
          <div class="fw-medium small text-muted mb-1">Completion / objection photo</div>
          <a id="vt_update_attachment_link" href="#" target="_blank"><img id="vt_update_attachment_img" src="" alt="Attachment" class="rounded" style="max-width:100%;max-height:220px;border:1px solid #e2e8f0;"></a>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

{{-- Raise Objection modal --}}
<div class="modal fade" id="objectTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="objectTaskForm">
        <input type="hidden" id="ot_id">
        <div class="modal-header">
          <h5 class="modal-title">Raise Objection: <span id="ot_task_title"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">Reason <span class="text-danger">*</span></label>
          <textarea id="ot_reason" class="form-control" rows="4" required placeholder="What needs to be corrected before this can be accepted?"></textarea>
          <div class="form-text mb-3">This sends the task back to the assignee as "Objection Raised" — they must fix it and resubmit.</div>
          <label class="form-label">Attach an image (optional)</label>
          <input type="file" id="ot_attachment" class="form-control" accept="image/png,image/jpeg,image/webp">
          <div class="form-text">e.g. a photo of what's wrong. JPG/PNG/WEBP, up to 5MB.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-hitech-danger">Raise Objection</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Manage Recurring Task modal --}}
<div class="modal fade" id="manageScheduleModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="msTitle">Manage Recurring Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3" id="msRecurrence"></p>
        <ul class="list-group" id="msTargetList"></ul>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger btn-sm" id="msStopBtn">
          <i class="bx bx-stop-circle"></i> Stop This Recurring Task
        </button>
        <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // No theme option: this project styles select2's own default markup
  // (see select2.scss) rather than the separate "bootstrap-5" theme, which
  // isn't installed here — passing it left the dropdown completely unstyled.
  $('.select2').select2({
    width: '100%',
    dropdownParent: $('#assignTaskModal'),
    minimumResultsForSearch: 0, // always show the search box, however short the list
    closeOnSelect: false,       // stay open so ticking several people/departments is one pass, not one re-open per pick
  });

  // Edit Task's "Assigned to" — a plain single-select living in a different
  // modal, so it needs its own dropdownParent (the shared .select2 init
  // above is hardcoded to assignTaskModal) and normal close-on-select.
  $('.select2-single').select2({
    width: '100%',
    dropdownParent: $('#editTaskModal'),
    minimumResultsForSearch: 0,
  });

  const modalEl = document.getElementById('assignTaskModal');
  const modal = new bootstrap.Modal(modalEl);

  // Picking Weekly/Monthly turns this into a recurring schedule on the
  // server (see storeSchedule() in the controller) rather than a single
  // task with a computed due date — the due-date box swaps for a read-only
  // recurrence label so it is obvious this is now "every Tuesday", not one
  // specific date.
  const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  function ordinal(n) {
    if (n % 100 >= 11 && n % 100 <= 13) return n + 'th';
    return n + ({1: 'st', 2: 'nd', 3: 'rd'}[n % 10] || 'th');
  }
  function recurrenceLabel(interval) {
    if (interval === 'daily') return 'Every day';
    if (interval === 'weekly') return 'Every ' + WEEKDAY_NAMES[parseInt(document.getElementById('at_weekday').value, 10)];
    if (interval === 'monthly') return 'Every ' + ordinal(parseInt(document.getElementById('at_monthday').value, 10) || 1) + ' of the month';
    return '';
  }

  // altInput shows dd/mm/yyyy to the user; the original #at_due_date stays
  // in the DOM as a hidden input holding the real Y-m-d H:i value the
  // submit handler below reads and the server expects. Toggling a d-none
  // class on #at_due_date itself no longer hides anything visible once
  // flatpickr owns it — the altInput is the thing on screen — so
  // refreshInterval() below reaches the altInput through the instance.
  const atDueDatePicker = flatpickr('#at_due_date', {
    enableTime: true,
    time_24hr: false,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'd/m/Y h:i K',
    minDate: 'today',
  });

  function refreshInterval() {
    const interval = document.getElementById('at_interval').value;
    const isRecurring = interval !== '';
    document.getElementById('at_weekday_wrap').classList.toggle('d-none', interval !== 'weekly');
    document.getElementById('at_monthday_wrap').classList.toggle('d-none', interval !== 'monthly');
    if (atDueDatePicker.altInput) atDueDatePicker.altInput.classList.toggle('d-none', isRecurring);
    document.getElementById('at_recurrence_display').classList.toggle('d-none', !isRecurring);
    document.getElementById('at_recurrence_help').classList.toggle('d-none', !isRecurring);
    if (isRecurring) {
      document.getElementById('at_recurrence_display').value = recurrenceLabel(interval);
    } else {
      atDueDatePicker.clear();
    }
  }

  document.getElementById('at_interval').addEventListener('change', refreshInterval);
  document.getElementById('at_weekday').addEventListener('change', refreshInterval);
  document.getElementById('at_monthday').addEventListener('change', refreshInterval);

  document.getElementById('btnAssignTask').addEventListener('click', function () {
    document.getElementById('assignTaskForm').reset();
    atDueDatePicker.clear();
    refreshInterval();
    $('#at_employees, #at_departments').val(null).trigger('change');
    modal.show();
  });

  document.getElementById('assignTaskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Assigning...';

    const fd = new FormData();
    fd.append('title', document.getElementById('at_title').value);
    fd.append('description', document.getElementById('at_description').value);
    if (document.getElementById('at_due_date').value) fd.append('due_date', document.getElementById('at_due_date').value);
    fd.append('priority', document.getElementById('at_priority').value);
    if (document.getElementById('at_interval').value) fd.append('interval', document.getElementById('at_interval').value);
    fd.append('weekday', document.getElementById('at_weekday').value);
    fd.append('day_of_month', document.getElementById('at_monthday').value);
    (($('#at_employees').val()) || []).forEach(id => fd.append('employee_ids[]', id));
    (($('#at_departments').val()) || []).forEach(id => fd.append('department_ids[]', id));
    const atFile = document.getElementById('at_attachment').files[0];
    if (atFile) fd.append('attachment', atFile);

    fetch("{{ route('task-management.store') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: fd,
    })
      .then(r => r.json().then(data => ({ ok: r.ok, data })))
      .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = original;
        if (ok && data.success) {
          Swal.fire({ icon: 'success', title: 'Task Assigned', text: data.message, timer: 1800, showConfirmButton: false })
            .then(() => window.location.reload());
        } else {
          Swal.fire({ icon: 'error', title: 'Could not assign', text: data.message || 'Something went wrong.' });
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
      });
  });

  document.querySelectorAll('.js-delete-task').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const id = btn.dataset.id;
      Swal.fire({
        icon: 'warning', title: 'Delete this task?', showCancelButton: true,
        confirmButtonText: 'Delete', confirmButtonColor: '#dc2626',
      }).then((res) => {
        if (!res.isConfirmed) return;
        fetch('/task-management/' + id, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
        })
          .then(r => r.json())
          .then(data => {
            if (data.success) { window.location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Could not delete', text: data.message || '' }); }
          });
      });
    });
  });

  // ══ Batch rows: expand a collapsed "N people" row in place ═══════════
  document.querySelectorAll('.js-batch-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const batchId = btn.dataset.batchId;
      const childRow = document.querySelector('.batch-children-row[data-batch-children="' + batchId + '"]');
      if (!childRow) return;
      const icon = btn.querySelector('i');
      const nowHidden = !childRow.classList.contains('d-none');

      if (nowHidden) {
        childRow.classList.add('d-none');
        icon.classList.remove('bx-chevron-down');
        icon.classList.add('bx-chevron-right');
        return;
      }

      childRow.classList.remove('d-none');
      icon.classList.remove('bx-chevron-right');
      icon.classList.add('bx-chevron-down');

      const body = childRow.querySelector('.js-batch-children-body');
      if (body.dataset.loaded) return;

      body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Loading...</td></tr>';
      fetch('/task-management/batch/' + batchId, {
        headers: { 'Accept': 'application/json' },
      })
        .then(r => r.json())
        .then(data => {
          body.innerHTML = data.html || '';
          body.dataset.loaded = '1';
        })
        .catch(() => {
          body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-3">Could not load. Try again.</td></tr>';
        });
    });
  });

  // ══ Bulk select / bulk delete ═════════════════════════════════════════
  const checkAll = document.getElementById('checkAllTasks');
  const rowChecks = document.querySelectorAll('.js-task-check');
  const bulkBar = document.getElementById('bulkActionBar');
  const bulkCount = document.getElementById('bulkSelectedCount');

  function refreshBulkBar() {
    const checked = document.querySelectorAll('.js-task-check:checked');
    bulkCount.textContent = checked.length;
    bulkBar.classList.toggle('d-none', checked.length === 0);
    bulkBar.classList.toggle('d-flex', checked.length > 0);
    if (checkAll) checkAll.checked = checked.length > 0 && checked.length === rowChecks.length;
  }

  if (checkAll) {
    checkAll.addEventListener('change', function () {
      rowChecks.forEach(function (c) { c.checked = checkAll.checked; });
      refreshBulkBar();
    });
  }
  rowChecks.forEach(function (c) { c.addEventListener('change', refreshBulkBar); });

  const bulkDeleteBtn = document.getElementById('btnBulkDelete');
  if (bulkDeleteBtn) {
    bulkDeleteBtn.addEventListener('click', function () {
      const ids = Array.from(document.querySelectorAll('.js-task-check:checked')).map(c => c.value);
      if (!ids.length) return;
      Swal.fire({
        icon: 'warning', title: 'Delete ' + ids.length + ' task(s)?', showCancelButton: true,
        confirmButtonText: 'Delete', confirmButtonColor: '#dc2626',
      }).then((res) => {
        if (!res.isConfirmed) return;
        fetch("{{ route('task-management.bulkDestroy') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}",
            'Accept': 'application/json',
          },
          body: JSON.stringify({ ids: ids }),
        })
          .then(r => r.json())
          .then(data => {
            if (data.success) { window.location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Nothing deleted', text: data.message || '' }); }
          });
      });
    });
  }

  // ══ View Task (read-only, populated from the row's own data attribute) ═
  const viewModalEl = document.getElementById('viewTaskModal');
  const viewModal = new bootstrap.Modal(viewModalEl);
  document.querySelectorAll('.js-view-task').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const t = JSON.parse(btn.dataset.task);
      document.getElementById('vt_title').textContent = t.title;
      document.getElementById('vt_description').textContent = t.description || '—';
      document.getElementById('vt_priority').textContent = t.priority;
      document.getElementById('vt_due').textContent = t.due;
      document.getElementById('vt_assignee').textContent = t.assignee;
      document.getElementById('vt_assignedBy').textContent = t.assignedBy;
      document.getElementById('vt_status').textContent = t.status;
      const hasObjection = !!t.objection;
      document.getElementById('vt_objection_label').classList.toggle('d-none', !hasObjection);
      document.getElementById('vt_objection').classList.toggle('d-none', !hasObjection);
      document.getElementById('vt_objection').textContent = t.objection || '';

      document.getElementById('vt_attachment_wrap').classList.toggle('d-none', !t.attachment);
      if (t.attachment) {
        document.getElementById('vt_attachment_link').href = t.attachment;
        document.getElementById('vt_attachment_img').src = t.attachment;
      }
      document.getElementById('vt_update_attachment_wrap').classList.toggle('d-none', !t.updateAttachment);
      if (t.updateAttachment) {
        document.getElementById('vt_update_attachment_link').href = t.updateAttachment;
        document.getElementById('vt_update_attachment_img').src = t.updateAttachment;
      }

      viewModal.show();
    });
  });

  // ══ Edit Task ════════════════════════════════════════════════════════
  const editModalEl = document.getElementById('editTaskModal');
  const editModal = new bootstrap.Modal(editModalEl);
  const etDueDatePicker = flatpickr('#et_due_date', {
    enableTime: true,
    time_24hr: false,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'd/m/Y h:i K',
  });

  let etOriginalUserId = null;
  document.querySelectorAll('.js-edit-task').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('et_id').value = btn.dataset.id;
      document.getElementById('et_title').value = btn.dataset.title;
      document.getElementById('et_description').value = btn.dataset.description || '';
      document.getElementById('et_priority').value = btn.dataset.priority;
      if (btn.dataset.due) { etDueDatePicker.setDate(btn.dataset.due, true); } else { etDueDatePicker.clear(); }

      etOriginalUserId = btn.dataset.userId;
      const assigneeSelect = document.getElementById('et_assignee');
      // Drop whatever was injected for the last task this modal was opened
      // for, or repeated edits of different out-of-scope assignees would
      // just keep piling up extra options underneath each other.
      assigneeSelect.querySelectorAll('option.js-et-outside-option').forEach(o => o.remove());
      // The current assignee might be outside this admin/manager's own team
      // (assigned before a reporting-line change, say) and so missing from
      // this list entirely -- without this, select2 would silently fall back
      // to whichever option happens to be first, which would reassign the
      // task to the wrong person the moment "Save" is clicked without anyone
      // touching the field.
      if (!assigneeSelect.querySelector('option[value="' + etOriginalUserId + '"]')) {
        const opt = document.createElement('option');
        opt.value = etOriginalUserId;
        opt.textContent = btn.dataset.userName + ' (outside your team)';
        opt.className = 'js-et-outside-option';
        assigneeSelect.prepend(opt);
      }
      $('#et_assignee').val(etOriginalUserId).trigger('change');

      editModal.show();
    });
  });

  document.getElementById('editTaskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    fetch('/task-management/' + document.getElementById('et_id').value, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        title: document.getElementById('et_title').value,
        description: document.getElementById('et_description').value,
        priority: document.getElementById('et_priority').value,
        due_date: document.getElementById('et_due_date').value || null,
        // Only sent when actually changed from what the row was opened
        // with -- never let a select2 quirk reassign a task nobody touched.
        user_id: (function () {
          const v = $('#et_assignee').val();
          return (v && v !== etOriginalUserId) ? v : null;
        })(),
      }),
    })
      .then(r => r.json().then(data => ({ ok: r.ok, data })))
      .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = original;
        if (ok && data.success) {
          Swal.fire({ icon: 'success', title: 'Saved', text: data.message, timer: 1500, showConfirmButton: false })
            .then(() => window.location.reload());
        } else {
          Swal.fire({ icon: 'error', title: 'Could not save', text: data.message || 'Something went wrong.' });
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
      });
  });

  // ══ Raise Objection ═════════════════════════════════════════════════
  const objectModalEl = document.getElementById('objectTaskModal');
  const objectModal = new bootstrap.Modal(objectModalEl);
  document.querySelectorAll('.js-object-task').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('ot_id').value = btn.dataset.id;
      document.getElementById('ot_task_title').textContent = btn.dataset.title;
      document.getElementById('ot_reason').value = '';
      document.getElementById('ot_attachment').value = '';
      objectModal.show();
    });
  });

  document.getElementById('objectTaskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Sending...';

    const otFd = new FormData();
    otFd.append('reason', document.getElementById('ot_reason').value);
    const otFile = document.getElementById('ot_attachment').files[0];
    if (otFile) otFd.append('attachment', otFile);

    fetch('/task-management/' + document.getElementById('ot_id').value + '/objection', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: otFd,
    })
      .then(r => r.json().then(data => ({ ok: r.ok, data })))
      .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = original;
        if (ok && data.success) {
          Swal.fire({ icon: 'success', title: 'Objection Raised', text: data.message, timer: 1800, showConfirmButton: false })
            .then(() => window.location.reload());
        } else {
          Swal.fire({ icon: 'error', title: 'Could not raise objection', text: data.message || 'Something went wrong.' });
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
      });
  });

  // ══ Recurring Tasks: manage (remove a person / stop entirely) ═══════════
  const manageModalEl = document.getElementById('manageScheduleModal');
  if (manageModalEl) {
    const manageModal = new bootstrap.Modal(manageModalEl);
    let currentScheduleId = null;

    function renderTargetList(targets) {
      const list = document.getElementById('msTargetList');
      list.innerHTML = '';
      if (!targets.length) {
        list.innerHTML = '<li class="list-group-item text-muted">Nobody left on this recurring task.</li>';
        return;
      }
      targets.forEach(function (t) {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center justify-content-between';
        li.innerHTML = '<span>' + t.name + '</span>';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-icon icon-sophisticated text-danger';
        removeBtn.title = 'Remove from this recurring task';
        removeBtn.innerHTML = '<i class="bx bx-x"></i>';
        removeBtn.addEventListener('click', function () {
          fetch('/task-management/schedules/' + currentScheduleId + '/remove-user', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': "{{ csrf_token() }}",
              'Accept': 'application/json',
            },
            body: JSON.stringify({ user_id: t.id }),
          })
            .then(r => r.json())
            .then(data => {
              if (data.success) { window.location.reload(); }
              else { Swal.fire({ icon: 'error', title: 'Could not remove', text: data.message || '' }); }
            });
        });
        li.appendChild(removeBtn);
        list.appendChild(li);
      });
    }

    document.querySelectorAll('.js-manage-schedule').forEach(function (btn) {
      btn.addEventListener('click', function () {
        currentScheduleId = btn.dataset.id;
        document.getElementById('msTitle').textContent = btn.dataset.title;
        document.getElementById('msRecurrence').innerHTML = '<i class="bx bx-repeat"></i> ' + btn.dataset.recurrence;
        renderTargetList(JSON.parse(btn.dataset.targets || '[]'));
        manageModal.show();
      });
    });

    document.getElementById('msStopBtn').addEventListener('click', function () {
      if (!currentScheduleId) return;
      Swal.fire({
        icon: 'warning', title: 'Stop this recurring task?',
        text: 'No more tasks will be created from it. Anything already assigned stays as it is.',
        showCancelButton: true, confirmButtonText: 'Stop It', confirmButtonColor: '#dc2626',
      }).then((res) => {
        if (!res.isConfirmed) return;
        fetch('/task-management/schedules/' + currentScheduleId + '/stop', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
        })
          .then(r => r.json())
          .then(data => {
            if (data.success) { window.location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Could not stop', text: data.message || '' }); }
          });
      });
    });
  }
});
</script>
@endsection
