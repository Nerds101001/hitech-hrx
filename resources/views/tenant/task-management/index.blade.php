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
     .modal-content, which wins the fight against Bootstrap's own
     .modal-dialog-scrollable sizing for a form this tall (title/
     description/priority/interval/due-date/two select2 pickers, or the
     view modal's full reply thread) - not just on narrow phones, but on
     any short browser window, desktop included, where the modal is
     taller than the viewport. Force the body's own scroll explicitly
     rather than relying on that cascade, at every width. */
  #assignTaskModal .modal-dialog,
  #editTaskModal .modal-dialog,
  #viewTaskModal .modal-dialog {
    max-height: 100dvh;
  }
  #assignTaskModal .modal-content,
  #editTaskModal .modal-content,
  #viewTaskModal .modal-content {
    max-height: 100dvh;
  }
  #assignTaskModal .modal-body,
  #editTaskModal .modal-body,
  #viewTaskModal .modal-body {
    overflow-y: auto !important;
    max-height: calc(100dvh - 130px); /* header + footer are ~65px each */
    -webkit-overflow-scrolling: touch;
  }

  @media (max-width: 768px) {
    #assignTaskModal .modal-dialog {
      margin: 0;
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

  /* Bootstrap's .tab-content carries its own 24px side padding (theme
     default, meant for tabbed content that isn't already inside a padded
     wrapper elsewhere). This page's tabs sit inside a .px-4 wrapper that
     already provides that gutter, so the theme's padding stacked an extra
     24px on both sides on top of it - every card inside these tabs was
     narrower than the header/stats above them by 48px total. */
  #tmTabsContent {
    padding: 0 !important;
  }

  #taskTable td, #taskTable th {
    padding: 0.65rem 0.85rem !important;
  }

  /* The expand-row chevron (batch/schedule toggle) is not an action - it
     was wearing the same 32px .icon-sophisticated box as View/Edit/Delete,
     which forced every batched row taller than a plain one just to fit that
     box. A bare small arrow, no background, no border, sized to the text
     line next to it. */
  .tm-row-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 1.2em;
    padding: 0;
    margin: 0;
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 0.75rem;
    line-height: 1;
  }
  .tm-row-toggle:hover {
    color: var(--hitech-primary, #008080);
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

  /* Task update thread — WhatsApp-style chat bubbles: own messages right
     (teal, matching --hitech-primary), everyone else's left (white/grey). */
  #vt_thread{display:flex;flex-direction:column;gap:2px;padding:4px 2px;}
  #vt_thread .chat-bubble-row{display:flex;flex-direction:column;max-width:78%;margin-bottom:6px;}
  #vt_thread .chat-bubble-row.is-own{align-self:flex-end;align-items:flex-end;}
  #vt_thread .chat-bubble-row.is-other{align-self:flex-start;align-items:flex-start;}
  #vt_thread .chat-sender{font-size:.7rem;font-weight:700;color:#0d5c56;margin:0 4px 2px;}
  #vt_thread .chat-bubble{border-radius:14px;padding:8px 12px;font-size:.84rem;line-height:1.4;word-break:break-word;box-shadow:0 1px 1px rgba(0,0,0,.04);}
  #vt_thread .chat-bubble-row.is-own .chat-bubble{background:var(--hitech-primary-light,rgba(0,128,128,.12));color:#0f2f2d;border-bottom-right-radius:4px;}
  #vt_thread .chat-bubble-row.is-other .chat-bubble{background:#f1f5f9;color:#1e293b;border-bottom-left-radius:4px;}
  #vt_thread .chat-bubble a{color:inherit;text-decoration:underline;}
  #vt_thread .chat-time{font-size:.65rem;color:#94a3b8;margin:2px 4px 0;}

  /* Phone (<=480px): the 4-tab segmented control (.hitech-segmented-control,
     shared/hitech-portal.scss) is display:inline-flex with no wrap/scroll,
     so 4 uppercase pill buttons plus badges force the page wider than the
     viewport on every role identically. Scrolls as its own strip instead. */
  @media (max-width: 480px) {
    #tmTabs {
      display: flex;
      flex-wrap: nowrap;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      max-width: 100%;
    }
    #tmTabs .js-tm-tab {
      flex-shrink: 0;
    }
    /* Each tab's own filter row is plain Bootstrap col-lg-*/col-md-6 with no
       phone breakpoint, so fields render as squeezed fractional columns
       instead of stacking - force every filter field full-width here. */
    #taskFilterCard .row.g-4 > [class*="col-"],
    #tmTabsContent .row.g-4 > [class*="col-"] {
      flex: 0 0 100%;
      max-width: 100%;
    }
  }
</style>
@endsection

@section('content')
@php
    // One label for every assignee picker on this page. Names are not unique -
    // two MANISHA KUMARIs are active right now, one in Accounts and one in
    // Billing - so a list of bare names leaves the assigner guessing. The
    // department is what tells them apart. Select2 matches on option text, so
    // typing a department name filters the list too.
    $employeeLabel = function ($e) {
        $label = trim($e->first_name . ' ' . $e->last_name);
        if ($e->department?->name) { $label .= ' — ' . $e->department->name; }
        return $label;
    };
@endphp
<div class="layout-full-width animate__animated animate__fadeIn">

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6 mx-4">
    <div>
      <h3 class="fw-extrabold mb-1 text-dark">Task Management</h3>
      <p class="text-muted mb-0 small">
        Assign work to a person, a hand-picked group, or a whole department at once.
        @if($canSeeAll)
          You can see every task in the system, assigned to or by anyone.
        @else
          You'll only see tasks assigned to you or assigned by you — not everyone else's.
        @endif
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

  <div class="px-4">
    <p class="text-muted small mb-4" style="margin-top:-.75rem;">The counts above are per person - an assignment sent to 10 people counts 10. In the list below it is one row.</p>
    <div class="hitech-segmented-control mb-6" id="tmTabs">
      <button class="btn btn-sm status-toggle-btn active js-tm-tab" data-tm-target="#tmOneTime" type="button">
        <i class="bx bx-task me-1"></i>One-Time Tasks
      </button>
      <button class="btn btn-sm status-toggle-btn js-tm-tab" data-tm-target="#tmRecurring" type="button">
        <i class="bx bx-repeat me-1"></i>Recurring Tasks
        @if($schedules->total() > 0)<span class="badge-hitech badge-brand-soft ms-1">{{ $schedules->total() }}</span>@endif
      </button>
      <button class="btn btn-sm status-toggle-btn js-tm-tab" data-tm-target="#tmPendency" type="button">
        <i class="bx bx-bar-chart-alt-2 me-1"></i>Pendency
        @if($pendency['totalPending'] > 0)<span class="badge-hitech badge-hitech-danger ms-1">{{ $pendency['totalPending'] }}</span>@endif
      </button>
      <button class="btn btn-sm status-toggle-btn js-tm-tab" data-tm-target="#tmEscalations" type="button">
        <i class="bx bx-trending-up me-1"></i>Escalations
        @if($escalations['escalatedCount'] + $escalations['willEscalateCount'] > 0)
          <span class="badge-hitech badge-hitech-danger ms-1">{{ $escalations['escalatedCount'] + $escalations['willEscalateCount'] }}</span>
        @endif
      </button>
    </div>

    <div class="tab-content" id="tmTabsContent">
    <div class="tab-pane fade show active" id="tmOneTime">

    {{-- Filters + task list, one card (same shell KPI Approvals uses) rather
         than a separately-rounded filter card sitting above a separately-
         rounded table card. --}}
    <div class="hitech-card mb-6" id="taskFilterCard">
      <div class="card-body p-4 border-bottom">
        <form method="GET" class="row align-items-end g-4">
          <div class="col-lg-3 col-md-6">
            <div class="search-wrapper-hitech mw-100">
              <i class="bx bx-search text-muted ms-3"></i>
              <input type="text" name="search" class="form-control" placeholder="Search title or person..." value="{{ request('search') }}">
              <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
            </div>
          </div>
          <div class="col-lg-2 col-md-6">
            <label class="form-label fw-bold small text-muted text-uppercase mb-2">View</label>
            <select class="form-select form-select-hitech" name="mine" onchange="this.form.submit()">
              <option value="">{{ $canSeeAll ? 'Everyone' : 'All my tasks' }}</option>
              <option value="to" {{ request('mine') === 'to' ? 'selected' : '' }}>Assigned to me</option>
              <option value="by" {{ request('mine') === 'by' ? 'selected' : '' }}>Assigned by me</option>
            </select>
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
              <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open (not completed)</option>
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
          @foreach(['sort', 'dir', 'user_id'] as $keep)
            @if(request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
          @endforeach
        </form>
        @if(request()->filled('user_id'))
          @php $onlyPerson = \App\Models\User::find((int) request('user_id')); @endphp
          <div class="mt-3 small">
            Showing only <strong>{{ optional($onlyPerson)->getFullName() ?? 'one person' }}</strong>'s tasks
            &middot; <a href="{{ request()->fullUrlWithQuery(['user_id' => null, 'page' => null]) }}">show everyone</a>
          </div>
        @endif
      </div>

      {{-- Bulk action bar - hidden until at least one row is checked --}}
      <div class="p-3 border-bottom d-none align-items-center justify-content-between" id="bulkActionBar">
        <span class="fw-medium"><span id="bulkSelectedCount">0</span> selected</span>
        <button type="button" class="btn btn-sm btn-hitech-danger" id="btnBulkDelete">
          <i class="bx bx-trash"></i> Delete Selected
        </button>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="taskTable">
          @php
            // Click a header to sort by it; click again to reverse.
            $sortLink = function (string $key, string $label) use ($sort, $dir) {
                $active = $sort === $key;
                $next = $active && $dir === 'asc' ? 'desc' : 'asc';
                $icon = $active ? ($dir === 'asc' ? 'bx-sort-up' : 'bx-sort-down') : 'bx-sort-alt-2 text-muted opacity-50';
                return '<a href="' . e(request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null])) . '" class="text-reset text-decoration-none text-nowrap">'
                    . e($label) . ' <i class="bx ' . $icon . '"></i></a>';
            };
          @endphp
          <thead class="table-light">
            <tr>
              <th style="width:40px"><input type="checkbox" class="form-check-input" id="checkAllTasks"></th>
              <th style="min-width:200px">{!! $sortLink('title', 'Task') !!}</th>
              <th style="min-width:180px">{!! $sortLink('assignee', 'Assigned To') !!}</th>
              <th>{!! $sortLink('priority', 'Priority') !!}</th>
              <th>{!! $sortLink('due', 'Due') !!}</th>
              <th>{!! $sortLink('assigned_by', 'Assigned By') !!}</th>
              <th>{!! $sortLink('status', 'Status') !!}</th>
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
                // Now that this page is open to everyone, a row can be here
                // because it's assigned TO this person, not BY them - View
                // still makes sense, but Edit/Objection/Delete (and bulk
                // delete's checkbox) would just 403 on click, same gate the
                // backend already enforces.
                $canManageThis = \App\Support\TaskAccess::canManage(auth()->user(), $t);
              @endphp
              <tr @if($isBatched) data-batch-summary="{{ $t->batch_id }}" @endif>
                <td>
                  @if($canManageThis)
                    <input type="checkbox" class="form-check-input js-task-check" value="{{ $t->id }}">
                  @endif
                </td>
                <td>
                  <div class="d-flex align-items-start gap-2">
                    @if($isBatched)
                      <button type="button" class="tm-row-toggle js-batch-toggle flex-shrink-0"
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
                      @else
                        @php $latest = $t->taskUpdates->first(); @endphp
                        @if($latest && $latest->comment)
                          <small class="text-muted d-block fst-italic text-truncate" style="max-width:320px;" title="{{ $latest->comment }}">
                            <i class="bx bx-message-rounded-dots"></i> {{ optional($latest->createdBy)->getFullName() ?? '—' }}: {{ $latest->comment }}
                          </small>
                        @endif
                      @endif
                      @if($t->ccUsers->isNotEmpty())
                        <small class="text-muted d-block"><i class="bx bx-user-voice"></i> CC: {{ $t->ccUsers->map(fn ($u) => $u->getFullName())->implode(', ') }}</small>
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
                  {{ $t->dueLabel('d/m/Y') ?? '—' }}
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
                  @if($isBatched)
                    {{-- One assignment, many people: the popup used to show just
                         the first person's copy. Expand instead - every person
                         has their own View there. --}}
                    <button type="button" class="icon-sophisticated js-batch-view" data-batch-id="{{ $t->batch_id }}" title="View everyone" aria-label="View everyone on this task">
                      <i class="bx bx-show"></i>
                    </button>
                  @else
                    <button type="button" class="icon-sophisticated js-view-task" title="View" aria-label="View task"
                            data-task="{{ json_encode(\App\Http\Controllers\tenant\TaskManagementController::viewPayload($t)) }}">
                      <i class="bx bx-show"></i>
                    </button>
                  @endif
                  @if($canManageThis)
                    <button type="button" class="icon-sophisticated js-edit-task" title="Edit"
                            data-id="{{ $t->id }}" data-title="{{ $t->title }}" data-description="{{ $t->description }}"
                            data-priority="{{ $t->priority }}" data-due="{{ $t->due_date?->format('Y-m-d H:i') }}"
                            data-user-id="{{ $t->user_id }}" data-user-name="{{ optional($t->user)->getFullName() ?? 'Unknown' }}"
                            data-cc-ids="{{ $t->ccUsers->pluck('id')->implode(',') }}"
                            data-batch-size="{{ $isBatched ? $batchCounts[$t->batch_id]->total : 1 }}">
                      <i class="bx bx-edit"></i>
                    </button>
                    @if(in_array($t->status, ['completed', 'resolved']))
                      <button type="button" class="icon-sophisticated text-warning js-object-task" title="Raise Objection"
                              data-id="{{ $t->id }}" data-title="{{ $t->title }}">
                        <i class="bx bx-error"></i>
                      </button>
                    @endif
                    <button class="icon-sophisticated text-danger js-delete-task" data-id="{{ $t->id }}" title="Delete">
                      <i class="bx bx-trash"></i>
                    </button>
                  @endif
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
                            <th>Timing</th>
                            <th style="min-width:220px">Latest remark</th>
                            <th class="text-end"></th>
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

    </div>{{-- /#tmOneTime --}}

    <div class="tab-pane fade" id="tmRecurring">
      <div class="hitech-card">
        <div class="card-body p-4 pb-2 border-bottom">
          <p class="text-muted small mb-3">Fires automatically every time its day comes round — no need to reassign it yourself. Expand a row to see every day it has actually fired.</p>
          <form method="GET" class="row align-items-end g-4">
            <div class="col-lg-3 col-md-6">
              <div class="search-wrapper-hitech mw-100">
                <i class="bx bx-search text-muted ms-3"></i>
                <input type="text" name="recurring_search" class="form-control" placeholder="Search recurring task title..." value="{{ request('recurring_search') }}">
                <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
              </div>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
              <select class="form-select form-select-hitech" name="recurring_status" onchange="this.form.submit()">
                <option value="active" {{ (request('recurring_status', 'active') === 'active') ? 'selected' : '' }}>Active (default)</option>
                <option value="stopped" {{ request('recurring_status') === 'stopped' ? 'selected' : '' }}>Stopped</option>
                <option value="all" {{ request('recurring_status') === 'all' ? 'selected' : '' }}>Active + Stopped</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Priority</label>
              <select class="form-select form-select-hitech" name="recurring_priority" onchange="this.form.submit()">
                <option value="">All priorities</option>
                @foreach(\App\Models\Task::PRIORITIES as $p)
                  <option value="{{ $p }}" {{ request('recurring_priority') === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Repeats</label>
              <select class="form-select form-select-hitech" name="recurring_frequency" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="daily" {{ request('recurring_frequency') === 'daily' ? 'selected' : '' }}>Daily</option>
                <option value="weekly" {{ request('recurring_frequency') === 'weekly' ? 'selected' : '' }}>Weekly</option>
                <option value="monthly" {{ request('recurring_frequency') === 'monthly' ? 'selected' : '' }}>Monthly</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Per Page</label>
              <select class="form-select form-select-hitech" name="recurring_per_page" onchange="this.form.submit()">
                @foreach(['10' => '10 per page', '15' => '15 per page', '20' => '20 per page', '50' => '50 per page', '100' => '100 per page', 'all' => 'All'] as $val => $label)
                  <option value="{{ $val }}" {{ (string) request('recurring_per_page', '15') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-1 col-md-6">
              <a href="{{ route('task-management.index') }}#tmRecurring" class="btn btn-hitech-cancel w-100">Clear</a>
            </div>
            @foreach(['recurring_sort', 'recurring_dir'] as $keep)
              @if(request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
            @endforeach
          </form>
        </div>
        <div class="table-responsive">
          @php
            $recSortLink = fn($col) => request()->fullUrlWithQuery([
              'recurring_sort' => $col,
              'recurring_dir' => (($recurringSort ?? 'created') === $col && ($recurringDir ?? 'desc') === 'desc') ? 'asc' : 'desc',
              'recurring_page' => null,
            ]) . '#tmRecurring';
            $recSortIcon = fn($col) => ($recurringSort ?? 'created') !== $col ? '' : (($recurringDir ?? 'desc') === 'desc' ? ' <i class="bx bx-chevron-down"></i>' : ' <i class="bx bx-chevron-up"></i>');
          @endphp
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:200px"><a href="{{ $recSortLink('title') }}" class="text-dark text-decoration-none">Task{!! $recSortIcon('title') !!}</a></th>
                <th><a href="{{ $recSortLink('frequency') }}" class="text-dark text-decoration-none">Repeats{!! $recSortIcon('frequency') !!}</a></th>
                <th><a href="{{ $recSortLink('priority') }}" class="text-dark text-decoration-none">Priority{!! $recSortIcon('priority') !!}</a></th>
                <th>Assigned By</th>
                <th>People</th>
                <th style="min-width:170px">Last Fired</th>
                <th class="text-end" style="min-width:160px">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($schedules as $s)
                @php
                  $lastFire = $lastFireStats->get($s->id);
                  // The Manage action (stop it / remove one person) and the
                  // firing-history expand both hit endpoints the backend
                  // already restricts to the schedule's own creator or
                  // admin/hr - a target-only viewer sees the row (it's their
                  // own recurring assignment too) but not those controls,
                  // same treatment as a one-time task assigned to them.
                  $canManageSchedule = \App\Support\TaskAccess::isUnrestricted(auth()->user()) || (int) $s->assigned_by_id === auth()->id();
                @endphp
                <tr>
                  <td>
                    <div class="d-flex align-items-start gap-2">
                      @if($canManageSchedule)
                        <button type="button" class="tm-row-toggle js-schedule-toggle flex-shrink-0"
                                data-schedule-id="{{ $s->id }}" title="Show firing history">
                          <i class="bx bx-chevron-right"></i>
                        </button>
                      @endif
                      <div>
                        <div class="fw-medium">
                          {{ $s->title }}
                          @unless($s->is_active)
                            <span class="badge-hitech badge-muted ms-1" title="No longer creating tasks">Stopped</span>
                          @endunless
                        </div>
                        @if($s->description)<small class="text-muted">{{ \Illuminate\Support\Str::limit($s->description, 70) }}</small>@endif
                      </div>
                    </div>
                  </td>
                  <td><span class="badge-hitech badge-brand-soft"><i class="bx bx-repeat"></i> {{ $s->recurrenceLabel() }}</span></td>
                  <td><span class="badge-hitech {{ ['low' => 'badge-muted', 'medium' => 'badge-brand-soft', 'high' => 'badge-hitech-danger'][$s->priority] ?? 'badge-muted' }}">{{ ucfirst($s->priority) }}</span></td>
                  <td><small class="text-muted">{{ optional($s->assignedBy)->getFullName() ?? '—' }}</small></td>
                  @php
                    $peopleOn = count($s->target_user_ids ?? []);
                    $activeOn = collect($s->target_user_ids ?? [])->filter(fn ($uid) => isset($activeTargetIds[(int) $uid]))->count();
                  @endphp
                  <td>
                    {{ $peopleOn }} {{ \Illuminate\Support\Str::plural('person', $peopleOn) }}
                    @if($activeOn === 0)
                      <span class="badge-hitech badge-hitech-danger d-block mt-1" style="width:fit-content;" title="Everyone on this recurring task has left - it creates nothing">Nobody active</span>
                    @elseif($activeOn < $peopleOn)
                      <small class="text-muted d-block">{{ $peopleOn - $activeOn }} left the company</small>
                    @endif
                  </td>
                  <td>
                    @if($lastFire)
                      <div class="small">{{ \Carbon\Carbon::parse($lastFire->fire_date)->format('d M Y') }}</div>
                      <span class="badge-hitech {{ $lastFire->done == $lastFire->total ? 'badge-hitech-success' : 'badge-brand-soft' }}">
                        {{ (int) $lastFire->done }}/{{ $lastFire->total }} done
                      </span>
                    @else
                      <span class="badge-hitech badge-muted">Not yet fired</span>
                    @endif
                  </td>
                  <td class="text-end">
                    @if($canManageSchedule)
                      <button type="button" class="btn btn-sm btn-hitech-cancel js-manage-schedule"
                              data-id="{{ $s->id }}" data-title="{{ $s->title }}" data-recurrence="{{ $s->recurrenceLabel() }}"
                              data-ends-on="{{ optional($s->ends_on)->format('Y-m-d') }}"
                              data-targets="{{ json_encode(collect($s->target_user_ids ?? [])->map(fn($uid) => ['id' => $uid, 'name' => optional($targetNames->get($uid))->getFullName() ?? 'Unknown'])->values()) }}">
                        Manage
                      </button>
                    @endif
                  </td>
                </tr>
                <tr class="d-none schedule-history-row" data-schedule-history="{{ $s->id }}">
                  <td></td>
                  <td colspan="6" class="p-0">
                    <div class="bg-light p-3">
                      <table class="table table-sm table-hover align-middle mb-0 bg-white">
                        <thead>
                          <tr>
                            <th>Date</th>
                            <th>Outcome</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody class="js-schedule-history-body">
                          {{-- filled by AJAX on first expand --}}
                        </tbody>
                      </table>
                    </div>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-5">
                  <i class="bx bx-repeat fs-3 d-block mb-2 text-success"></i>
                  @if(request()->filled('recurring_search') || request()->filled('recurring_priority') || request()->filled('recurring_frequency') || request('recurring_status', 'active') !== 'active')
                    Nothing matches these filters —
                    <a href="{{ route('task-management.index') }}#tmRecurring">clear them</a>.
                  @else
                    No recurring tasks set up.
                  @endif
                </td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($schedules->hasPages())
          <div class="p-3 border-top">{{ $schedules->links() }}</div>
        @endif
      </div>
    </div>{{-- /#tmRecurring --}}

    <div class="tab-pane fade" id="tmPendency">
      <div class="row row-cols-2 row-cols-md-4 g-6 mb-6">
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-red uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $pendency['totalPending'] }}</h3><span class="stat-label">Total pending</span></div>
          </div>
        </div>
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-red uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-error-circle"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $pendency['totalOverdue'] }}</h3><span class="stat-label">Overdue</span></div>
          </div>
        </div>
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-buildings"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $pendency['departmentsAffected'] }}</h3><span class="stat-label">Departments with backlog</span></div>
          </div>
        </div>
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-group"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $pendency['peopleAffected'] }}</h3><span class="stat-label">People with backlog</span></div>
          </div>
        </div>
      </div>

      <div class="hitech-card mb-6">
        <div class="card-body p-4 pb-2">
          <h6 class="fw-bold mb-0">Pending by Department</h6>
          <p class="text-muted small mb-0">Click a department to filter the table below to just that team.</p>
        </div>
        <div class="table-responsive">
          @php
            $deptSortLink = fn($col) => request()->fullUrlWithQuery([
              'pendency_dept_sort' => $col,
              'pendency_dept_dir' => ($pendency['deptSort'] === $col && $pendency['deptDir'] === 'desc') ? 'asc' : 'desc',
            ]) . '#tmPendency';
            $deptSortIcon = fn($col) => $pendency['deptSort'] !== $col ? '' : ($pendency['deptDir'] === 'desc' ? ' <i class="bx bx-chevron-down"></i>' : ' <i class="bx bx-chevron-up"></i>');
          @endphp
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:200px"><a href="{{ $deptSortLink('name') }}" class="text-dark text-decoration-none js-pendency-tab-link">Department{!! $deptSortIcon('name') !!}</a></th>
                <th><a href="{{ $deptSortLink('pending') }}" class="text-dark text-decoration-none js-pendency-tab-link">Pending{!! $deptSortIcon('pending') !!}</a></th>
                <th><a href="{{ $deptSortLink('overdue') }}" class="text-dark text-decoration-none js-pendency-tab-link">Overdue{!! $deptSortIcon('overdue') !!}</a></th>
                <th class="text-end">Filter</th>
              </tr>
            </thead>
            <tbody>
              @forelse($pendency['byDepartment'] as $d)
                <tr class="{{ (string) request('pendency_department_id') === (string) $d->department_id ? 'table-active' : '' }}">
                  <td class="fw-medium">{{ $d->department_name }}</td>
                  <td><span class="badge-hitech badge-brand-soft">{{ $d->pending }}</span></td>
                  <td>
                    @if($d->overdue > 0)
                      <span class="badge-hitech badge-hitech-danger">{{ (int) $d->overdue }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-end">
                    <a href="{{ request()->fullUrlWithQuery(['pendency_department_id' => $d->department_id]) }}#tmPendency" class="small js-pendency-tab-link">View team</a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No backlog anywhere — clean slate.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <div class="hitech-card">
        <div class="card-body p-4 pb-2 border-bottom">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
              <h6 class="fw-bold mb-0">Pending by Person</h6>
              <p class="text-muted small mb-0">Every person with at least one pending task, worst first.</p>
            </div>
          </div>
          <form method="GET" class="row align-items-end g-4">
            <div class="col-lg-3 col-md-6">
              <div class="search-wrapper-hitech mw-100">
                <i class="bx bx-search text-muted ms-3"></i>
                <input type="text" name="pendency_search" class="form-control" placeholder="Search person..." value="{{ request('pendency_search') }}">
                <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
              </div>
            </div>
            <div class="col-lg-3 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Department</label>
              <select class="form-select form-select-hitech" name="pendency_department_id" onchange="this.form.submit()">
                <option value="">All Departments</option>
                @foreach($departments as $d)
                  <option value="{{ $d->id }}" {{ (string) request('pendency_department_id') === (string) $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Per Page</label>
              <select class="form-select form-select-hitech" name="pendency_per_page" onchange="this.form.submit()">
                @foreach(['10' => '10 per page', '15' => '15 per page', '25' => '25 per page', '50' => '50 per page', 'all' => 'All'] as $val => $label)
                  <option value="{{ $val }}" {{ (string) request('pendency_per_page', '15') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-1 col-md-6">
              <a href="{{ route('task-management.index') }}#tmPendency" class="btn btn-hitech-cancel w-100">Clear</a>
            </div>
            @foreach(['pendency_sort', 'pendency_dir'] as $keep)
              @if(request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
            @endforeach
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                @php
                  $pendSortLink = fn($col) => request()->fullUrlWithQuery([
                    'pendency_sort' => $col,
                    'pendency_dir' => ($pendency['sort'] === $col && $pendency['dir'] === 'desc') ? 'asc' : 'desc',
                    'pendency_page' => null,
                  ]) . '#tmPendency';
                  $sortIcon = fn($col) => $pendency['sort'] !== $col ? '' : ($pendency['dir'] === 'desc' ? ' <i class="bx bx-chevron-down"></i>' : ' <i class="bx bx-chevron-up"></i>');
                @endphp
                <th style="min-width:180px"><a href="{{ $pendSortLink('name') }}" class="text-dark text-decoration-none js-pendency-tab-link">Person{!! $sortIcon('name') !!}</a></th>
                <th><a href="{{ $pendSortLink('department') }}" class="text-dark text-decoration-none js-pendency-tab-link">Department{!! $sortIcon('department') !!}</a></th>
                <th><a href="{{ $pendSortLink('pending') }}" class="text-dark text-decoration-none js-pendency-tab-link">Pending{!! $sortIcon('pending') !!}</a></th>
                <th><a href="{{ $pendSortLink('overdue') }}" class="text-dark text-decoration-none js-pendency-tab-link">Overdue{!! $sortIcon('overdue') !!}</a></th>
                <th class="text-end">View</th>
              </tr>
            </thead>
            <tbody>
              @forelse($pendency['byPerson'] as $p)
                <tr>
                  <td class="fw-medium">{{ trim($p->first_name . ' ' . $p->last_name) }}</td>
                  <td><small class="text-muted">{{ $p->department_name }}</small></td>
                  <td><span class="badge-hitech badge-brand-soft">{{ $p->pending }}</span></td>
                  <td>
                    @if($p->overdue > 0)
                      <span class="badge-hitech badge-hitech-danger">{{ (int) $p->overdue }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-end">
                    <a href="{{ route('task-management.index', ['user_id' => $p->user_id, 'status' => 'open']) }}" class="small text-decoration-none js-pendency-tab-link">View tasks</a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">
                  @if(request()->filled('pendency_search') || request()->filled('pendency_department_id'))
                    Nothing matches these filters —
                    <a href="{{ route('task-management.index') }}#tmPendency">clear them</a>.
                  @else
                    No one has a pending task right now — clean slate.
                  @endif
                </td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($pendency['byPerson']->hasPages())
          <div class="p-3 border-top">{{ $pendency['byPerson']->links() }}</div>
        @endif
      </div>
    </div>{{-- /#tmPendency --}}

    <div class="tab-pane fade" id="tmEscalations">
      <div class="row row-cols-2 row-cols-md-4 g-6 mb-6">
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-red uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-red" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-error-circle"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $escalations['escalatedCount'] }}</h3><span class="stat-label">Already Escalated</span></div>
          </div>
        </div>
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-amber uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-amber" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-time-five"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $escalations['willEscalateCount'] }}</h3><span class="stat-label">Will Escalate Next Run</span></div>
          </div>
        </div>
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-blue uniform-card">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-blue" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-hourglass"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $escalations['pendingCount'] }}</h3><span class="stat-label">Overdue, Not Yet at {{ $escalations['thresholdDays'] }}d</span></div>
          </div>
        </div>
        <div class="col">
          <div class="hitech-stat-card dashboard-variant card-teal uniform-card" title="Overdue since before escalation went live ({{ config('tasks.escalate_from') }}) - the storm guard excludes these from ever auto-escalating, on purpose.">
            <div class="stat-card-header mb-2"><div class="stat-icon-wrap icon-teal" style="width:38px;height:38px;font-size:1.2rem;"><i class="bx bx-shield-x"></i></div></div>
            <div><h3 class="stat-value mb-0">{{ $escalations['exemptCount'] }}</h3><span class="stat-label">Pre-Rule Backlog (Exempt)</span></div>
          </div>
        </div>
      </div>

      <div class="hitech-card">
        <div class="card-body p-4 pb-2 border-bottom">
          <p class="text-muted small mb-3">
            A task open past its due date crosses the line once it has been overdue
            <strong>{{ $escalations['thresholdDays'] }}+ {{ \Illuminate\Support\Str::plural('day', $escalations['thresholdDays']) }}</strong>
            — it then escalates (once) to the assignee's manager. This is every currently overdue task you can see, with its exact status.
          </p>
          <form method="GET" class="row align-items-end g-4">
            <div class="col-lg-3 col-md-6">
              <div class="search-wrapper-hitech mw-100">
                <i class="bx bx-search text-muted ms-3"></i>
                <input type="text" name="escalation_search" class="form-control" placeholder="Search task, assignee or assigner..." value="{{ request('escalation_search') }}">
                <button class="btn-search shadow-sm" type="submit"><i class="bx bx-search fs-5"></i><span>Search</span></button>
              </div>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Status</label>
              <select class="form-select form-select-hitech" name="escalation_status" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="escalated" {{ request('escalation_status') === 'escalated' ? 'selected' : '' }}>Already Escalated</option>
                <option value="will_escalate" {{ request('escalation_status') === 'will_escalate' ? 'selected' : '' }}>Will Escalate Next Run</option>
                <option value="pending" {{ request('escalation_status') === 'pending' ? 'selected' : '' }}>Overdue, Not Yet at Threshold</option>
                <option value="exempt" {{ request('escalation_status') === 'exempt' ? 'selected' : '' }}>Pre-Rule Backlog (Exempt)</option>
              </select>
            </div>
            <div class="col-lg-2 col-md-6">
              <label class="form-label fw-bold small text-muted text-uppercase mb-2">Per Page</label>
              <select class="form-select form-select-hitech" name="escalation_per_page" onchange="this.form.submit()">
                @foreach(['10' => '10 per page', '15' => '15 per page', '25' => '25 per page', '50' => '50 per page', 'all' => 'All'] as $val => $label)
                  <option value="{{ $val }}" {{ (string) request('escalation_per_page', '15') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-lg-1 col-md-6">
              <a href="{{ route('task-management.index') }}#tmEscalations" class="btn btn-hitech-cancel w-100">Clear</a>
            </div>
            @foreach(['escalation_sort', 'escalation_dir'] as $keep)
              @if(request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
            @endforeach
          </form>
        </div>
        <div class="table-responsive">
          @php
            $escSortLink = fn($col) => request()->fullUrlWithQuery([
              'escalation_sort' => $col,
              'escalation_dir' => ($escalations['sort'] === $col && $escalations['dir'] === 'desc') ? 'asc' : 'desc',
              'escalation_page' => null,
            ]) . '#tmEscalations';
            $escSortIcon = fn($col) => $escalations['sort'] !== $col ? '' : ($escalations['dir'] === 'desc' ? ' <i class="bx bx-chevron-down"></i>' : ' <i class="bx bx-chevron-up"></i>');
            $escStatusBadge = ['escalated' => ['Escalated', 'badge-hitech-danger'], 'will_escalate' => ['Will Escalate', 'badge-brand-soft'], 'pending' => ['Pending', 'badge-muted'], 'exempt' => ['Exempt', 'badge-muted']];
          @endphp
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:200px"><a href="{{ $escSortLink('title') }}" class="text-dark text-decoration-none">Task{!! $escSortIcon('title') !!}</a></th>
                <th>Assigned By</th>
                <th>Assigned To</th>
                <th>Escalated To</th>
                <th>Due Date</th>
                <th><a href="{{ $escSortLink('days') }}" class="text-dark text-decoration-none">Days Overdue{!! $escSortIcon('days') !!}</a></th>
                <th><a href="{{ $escSortLink('status') }}" class="text-dark text-decoration-none">Status{!! $escSortIcon('status') !!}</a></th>
              </tr>
            </thead>
            <tbody>
              @forelse($escalations['rows'] as $r)
                <tr>
                  <td class="fw-medium">{{ $r['title'] }}</td>
                  <td><small class="text-muted">{{ $r['assignedBy'] }}</small></td>
                  <td><small class="text-muted">{{ $r['assignee'] }}</small></td>
                  <td><small class="text-muted">{{ $r['escalatedTo'] }}</small></td>
                  <td><small>{{ $r['dueLabel'] }}</small></td>
                  <td><span class="badge-hitech badge-hitech-danger">{{ $r['daysOverdue'] }}</span></td>
                  <td>
                    @php [$label, $cls] = $escStatusBadge[$r['status']] ?? ['—', 'badge-muted']; @endphp
                    <span class="badge-hitech {{ $cls }}">{{ $label }}</span>
                    @if($r['status'] === 'escalated' && $r['escalatedAt'])
                      <div class="small text-muted mt-1">on {{ $r['escalatedAt']->format('d M Y') }}</div>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-5">
                  <i class="bx bx-check-shield fs-3 d-block mb-2 text-success"></i>
                  @if(request()->filled('escalation_search') || request()->filled('escalation_status'))
                    Nothing matches these filters — <a href="{{ route('task-management.index') }}#tmEscalations">clear them</a>.
                  @else
                    Nothing overdue right now — clean slate.
                  @endif
                </td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if($escalations['rows']->hasPages())
          <div class="p-3 border-top">{{ $escalations['rows']->links() }}</div>
        @endif
      </div>
    </div>{{-- /#tmEscalations --}}

    </div>{{-- /.tab-content --}}
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
            <div class="col-md-4 d-none" id="at_ends_on_wrap">
              <label class="form-label">End date <span class="text-muted">(optional)</span></label>
              <input type="date" id="at_ends_on" class="form-control">
              <div class="form-text">Leave blank to run until stopped. After this date it stops on its own.</div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-6">
              <label class="form-label">Assign to employees</label>
              <select id="at_employees" class="form-select select2" multiple>
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ $employeeLabel($e) }}</option>
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
              <label class="form-label">CC (optional)</label>
              <select id="at_cc" class="form-select select2" multiple>
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ $employeeLabel($e) }}</option>
                @endforeach
              </select>
              <div class="form-text">People who can follow this task's status and reply without owning it — it shows up on their dashboard as something Assigned to someone else you're watching.</div>
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
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
            <div class="col-12 d-none" id="et_batch_wrap">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="et_apply_batch" checked>
                <label class="form-check-label" for="et_apply_batch">Apply to all <span id="et_batch_size"></span> people on this task</label>
              </div>
              <div class="form-text">Untick to change only the one person's copy shown on this row.</div>
            </div>
            <div class="col-12" id="et_assignee_wrap">
              <label class="form-label">Assigned to</label>
              <select id="et_assignee" class="form-select select2-single">
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ $employeeLabel($e) }}</option>
                @endforeach
              </select>
              <div class="form-text">Reassigns this one task — only to someone on your own team.</div>
            </div>
            <div class="col-12">
              <label class="form-label">CC (optional)</label>
              <select id="et_cc" class="form-select select2" multiple>
                @foreach($employees as $e)
                  <option value="{{ $e->id }}">{{ $employeeLabel($e) }}</option>
                @endforeach
              </select>
              <div class="form-text">People who can follow this task's status and reply without owning it.</div>
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
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
          <dt class="col-4">Assigned To</dt><dd class="col-8" id="vt_assignee"></dd>
          <dt class="col-4">Assigned By</dt><dd class="col-8" id="vt_assignedBy"></dd>
          <dt class="col-4 d-none" id="vt_cc_label">CC</dt><dd class="col-8 d-none" id="vt_cc"></dd>
          <dt class="col-4">Status</dt><dd class="col-8" id="vt_status"></dd>
          <dt class="col-4 d-none" id="vt_objection_label">Objection</dt><dd class="col-8 d-none" id="vt_objection"></dd>
        </dl>
        <hr class="my-3">
        <dl class="row mb-0">
          <dt class="col-4">Assigned On</dt><dd class="col-8" id="vt_assignedOn"></dd>
          <dt class="col-4">Due</dt><dd class="col-8" id="vt_due"></dd>
          <dt class="col-4 d-none" id="vt_expected_label">Assignee expects</dt><dd class="col-8 d-none" id="vt_expected"></dd>
          <dt class="col-4">Actual Completion</dt><dd class="col-8" id="vt_actualCompletion"></dd>
          <dt class="col-4 d-none" id="vt_timing_label">Timing</dt>
          <dd class="col-8 d-none" id="vt_timing_wrap"><span class="badge-hitech" id="vt_timing"></span></dd>
        </dl>
        <div class="d-none mt-3" id="vt_attachment_wrap">
          <div class="fw-medium small text-muted mb-1">Attachment</div>
          <a id="vt_attachment_link" href="#" target="_blank"><img id="vt_attachment_img" src="" alt="Attachment" class="rounded" style="max-width:100%;max-height:220px;border:1px solid #e2e8f0;"></a>
        </div>
        <div class="d-none mt-3" id="vt_update_attachment_wrap">
          <div class="fw-medium small text-muted mb-1">Completion / objection photo</div>
          <a id="vt_update_attachment_link" href="#" target="_blank"><img id="vt_update_attachment_img" src="" alt="Attachment" class="rounded" style="max-width:100%;max-height:220px;border:1px solid #e2e8f0;"></a>
        </div>

        <hr class="my-3">
        <div class="fw-medium small text-muted mb-2">Updates &amp; replies</div>
        <div id="vt_thread" style="max-height:260px;overflow-y:auto;">
          <div class="text-muted small" id="vt_thread_loading">Loading...</div>
        </div>
        <div class="input-group input-group-sm mt-2">
          <input type="text" id="vt_reply_text" class="form-control" placeholder="Add a reply or follow-up...">
          <button type="button" class="btn btn-hitech" id="vt_reply_btn">Send</button>
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
        <div class="border rounded p-3 mb-3 bg-light">
          <label class="form-label fw-semibold mb-1"><i class="bx bx-calendar-x me-1"></i>End date <span class="text-muted fw-normal">(optional)</span></label>
          <div class="d-flex gap-2 align-items-center">
            <input type="date" id="msEndsOn" class="form-control form-control-sm" style="max-width:200px">
            <button type="button" class="btn btn-sm btn-hitech" id="msEndsOnSave">Save</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="msEndsOnClear" title="Clear end date">Clear</button>
          </div>
          <div class="form-text">Set a date to make this recurring task stop on its own. Leave blank / clear to run until stopped.</div>
        </div>
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
  // A stale session (token expired, logged in elsewhere, long-idle tab)
  // makes every one of this page's fetch() calls 419 - previously that
  // surfaced as a raw "CSRF token mismatch." inside the normal error
  // dialog, which reads like a real save failure rather than what it is.
  // Reloading the current page while unauthenticated bounces through the
  // auth middleware straight to login, same as the app's other session-
  // expiry handling (main.js's global $.ajaxError for jQuery requests) -
  // this just covers the fetch()-based calls that handler never sees.
  function fetchJson(url, opts) {
    return fetch(url, opts).then(function (r) {
      if (r.status === 419) {
        window.location.reload();
        return new Promise(function () {}); // page is navigating away
      }
      return r.json().then(function (data) { return { ok: r.ok, data: data }; });
    });
  }

  // ══ Tabs — same segmented-pill toggle as Leave, driven by plain JS
  // rather than Bootstrap's tab component (this page's buttons don't sit in
  // a .nav, and swapping to Bootstrap's own tab markup is exactly the
  // "doesn't match the rest of the app" look this replaced). ═════════════
  document.querySelectorAll('.js-tm-tab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.js-tm-tab').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('#tmTabsContent > .tab-pane').forEach(function (pane) {
        pane.classList.remove('show', 'active');
      });
      document.querySelector(btn.dataset.tmTarget).classList.add('show', 'active');
    });
  });

  // Pendency's department/sort links reload the page (GET params drive the
  // query) rather than fetching in place, which would otherwise dump
  // whoever clicked one back on the One-Time tab instead of where they were.
  // Restore the active tab after a reload — from the URL hash, or from a tab's
  // own query params (its search box submits a plain form with no hash).
  (function () {
    const params = new URLSearchParams(window.location.search);
    const hasPrefix = (pre) => [...params.keys()].some((k) => k.startsWith(pre));
    let target = null;
    if (window.location.hash === '#tmRecurring' || hasPrefix('recurring_')) target = '#tmRecurring';
    else if (window.location.hash === '#tmPendency' || hasPrefix('pendency_')) target = '#tmPendency';
    else if (window.location.hash === '#tmEscalations' || hasPrefix('escalation_')) target = '#tmEscalations';
    if (target) {
      const trigger = document.querySelector('.js-tm-tab[data-tm-target="' + target + '"]');
      if (trigger) trigger.click();
    }
  })();

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
    document.getElementById('at_ends_on_wrap').classList.toggle('d-none', !isRecurring);
    if (isRecurring) {
      document.getElementById('at_recurrence_display').value = recurrenceLabel(interval);
    } else {
      atDueDatePicker.clear();
      document.getElementById('at_ends_on').value = '';
    }
  }

  document.getElementById('at_interval').addEventListener('change', refreshInterval);
  document.getElementById('at_weekday').addEventListener('change', refreshInterval);
  document.getElementById('at_monthday').addEventListener('change', refreshInterval);

  document.getElementById('btnAssignTask').addEventListener('click', function () {
    document.getElementById('assignTaskForm').reset();
    atDueDatePicker.clear();
    refreshInterval();
    $('#at_employees, #at_departments, #at_cc').val(null).trigger('change');
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
    if (document.getElementById('at_interval').value && document.getElementById('at_ends_on').value) fd.append('ends_on', document.getElementById('at_ends_on').value);
    (($('#at_employees').val()) || []).forEach(id => fd.append('employee_ids[]', id));
    (($('#at_departments').val()) || []).forEach(id => fd.append('department_ids[]', id));
    (($('#at_cc').val()) || []).forEach(id => fd.append('cc_user_ids[]', id));
    const atFile = document.getElementById('at_attachment').files[0];
    if (atFile) fd.append('attachment', atFile);

    fetchJson("{{ route('task-management.store') }}", {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: fd,
    })
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
        fetchJson('/task-management/' + id, {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
        })
          .then(({ data }) => {
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

      body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>';
      fetch('/task-management/batch/' + batchId, {
        headers: { 'Accept': 'application/json' },
      })
        .then(r => r.json())
        .then(data => {
          body.innerHTML = data.html || '';
          body.dataset.loaded = '1';
        })
        .catch(() => {
          body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Could not load. Try again.</td></tr>';
        });
    });
  });

  // ══ Recurring rows: expand to see every day it has actually fired ════
  document.querySelectorAll('.js-schedule-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const scheduleId = btn.dataset.scheduleId;
      const childRow = document.querySelector('.schedule-history-row[data-schedule-history="' + scheduleId + '"]');
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

      const body = childRow.querySelector('.js-schedule-history-body');
      if (body.dataset.loaded) return;

      body.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>';
      fetch('/task-management/schedules/' + scheduleId + '/history', {
        headers: { 'Accept': 'application/json' },
      })
        .then(r => r.json())
        .then(data => {
          body.innerHTML = data.html || '';
          body.dataset.loaded = '1';
        })
        .catch(() => {
          body.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-3">Could not load. Try again.</td></tr>';
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
        fetchJson("{{ route('task-management.bulkDestroy') }}", {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}",
            'Accept': 'application/json',
          },
          body: JSON.stringify({ ids: ids }),
        })
          .then(({ data }) => {
            if (data.success) { window.location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Nothing deleted', text: data.message || '' }); }
          });
      });
    });
  }

  // ══ View Task (read-only, populated from the row's own data attribute) ═
  const viewModalEl = document.getElementById('viewTaskModal');
  const viewModal = new bootstrap.Modal(viewModalEl);
  // A grouped row's View opens the list of everyone on it.
  document.querySelectorAll('.js-batch-view').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const toggle = document.querySelector('.js-batch-toggle[data-batch-id="' + btn.dataset.batchId + '"]');
      const children = document.querySelector('.batch-children-row[data-batch-children="' + btn.dataset.batchId + '"]');
      if (toggle && children && children.classList.contains('d-none')) toggle.click();
      if (children) children.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
  });
  // Delegated: the per-person View buttons inside an expanded group are
  // added after the page loads.
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-view-task');
    if (!btn) return;
      const t = JSON.parse(btn.dataset.task);
      document.getElementById('vt_title').textContent = t.title;
      document.getElementById('vt_description').textContent = t.description || '—';
      document.getElementById('vt_priority').textContent = t.priority;
      document.getElementById('vt_due').textContent = t.due;
      document.getElementById('vt_expected_label').classList.toggle('d-none', !t.expected);
      document.getElementById('vt_expected').classList.toggle('d-none', !t.expected);
      document.getElementById('vt_expected').textContent = t.expected || '';
      document.getElementById('vt_assignee').textContent = t.assignee;
      document.getElementById('vt_assignedBy').textContent = t.assignedBy;
      document.getElementById('vt_cc_label').classList.toggle('d-none', !t.ccNames);
      document.getElementById('vt_cc').classList.toggle('d-none', !t.ccNames);
      document.getElementById('vt_cc').textContent = t.ccNames || '';
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

      document.getElementById('vt_assignedOn').textContent = t.assignedOn;
      document.getElementById('vt_actualCompletion').textContent = t.actualCompletion;
      const hasTiming = !!t.timingLabel;
      document.getElementById('vt_timing_label').classList.toggle('d-none', !hasTiming);
      document.getElementById('vt_timing_wrap').classList.toggle('d-none', !hasTiming);
      if (hasTiming) {
        const badge = document.getElementById('vt_timing');
        badge.textContent = t.timingLabel;
        badge.className = 'badge-hitech ' + t.timingClass;
      }

      window.__vtThreadUrl = t.threadUrl;
      loadTaskThread(t.threadUrl);

      viewModal.show();
  });

  // Renders each update via DOM APIs (never innerHTML with the comment
  // text itself) since a comment is free text anyone with access to the
  // task could have typed.
  function loadTaskThread(threadUrl) {
    const box = document.getElementById('vt_thread');
    box.innerHTML = '<div class="text-muted small">Loading...</div>';
    fetch(threadUrl, { headers: { 'Accept': 'application/json' } })
      .then(r => r.json().then(data => ({ ok: r.ok, data: data })))
      .then(({ ok, data }) => {
        box.innerHTML = '';
        if (!ok) {
          const err = document.createElement('div');
          err.className = 'text-danger small';
          err.textContent = data.message || 'Could not load updates.';
          box.appendChild(err);
          return;
        }
        if (!data.updates || !data.updates.length) {
          const empty = document.createElement('div');
          empty.className = 'text-muted small';
          empty.textContent = 'No updates yet.';
          box.appendChild(empty);
          return;
        }
        // Rendered as WhatsApp-style bubbles: own messages (is_own) on the
        // right, everyone else's on the left — same convention as my-tasks'
        // own thread view (they share this same JSON shape/endpoint).
        data.updates.forEach(function (u) {
          const row = document.createElement('div');
          row.className = 'chat-bubble-row ' + (u.is_own ? 'is-own' : 'is-other');

          if (!u.is_own) {
            const sender = document.createElement('div');
            sender.className = 'chat-sender';
            sender.textContent = u.created_by;
            row.appendChild(sender);
          }

          const bubble = document.createElement('div');
          bubble.className = 'chat-bubble';

          if (u.comment) {
            const body = document.createElement('div');
            body.textContent = u.comment;
            bubble.appendChild(body);
          }

          if (u.file_url) {
            const link = document.createElement('a');
            link.href = u.file_url;
            link.target = '_blank';
            link.className = 'small d-block';
            link.textContent = 'View attachment';
            bubble.appendChild(link);
          }

          row.appendChild(bubble);

          const time = document.createElement('div');
          time.className = 'chat-time';
          time.textContent = u.created_at;
          row.appendChild(time);

          box.appendChild(row);
        });
        box.scrollTop = box.scrollHeight;
      })
      .catch(() => { box.innerHTML = '<div class="text-danger small">Could not load updates.</div>'; });
  }

  document.getElementById('vt_reply_btn').addEventListener('click', function () {
    const input = document.getElementById('vt_reply_text');
    const comment = input.value.trim();
    if (!comment || !window.__vtThreadUrl) return;

    const replyUrl = window.__vtThreadUrl.replace(/\/thread$/, '/reply');
    const btn = this;
    btn.disabled = true;

    fetchJson(replyUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: JSON.stringify({ comment: comment }),
    })
      .then(({ ok, data }) => {
        btn.disabled = false;
        if (ok && data.success) {
          input.value = '';
          loadTaskThread(window.__vtThreadUrl);
        } else {
          Swal.fire({ icon: 'error', title: 'Could not send', text: data.message || 'Something went wrong.' });
        }
      })
      .catch(() => {
        btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
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

      const ccIds = (btn.dataset.ccIds || '').split(',').filter(Boolean);
      $('#et_cc').val(ccIds).trigger('change');

      // A grouped row: edit everyone's copy by default; reassigning is one
      // person at a time, so the picker hides while that's ticked.
      const batchSize = parseInt(btn.dataset.batchSize || '1', 10);
      document.getElementById('et_batch_wrap').classList.toggle('d-none', batchSize < 2);
      document.getElementById('et_batch_size').textContent = batchSize;
      document.getElementById('et_apply_batch').checked = batchSize > 1;
      refreshEtBatch();

      editModal.show();
    });
  });

  function refreshEtBatch() {
    const applyAll = !document.getElementById('et_batch_wrap').classList.contains('d-none')
      && document.getElementById('et_apply_batch').checked;
    document.getElementById('et_assignee_wrap').classList.toggle('d-none', applyAll);
  }
  document.getElementById('et_apply_batch').addEventListener('change', refreshEtBatch);

  document.getElementById('editTaskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    fetchJson('/task-management/' + document.getElementById('et_id').value, {
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
          if (!document.getElementById('et_assignee_wrap').classList.contains('d-none')) {
            const v = $('#et_assignee').val();
            return (v && v !== etOriginalUserId) ? v : null;
          }
          return null;
        })(),
        cc_user_ids: $('#et_cc').val() || [],
        apply_to_batch: !document.getElementById('et_batch_wrap').classList.contains('d-none')
          && document.getElementById('et_apply_batch').checked,
      }),
    })
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

    fetchJson('/task-management/' + document.getElementById('ot_id').value + '/objection', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: otFd,
    })
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
          fetchJson('/task-management/schedules/' + currentScheduleId + '/remove-user', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': "{{ csrf_token() }}",
              'Accept': 'application/json',
            },
            body: JSON.stringify({ user_id: t.id }),
          })
            .then(({ data }) => {
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
        document.getElementById('msEndsOn').value = btn.dataset.endsOn || '';
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
        fetchJson('/task-management/schedules/' + currentScheduleId + '/stop', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
        })
          .then(({ data }) => {
            if (data.success) { window.location.reload(); }
            else { Swal.fire({ icon: 'error', title: 'Could not stop', text: data.message || '' }); }
          });
      });
    });

    function saveEndDate(value) {
      if (!currentScheduleId) return;
      fetchJson('/task-management/schedules/' + currentScheduleId + '/end-date', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
        body: JSON.stringify({ ends_on: value || null }),
      })
        .then(({ data }) => {
          if (data.success) {
            Swal.fire({ icon: 'success', title: 'Saved', text: data.message, timer: 1800, showConfirmButton: false })
              .then(() => window.location.reload());
          } else {
            Swal.fire({ icon: 'error', title: 'Could not save', text: data.message || '' });
          }
        });
    }
    document.getElementById('msEndsOnSave').addEventListener('click', function () {
      saveEndDate(document.getElementById('msEndsOn').value);
    });
    document.getElementById('msEndsOnClear').addEventListener('click', function () {
      document.getElementById('msEndsOn').value = '';
      saveEndDate(null);
    });
  }
});
</script>
@endsection
