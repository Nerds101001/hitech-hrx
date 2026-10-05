@extends('layouts/layoutMaster')

@section('title', 'My Tasks')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
@endsection

@section('vendor-script')
  {{-- Swal.fire() is called on this page's error paths (complete/resubmit)
       but the library was never actually loaded here - the same class of
       bug just fixed on Task Management (Swal called without ever being
       included), just not yet noticed here since a fetch() failure on this
       page is rarer. --}}
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-style')
<style>
  .mt-group{background:#fff;border:1px solid #e6ecf1;border-radius:16px;margin-bottom:18px;overflow:hidden}
  .mt-head{display:flex;align-items:center;gap:12px;padding:16px 20px;border-bottom:1px solid #eef2f6}
  .mt-ic{width:40px;height:40px;flex:0 0 40px;border-radius:11px;display:grid;place-items:center;
    font-size:1.25rem;color:var(--c);background:color-mix(in srgb,var(--c) 12%,#fff)}
  .mt-title{font-size:1rem;font-weight:800;color:#0f172a;margin:0}
  .mt-sub{font-size:.78rem;color:#64748b}
  .mt-count{margin-left:auto;background:#005a5a;color:#fff;font-size:.72rem;font-weight:800;
    padding:3px 11px;border-radius:999px}
  .mt-row{display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid #f4f8fa}
  .mt-row:last-child{border-bottom:none}
  .mt-row:hover{background:#fbfdfe}
  .mt-nm{font-weight:700;color:#0f172a;font-size:.88rem}
  .mt-mt{font-size:.74rem;color:#64748b}
  .mt-tags{display:flex;gap:5px;flex-wrap:wrap;margin-left:auto}
  .mt-tag{font-size:.65rem;font-weight:800;padding:2px 9px;border-radius:999px;
    background:#eef6f6;color:#0f766e;white-space:nowrap}
  .mt-go{background:#005a5a;color:#fff;border-radius:8px;padding:6px 14px;font-size:.76rem;
    font-weight:700;text-decoration:none;white-space:nowrap;border:none}
  .mt-go:hover{background:#00403f;color:#fff}
  .mt-go.mt-go-danger{background:#dc2626}
  .mt-go.mt-go-danger:hover{background:#b91c1c}
  .mt-empty{background:#fff;border:1px solid #e6ecf1;border-radius:16px;padding:56px 20px;text-align:center}
  .mt-empty i{font-size:3rem;color:#0d9488;opacity:.5}
  .mt-empty h5{margin:14px 0 4px;font-weight:800;color:#0f172a}
  .mt-empty p{margin:0;color:#64748b;font-size:.86rem}

  /* Task update thread — WhatsApp-style chat bubbles: own messages right
     (teal, matching --hitech-primary), everyone else's left (white/grey). */
  #threadModalBody{display:flex;flex-direction:column;gap:2px;padding:4px 2px;}
  #threadModalBody .chat-bubble-row{display:flex;flex-direction:column;max-width:78%;margin-bottom:6px;}
  #threadModalBody .chat-bubble-row.is-own{align-self:flex-end;align-items:flex-end;}
  #threadModalBody .chat-bubble-row.is-other{align-self:flex-start;align-items:flex-start;}
  #threadModalBody .chat-sender{font-size:.7rem;font-weight:700;color:#0d5c56;margin:0 4px 2px;}
  #threadModalBody .chat-bubble{border-radius:14px;padding:8px 12px;font-size:.84rem;line-height:1.4;word-break:break-word;box-shadow:0 1px 1px rgba(0,0,0,.04);}
  #threadModalBody .chat-bubble-row.is-own .chat-bubble{background:var(--hitech-primary-light,rgba(0,128,128,.12));color:#0f2f2d;border-bottom-right-radius:4px;}
  #threadModalBody .chat-bubble-row.is-other .chat-bubble{background:#f1f5f9;color:#1e293b;border-bottom-left-radius:4px;}
  #threadModalBody .chat-bubble a{color:inherit;text-decoration:underline;}
  #threadModalBody .chat-time{font-size:.65rem;color:#94a3b8;margin:2px 4px 0;}
</style>
@endsection

@section('content')
<div class="layout-full-width animate__animated animate__fadeIn">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 mx-4 py-2">
    <div>
      <h3 class="fw-extrabold mb-0 text-dark">My Tasks</h3>
      <p class="text-muted mb-0 small">Everything waiting on you, in one place.</p>
    </div>
    @php
      $taskOpenCount = $taskBuckets['overdue']->count() + $taskBuckets['pending']->count() + $taskBuckets['upcoming']->count();
      $total = collect($groups)->sum(fn($g) => count($g['items'])) + $taskOpenCount;
    @endphp
    <span class="badge {{ $total ? 'bg-label-warning' : 'bg-label-success' }} fs-6 px-3 py-2">
      {{ $total ? $total . ' pending' : 'All caught up' }}
    </span>
  </div>

  <div class="px-4">

    {{-- ===================== Assigned Tasks ===================== --}}
    @php
      $anyAssignedTask = $taskOpenCount + $taskBuckets['completed']->count() + $upcomingSchedules->count() + $taskBuckets['watching']->count() > 0;
      $priorityBadge = ['low' => 'badge-muted', 'medium' => 'badge-brand-soft', 'high' => 'badge-hitech-danger'];
    @endphp
    @if($anyAssignedTask)
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <ul class="nav nav-pills hitech-tabs-sm mb-0" id="taskTabs">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#taskAll" type="button">
              <i class="bx bx-list-ul"></i> All <span class="badge badge-brand-soft badge-hitech ms-1">{{ $taskBuckets['all']->count() }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taskOverdue" type="button">
              <i class="bx bx-error-circle"></i> Overdue <span class="badge badge-hitech-danger badge-hitech ms-1">{{ $taskBuckets['overdue']->count() }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taskPending" type="button">
              <i class="bx bx-time-five"></i> Due Today <span class="badge badge-brand-soft badge-hitech ms-1">{{ $taskBuckets['pending']->count() }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taskUpcoming" type="button">
              <i class="bx bx-calendar"></i> Upcoming <span class="badge badge-muted badge-hitech ms-1">{{ $taskBuckets['upcoming']->count() }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taskCompleted" type="button">
              <i class="bx bx-check-circle"></i> Completed <span class="badge badge-hitech-success badge-hitech ms-1">{{ $taskBuckets['completed']->count() }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#taskWatching" type="button" title="Tasks you assigned to someone else, or are CC'd on">
              <i class="bx bx-show"></i> Assigned <span class="badge badge-muted badge-hitech ms-1">{{ $taskBuckets['watching']->count() }}</span>
            </button>
          </li>
        </ul>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <input type="search" class="form-control form-control-sm" id="taskSearch" style="min-width:220px;"
                 placeholder="Search task, person, remark..." aria-label="Search tasks">
          <div style="min-width:160px">
          <select class="form-select form-select-sm" id="taskPriorityFilter" aria-label="Filter by priority">
            <option value="">All priorities</option>
            @foreach(\App\Models\Task::PRIORITIES as $p)
              <option value="{{ $p }}">{{ ucfirst($p) }} priority</option>
            @endforeach
          </select>
          </div>
        </div>
      </div>
      @php
        // Sort/search keys on each row - read by the small script at the
        // bottom (click a column header to sort, type to search).
        $rowKeys = function ($t) {
            $latest = $t->taskUpdates->first();
            return [
                'search'   => mb_strtolower(implode(' ', array_filter([$t->title, $t->description, optional($t->user)->getFullName(),
                    optional($t->assignedBy)->getFullName(), $t->ccUsers->map(fn ($u) => $u->getFullName())->implode(' '), optional($latest)->comment]))),
                'title'    => mb_strtolower($t->title),
                'priority' => ['high' => 3, 'medium' => 2, 'low' => 1][$t->priority] ?? 0,
                'due'      => $t->due_date ? $t->due_date->timestamp : 99999999999,
                'by'       => mb_strtolower(optional($t->assignedBy)->getFullName() ?? ''),
                'to'       => mb_strtolower(optional($t->user)->getFullName() ?? ''),
                'status'   => ['objection' => 0, 'new' => 1, 'in_progress' => 2][$t->status] ?? 3,
            ];
        };
        $sortTh = fn (string $key, string $label, string $extra = '') => '<th class="js-sort ' . $extra . '" data-key="' . $key . '" role="button" tabindex="0" style="cursor:pointer;white-space:nowrap;">'
            . e($label) . ' <i class="bx bx-sort-alt-2 text-muted opacity-50"></i></th>';
      @endphp

      @php
        $timingBadge = ['early' => 'badge-hitech-success', 'on_time' => 'badge-brand-soft', 'late' => 'badge-hitech-danger'];
        $timingLabel = ['early' => 'Completed early', 'on_time' => 'Completed on time', 'late' => 'Completed late'];
      @endphp
      <div class="tab-content p-0 border-0 mb-6">
        @foreach(['all' => 'taskAll', 'overdue' => 'taskOverdue', 'pending' => 'taskPending', 'upcoming' => 'taskUpcoming', 'completed' => 'taskCompleted', 'watching' => 'taskWatching'] as $bucketKey => $paneId)
          <div class="tab-pane fade {{ $bucketKey === 'all' ? 'show active' : '' }}" id="{{ $paneId }}">
            @if($bucketKey === 'watching')
              {{-- Someone else's work this person is tracking - CC'd on it,
                   or they're the one who assigned it - watch status and
                   reply, no Complete action, it isn't theirs to finish. --}}
              @if($taskBuckets['watching']->isNotEmpty())
                <div class="hitech-card">
                  <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                      <thead class="table-light">
                        <tr>
                          {!! $sortTh('title', 'Task') !!}
                          {!! $sortTh('to', 'Assigned To') !!}
                          {!! $sortTh('by', 'Assigned By') !!}
                          {!! $sortTh('priority', 'Priority') !!}
                          {!! $sortTh('due', 'Due') !!}
                          {!! $sortTh('status', 'Status', 'text-end') !!}
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($taskBuckets['watching'] as $t)
                          @php $k = $rowKeys($t); $latest = $t->taskUpdates->first(); @endphp
                          <tr data-priority="{{ $t->priority }}" data-search="{{ $k['search'] }}" data-sort-title="{{ $k['title'] }}" data-sort-to="{{ $k['to'] }}"
                              data-sort-by="{{ $k['by'] }}" data-sort-priority="{{ $k['priority'] }}" data-sort-due="{{ $k['due'] }}" data-sort-status="{{ $k['status'] }}">
                            <td>
                              <div class="fw-medium">{{ $t->title }}</div>
                              @if($t->description)
                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($t->description, 90) }}</small>
                              @endif
                              @if($latest && $latest->comment)
                                <small class="text-muted d-block fst-italic"><i class="bx bx-message-rounded-dots"></i> {{ optional($latest->createdBy)->getFullName() ?? '—' }}: {{ \Illuminate\Support\Str::limit($latest->comment, 120) }}</small>
                              @endif
                              @if($t->ccUsers->isNotEmpty())
                                <small class="text-muted d-block"><i class="bx bx-user-voice"></i> CC: {{ $t->ccUsers->map(fn ($u) => $u->getFullName())->implode(', ') }}</small>
                              @endif
                            </td>
                            <td><small class="text-muted">{{ optional($t->user)->getFullName() ?? '—' }}</small></td>
                            <td><small class="text-muted">{{ optional($t->assignedBy)->getFullName() ?? '—' }}</small></td>
                            <td><span class="badge-hitech {{ $priorityBadge[$t->priority] ?? 'badge-muted' }}">{{ ucfirst($t->priority) }}</span></td>
                            <td class="text-nowrap">
                              {{ $t->dueLabel() ?? '—' }}
                              @if($t->is_overdue)
                                <span class="badge-hitech badge-hitech-danger d-block mt-1" style="width:fit-content;">Overdue</span>
                              @endif
                            </td>
                            <td class="text-end">
                              <span class="badge-hitech {{ $t->is_completed ? 'badge-hitech-success' : ($t->status === 'objection' ? 'badge-hitech-danger' : 'badge-brand-soft') }} d-block mb-1" style="width:fit-content;margin-left:auto;">
                                {{ $t->status === 'objection' ? 'Objection raised' : (\App\Models\Task::PROGRESS_STATUSES[$t->status] ?? ucfirst($t->status)) }}
                              </span>
                              <button type="button" class="mt-go js-view-thread" data-url="{{ route('my-tasks.thread', $t->id) }}" data-title="{{ $t->title }}">
                                View &amp; Reply
                              </button>
                            </td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                </div>
              @else
                <div class="mt-empty py-4">
                  <i class="bx bx-check-circle" style="font-size:2rem"></i>
                  <p class="mb-0">Nothing assigned by you, and nothing you're CC'd on.</p>
                </div>
              @endif
            @elseif($taskBuckets[$bucketKey]->isNotEmpty())
              <div class="hitech-card">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        {!! $sortTh('title', 'Task') !!}
                        {!! $sortTh('priority', 'Priority') !!}
                        {!! $sortTh('due', 'Due') !!}
                        {!! $sortTh('by', 'Assigned By') !!}
                        {!! $sortTh('status', 'Status', 'text-end') !!}
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($taskBuckets[$bucketKey] as $t)
                        @php $k = $rowKeys($t); @endphp
                        <tr data-priority="{{ $t->priority }}" data-search="{{ $k['search'] }}" data-sort-title="{{ $k['title'] }}" data-sort-by="{{ $k['by'] }}"
                            data-sort-priority="{{ $k['priority'] }}" data-sort-due="{{ $k['due'] }}" data-sort-status="{{ $k['status'] }}">
                          <td>
                            <div class="fw-medium">
                              {{ $t->title }}
                              @if($t->attachment_path)
                                <a href="{{ route('tasks.attachment', $t->id) }}" target="_blank" class="ms-1 text-muted" title="View attached reference image">
                                  <i class="bx bx-image"></i>
                                </a>
                              @endif
                            </div>
                            @if($t->description)
                              <small class="text-muted">{{ \Illuminate\Support\Str::limit($t->description, 90) }}</small>
                            @endif
                            @if($t->status !== 'objection' && optional($t->taskUpdates->first())->comment)
                              <small class="text-muted d-block fst-italic"><i class="bx bx-message-rounded-dots"></i> {{ optional($t->taskUpdates->first()->createdBy)->getFullName() ?? '—' }}: {{ \Illuminate\Support\Str::limit($t->taskUpdates->first()->comment, 120) }}</small>
                            @endif
                            @if($t->ccUsers->isNotEmpty())
                              <small class="text-muted d-block"><i class="bx bx-user-voice"></i> CC: {{ $t->ccUsers->map(fn ($u) => $u->getFullName())->implode(', ') }}</small>
                            @endif
                            @if($t->is_completed && optional($t->taskUpdates->first())->file_url)
                              <a href="{{ route('task-updates.attachment', $t->taskUpdates->first()->id) }}" target="_blank" class="small text-decoration-none d-block">
                                <i class="bx bx-image"></i> View completion photo
                              </a>
                            @endif
                            @if($t->status === 'objection' && $t->objection_notes)
                              <small class="text-danger d-block mt-1"><i class="bx bx-error"></i> Sent back: {{ $t->objection_notes }}</small>
                              @if(optional($t->taskUpdates->first())->file_url)
                                <a href="{{ route('task-updates.attachment', $t->taskUpdates->first()->id) }}" target="_blank" class="small text-decoration-none d-block">
                                  <i class="bx bx-image"></i> View objection photo
                                </a>
                              @endif
                            @endif
                          </td>
                          <td><span class="badge-hitech {{ $priorityBadge[$t->priority] ?? 'badge-muted' }}">{{ ucfirst($t->priority) }}</span></td>
                          <td class="text-nowrap">
                            {{ $t->dueLabel() ?? '—' }}
                            @if($t->is_overdue)
                              <span class="badge-hitech badge-hitech-danger d-block mt-1" style="width:fit-content;">Overdue</span>
                            @endif
                          </td>
                          <td><small class="text-muted">{{ optional($t->assignedBy)->getFullName() ?? '—' }}</small></td>
                          <td class="text-end">
                            @if($t->status === 'objection')
                              <button type="button" class="mt-go mt-go-danger js-resubmit-task" data-id="{{ $t->id }}" data-title="{{ $t->title }}">
                                Fix &amp; Resubmit
                              </button>
                            @elseif(!$t->is_completed)
                              @if($t->completion_expected_date)
                                <small class="text-muted d-block mb-1">Expects {{ $t->completion_expected_date->format('d M Y') }}</small>
                              @endif
                              <button type="button" class="mt-go js-status-task {{ $t->is_overdue ? 'mt-go-danger' : '' }}"
                                      data-id="{{ $t->id }}" data-title="{{ $t->title }}" data-status="{{ $t->status }}"
                                      data-expected="{{ $t->completion_expected_date?->format('Y-m-d') }}">
                                Update Status
                              </button>
                            @else
                              <small class="text-muted d-block">{{ $t->completed_at ? $t->completed_at->format('d M Y') : '—' }}</small>
                              @if($t->completion_timing)
                                <span class="badge-hitech {{ $timingBadge[$t->completion_timing] }}">{{ $timingLabel[$t->completion_timing] }}</span>
                              @endif
                            @endif
                            <button type="button" class="mt-go js-view-thread mt-1" style="background:#fff;color:#005a5a;border:1px solid #cfe3e3;"
                                    data-url="{{ route('my-tasks.thread', $t->id) }}" data-title="{{ $t->title }}">
                              Updates
                            </button>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @elseif($bucketKey !== 'upcoming' || $upcomingSchedules->isEmpty())
              <div class="mt-empty py-4">
                <i class="bx bx-check-circle" style="font-size:2rem"></i>
                <p class="mb-0">Nothing here.</p>
              </div>
            @endif

            @if($bucketKey === 'upcoming' && $upcomingSchedules->isNotEmpty())
              {{-- Recurring schedules only ever create their Task row on the
                   day they're actually due, never in advance - these rows
                   preview that a schedule will fire, they are not real
                   assigned tasks (no id, no Mark Complete action). --}}
              <div class="hitech-card mt-3">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th style="min-width:220px">Recurring Task</th>
                        <th>Cadence</th>
                        <th>Next Due</th>
                        <th class="text-end" style="min-width:190px">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($upcomingSchedules as $s)
                        <tr>
                          <td><div class="fw-medium">{{ $s->title }}</div></td>
                          <td><small class="text-muted">{{ $s->recurrence_label }}</small></td>
                          <td class="text-nowrap">{{ $s->due_date->format('d M Y') }}</td>
                          <td class="text-end"><span class="badge-hitech badge-muted">Not yet assigned</span></td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            @endif
          </div>
        @endforeach
      </div>
    @endif

    {{-- ===================== Other outstanding items ===================== --}}
    @forelse($groups as $g)
      <div class="mt-group" style="--c: {{ $g['tone'] === 'danger' ? '#dc2626' : '#0d9488' }}">
        <div class="mt-head">
          <span class="mt-ic"><i class="bx {{ $g['icon'] }}"></i></span>
          <span>
            <h6 class="mt-title">{{ $g['title'] }}</h6>
            <span class="mt-sub">{{ $g['sub'] }}</span>
          </span>
          <span class="mt-count">{{ count($g['items']) }}</span>
        </div>

        @foreach($g['items'] as $item)
          <div class="mt-row">
            <span>
              <span class="mt-nm d-block">{{ $item['title'] }}</span>
              <span class="mt-mt">{{ $item['meta'] }}</span>
            </span>
            <span class="mt-tags">
              @foreach($item['tags'] as $tag)
                <span class="mt-tag">{{ $tag }}</span>
              @endforeach
            </span>
            @if(!empty($item['modal']))
              <button type="button" class="mt-go js-setup-task"
                      data-id="{{ $item['modal']['id'] }}"
                      data-name="{{ $item['modal']['name'] }}"
                      data-meta="{{ $item['modal']['meta'] }}"
                      data-emp="{{ $item['modal']['emp'] }}"
                      data-needs-emp="{{ $item['modal']['needs_emp'] }}"
                      data-needs-bio="{{ $item['modal']['needs_bio'] }}"
                      style="border:none">Complete</button>
            @else
              <a href="{{ $item['url'] }}" class="mt-go">Complete</a>
            @endif
          </div>
        @endforeach
      </div>
    @empty
      @if(!$anyAssignedTask)
        <div class="mt-empty">
          <i class="bx bx-check-circle"></i>
          <h5>Nothing pending</h5>
          <p>You have no tasks waiting. Anything assigned to you will appear here.</p>
        </div>
      @endif
    @endforelse
  </div>
</div>

{{-- Mark Complete modal --}}
<div class="modal fade" id="statusTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="statusTaskForm">
        <div class="modal-header">
          <h5 class="modal-title">Update Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="fw-medium mb-3" id="statusTaskTitle"></p>

          <label class="form-label">Status</label>
          <div class="btn-group w-100 mb-3" role="group">
            @foreach(\App\Models\Task::PROGRESS_STATUSES as $key => $label)
              <input type="radio" class="btn-check" name="statusTaskStatus" id="statusTaskStatus_{{ $key }}" value="{{ $key }}" @if($key === 'new') checked @endif>
              <label class="btn btn-outline-hitech" for="statusTaskStatus_{{ $key }}">{{ $label }}</label>
            @endforeach
          </div>

          <div id="statusTaskExpectedWrap">
            <label class="form-label">Expected completion date (optional)</label>
            <input type="date" id="statusTaskExpected" class="form-control mb-3">
            <div class="form-text mb-3">You'll get a reminder that day if this isn't marked complete yet.</div>
          </div>

          <label class="form-label">Remarks (optional)</label>
          <textarea id="statusTaskRemarks" class="form-control mb-3" rows="3" placeholder="Anything worth noting..."></textarea>
          <div id="statusTaskAttachmentWrap">
            <label class="form-label">Attach a photo (optional)</label>
            <input type="file" id="statusTaskAttachment" class="form-control" accept="image/png,image/jpeg,image/webp">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-hitech">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Task detail: full update thread + reply — shared by "Updates" on an
     owned task and "View & Reply" on a watched (CC'd) one. --}}
<div class="modal fade" id="threadModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="threadModalTitle">Updates</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="background:#f8fafc;">
        {{-- Who, when, CC - the popup used to show only the messages. --}}
        <dl class="row small mb-3 d-none" id="threadTaskInfo"></dl>
        <div id="threadModalBody" style="min-height:120px;">
          <div class="text-muted small">Loading...</div>
        </div>
      </div>
      <div class="modal-footer" style="display:block;">
        <div class="input-group input-group-sm">
          <input type="text" id="threadReplyText" class="form-control" placeholder="Add a reply or follow-up...">
          <button type="button" class="btn btn-hitech" id="threadReplyBtn">Send</button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Fix & Resubmit modal - the only way out of an 'objection' status --}}
<div class="modal fade" id="resubmitTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="resubmitTaskForm">
        <div class="modal-header">
          <h5 class="modal-title">Resubmit</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="fw-medium mb-3" id="resubmitTaskTitle"></p>
          <label class="form-label">What did you fix? (optional)</label>
          <textarea id="resubmitTaskRemarks" class="form-control mb-3" rows="3" placeholder="What changed since the objection was raised..."></textarea>
          <label class="form-label">Attach a photo (optional)</label>
          <input type="file" id="resubmitTaskAttachment" class="form-control" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-hitech">Resubmit</button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('tenant.employees._setup-modal')
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

  // Search + priority filter, across every tab (the Assigned tab included -
  // its rows used to carry no priority, so the filter never touched them).
  const priorityFilter = document.getElementById('taskPriorityFilter');
  const taskSearch = document.getElementById('taskSearch');
  function applyTaskFilters() {
    const want = priorityFilter ? priorityFilter.value : '';
    const q = taskSearch ? taskSearch.value.trim().toLowerCase() : '';
    document.querySelectorAll('.tab-content tr[data-search]').forEach(function (row) {
      const show = (!want || row.dataset.priority === want) && (!q || row.dataset.search.indexOf(q) !== -1);
      row.style.display = show ? '' : 'none';
    });
  }
  if (priorityFilter) priorityFilter.addEventListener('change', applyTaskFilters);
  if (taskSearch) taskSearch.addEventListener('input', applyTaskFilters);

  // Click a column header to sort that table; click again to reverse.
  function sortBy(th) {
    const key = th.dataset.key;
    const tbody = th.closest('table').querySelector('tbody');
    const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
    th.closest('tr').querySelectorAll('.js-sort').forEach(function (h) {
      delete h.dataset.dir;
      const i = h.querySelector('i'); if (i) i.className = 'bx bx-sort-alt-2 text-muted opacity-50';
    });
    th.dataset.dir = dir;
    const icon = th.querySelector('i'); if (icon) icon.className = 'bx ' + (dir === 'asc' ? 'bx-sort-up' : 'bx-sort-down');
    const attr = 'sort' + key.charAt(0).toUpperCase() + key.slice(1);
    const rows = Array.from(tbody.querySelectorAll('tr[data-search]'));
    rows.sort(function (a, b) {
      const x = a.dataset[attr] || '', y = b.dataset[attr] || '';
      const nx = parseFloat(x), ny = parseFloat(y);
      const cmp = (!isNaN(nx) && !isNaN(ny) && String(nx) === x && String(ny) === y) ? nx - ny : x.localeCompare(y);
      return dir === 'asc' ? cmp : -cmp;
    });
    rows.forEach(function (r) { tbody.appendChild(r); });
  }
  document.querySelectorAll('.js-sort').forEach(function (th) {
    th.addEventListener('click', function () { sortBy(th); });
    th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sortBy(th); } });
  });

  const statusModalEl = document.getElementById('statusTaskModal');
  if (!statusModalEl) return;
  const statusModal = new bootstrap.Modal(statusModalEl);
  let currentTaskId = null;

  function toggleStatusExtras() {
    const status = document.querySelector('input[name="statusTaskStatus"]:checked').value;
    document.getElementById('statusTaskExpectedWrap').classList.toggle('d-none', status === 'completed');
    document.getElementById('statusTaskAttachmentWrap').classList.toggle('d-none', status !== 'completed');
  }
  document.querySelectorAll('input[name="statusTaskStatus"]').forEach(r => r.addEventListener('change', toggleStatusExtras));

  document.querySelectorAll('.js-status-task').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentTaskId = btn.dataset.id;
      document.getElementById('statusTaskTitle').textContent = btn.dataset.title;
      document.getElementById('statusTaskStatus_' + (btn.dataset.status === 'completed' ? 'completed' : (btn.dataset.status === 'in_progress' ? 'in_progress' : 'new'))).checked = true;
      document.getElementById('statusTaskExpected').value = btn.dataset.expected || '';
      document.getElementById('statusTaskRemarks').value = '';
      document.getElementById('statusTaskAttachment').value = '';
      toggleStatusExtras();
      statusModal.show();
    });
  });

  document.getElementById('statusTaskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!currentTaskId) return;
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    const status = document.querySelector('input[name="statusTaskStatus"]:checked').value;

    // Completed carries a possible photo, so it goes through the original
    // multipart /complete endpoint (unchanged, still what the dashboard
    // widget's own quick Complete button hits too). Pending/Partially Done
    // never take a photo, so they go through /status as plain JSON.
    if (status === 'completed') {
      const fd = new FormData();
      fd.append('remarks', document.getElementById('statusTaskRemarks').value);
      const file = document.getElementById('statusTaskAttachment').files[0];
      if (file) fd.append('attachment', file);

      fetchJson('/my-tasks/' + currentTaskId + '/complete', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
        body: fd,
      })
        .then(({ ok, data }) => {
          btn.disabled = false; btn.innerHTML = original;
          if (ok && data.success) { window.location.reload(); }
          else { Swal.fire({ icon: 'error', title: 'Could not save', text: data.message || 'Something went wrong.' }); }
        })
        .catch(() => {
          btn.disabled = false; btn.innerHTML = original;
          Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
        });
      return;
    }

    fetchJson('/my-tasks/' + currentTaskId + '/status', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        status: status,
        completion_expected_date: document.getElementById('statusTaskExpected').value || null,
        remarks: document.getElementById('statusTaskRemarks').value || null,
      }),
    })
      .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = original;
        if (ok && data.success) {
          window.location.reload();
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

  // ══ Task detail thread: shared by "Updates" and "View & Reply" ═══════
  const threadModalEl = document.getElementById('threadModal');
  const threadModal = new bootstrap.Modal(threadModalEl);
  let threadUrl = null;

  function renderTaskInfo(task) {
    const info = document.getElementById('threadTaskInfo');
    info.innerHTML = '';
    if (!task) { info.classList.add('d-none'); return; }
    [['Assigned to', task.assignee], ['Assigned by', task.assigned_by], ['Due', task.due_date], ['Priority', task.priority],
     ['Status', task.status_label], ['Assignee expects', task.completion_expected_date],
     ['CC', (task.cc_users || []).join(', ')], ['Description', task.description]].forEach(function (pair) {
      if (!pair[1]) return;
      const dt = document.createElement('dt'); dt.className = 'col-4 col-md-3 text-muted fw-normal'; dt.textContent = pair[0];
      const dd = document.createElement('dd'); dd.className = 'col-8 col-md-9 mb-1'; dd.textContent = pair[1];
      info.appendChild(dt); info.appendChild(dd);
    });
    info.classList.remove('d-none');
  }

  function loadThread() {
    const box = document.getElementById('threadModalBody');
    box.innerHTML = '<div class="text-muted small">Loading...</div>';
    renderTaskInfo(null);
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
        renderTaskInfo(data.task);
        if (!data.updates || !data.updates.length) {
          const empty = document.createElement('div');
          empty.className = 'text-muted small';
          empty.textContent = 'No updates yet.';
          box.appendChild(empty);
          return;
        }
        // DOM APIs throughout, never innerHTML with the comment text
        // itself - it's free text anyone with access to the task could
        // have typed. Rendered as WhatsApp-style bubbles: own messages
        // (is_own) on the right, everyone else's on the left.
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

  document.querySelectorAll('.js-view-thread').forEach(function (btn) {
    btn.addEventListener('click', function () {
      threadUrl = btn.dataset.url;
      document.getElementById('threadModalTitle').textContent = btn.dataset.title;
      document.getElementById('threadReplyText').value = '';
      loadThread();
      threadModal.show();
    });
  });

  document.getElementById('threadReplyBtn').addEventListener('click', function () {
    const input = document.getElementById('threadReplyText');
    const comment = input.value.trim();
    if (!comment || !threadUrl) return;

    const replyUrl = threadUrl.replace(/\/thread$/, '/reply');
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
          loadThread();
        } else {
          Swal.fire({ icon: 'error', title: 'Could not send', text: data.message || 'Something went wrong.' });
        }
      })
      .catch(() => {
        btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
      });
  });

  const resubmitModalEl = document.getElementById('resubmitTaskModal');
  if (resubmitModalEl) {
    const resubmitModal = new bootstrap.Modal(resubmitModalEl);
    let resubmitTaskId = null;

    document.querySelectorAll('.js-resubmit-task').forEach(function (btn) {
      btn.addEventListener('click', function () {
        resubmitTaskId = btn.dataset.id;
        document.getElementById('resubmitTaskTitle').textContent = btn.dataset.title;
        document.getElementById('resubmitTaskRemarks').value = '';
        document.getElementById('resubmitTaskAttachment').value = '';
        resubmitModal.show();
      });
    });

    document.getElementById('resubmitTaskForm').addEventListener('submit', function (e) {
      e.preventDefault();
      if (!resubmitTaskId) return;
      const btn = e.target.querySelector('button[type="submit"]');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = 'Sending...';

      const resubmitFd = new FormData();
      resubmitFd.append('remarks', document.getElementById('resubmitTaskRemarks').value);
      const resubmitFile = document.getElementById('resubmitTaskAttachment').files[0];
      if (resubmitFile) resubmitFd.append('attachment', resubmitFile);

      fetchJson('/my-tasks/' + resubmitTaskId + '/resubmit', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': "{{ csrf_token() }}",
          'Accept': 'application/json',
        },
        body: resubmitFd,
      })
        .then(({ ok, data }) => {
          btn.disabled = false;
          btn.innerHTML = original;
          if (ok && data.success) {
            window.location.reload();
          } else {
            Swal.fire({ icon: 'error', title: 'Could not resubmit', text: data.message || 'Something went wrong.' });
          }
        })
        .catch(() => {
          btn.disabled = false;
          btn.innerHTML = original;
          Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
        });
    });
  }
});
</script>
@endsection
