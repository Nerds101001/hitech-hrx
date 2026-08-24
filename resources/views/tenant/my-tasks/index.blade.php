@extends('layouts/layoutMaster')

@section('title', 'My Tasks')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/scss/pages/hitech-portal.scss'
  ])
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
      $anyAssignedTask = $taskOpenCount + $taskBuckets['completed']->count() > 0;
    @endphp
    @if($anyAssignedTask)
      <ul class="nav nav-pills hitech-tabs-sm mb-4" id="taskTabs">
        <li class="nav-item">
          <button class="nav-link {{ $taskBuckets['overdue']->count() ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#taskOverdue" type="button">
            <i class="bx bx-error-circle"></i> Overdue <span class="badge badge-hitech-danger badge-hitech ms-1">{{ $taskBuckets['overdue']->count() }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link {{ !$taskBuckets['overdue']->count() ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#taskPending" type="button">
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
      </ul>

      <div class="tab-content p-0 border-0 mb-6">
        @foreach(['overdue' => 'taskOverdue', 'pending' => 'taskPending', 'upcoming' => 'taskUpcoming', 'completed' => 'taskCompleted'] as $bucketKey => $paneId)
          <div class="tab-pane fade {{ ($bucketKey === 'overdue' && $taskBuckets['overdue']->count()) || ($bucketKey === 'pending' && !$taskBuckets['overdue']->count()) ? 'show active' : '' }}" id="{{ $paneId }}">
            @forelse($taskBuckets[$bucketKey] as $t)
              <div class="mt-group" style="--c: {{ $bucketKey === 'overdue' ? '#dc2626' : ($bucketKey === 'completed' ? '#16a34a' : '#0d9488') }}">
                <div class="mt-row">
                  <span>
                    <span class="mt-nm d-block">{{ $t->title }}</span>
                    <span class="mt-mt">
                      Assigned by {{ optional($t->assignedBy)->getFullName() ?? '—' }}
                      @if($t->due_date) &middot; due {{ $t->due_date->format('d M Y') }} @endif
                      @if($bucketKey === 'completed' && $t->completed_at) &middot; completed {{ $t->completed_at->format('d M Y') }} @endif
                    </span>
                    @if($t->description)
                      <span class="mt-mt d-block mt-1">{{ \Illuminate\Support\Str::limit($t->description, 120) }}</span>
                    @endif
                    @if($bucketKey === 'completed' && optional($t->taskUpdates->first())->comment)
                      <span class="mt-mt d-block mt-1 fst-italic">"{{ $t->taskUpdates->first()->comment }}"</span>
                    @endif
                  </span>
                  @if($bucketKey !== 'completed')
                    <button type="button" class="mt-go js-complete-task {{ $bucketKey === 'overdue' ? 'mt-go-danger' : '' }}" data-id="{{ $t->id }}" data-title="{{ $t->title }}">
                      Mark Complete
                    </button>
                  @else
                    <span class="mt-tag">Completed</span>
                  @endif
                </div>
              </div>
            @empty
              <div class="mt-empty py-4">
                <i class="bx bx-check-circle" style="font-size:2rem"></i>
                <p class="mb-0">Nothing here.</p>
              </div>
            @endforelse
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
<div class="modal fade" id="completeTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="completeTaskForm">
        <div class="modal-header">
          <h5 class="modal-title">Mark Complete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="fw-medium mb-3" id="completeTaskTitle"></p>
          <label class="form-label">Remarks (optional)</label>
          <textarea id="completeTaskRemarks" class="form-control" rows="3" placeholder="Anything worth noting about how this was completed..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-hitech-cancel" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn-hitech">Mark Complete</button>
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
  const completeModalEl = document.getElementById('completeTaskModal');
  if (!completeModalEl) return;
  const completeModal = new bootstrap.Modal(completeModalEl);
  let currentTaskId = null;

  document.querySelectorAll('.js-complete-task').forEach(function (btn) {
    btn.addEventListener('click', function () {
      currentTaskId = btn.dataset.id;
      document.getElementById('completeTaskTitle').textContent = btn.dataset.title;
      document.getElementById('completeTaskRemarks').value = '';
      completeModal.show();
    });
  });

  document.getElementById('completeTaskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!currentTaskId) return;
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Saving...';

    fetch('/my-tasks/' + currentTaskId + '/complete', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': "{{ csrf_token() }}",
        'Accept': 'application/json',
      },
      body: JSON.stringify({ remarks: document.getElementById('completeTaskRemarks').value }),
    })
      .then(r => r.json().then(data => ({ ok: r.ok, data })))
      .then(({ ok, data }) => {
        btn.disabled = false;
        btn.innerHTML = original;
        if (ok && data.success) {
          window.location.reload();
        } else {
          Swal.fire({ icon: 'error', title: 'Could not complete', text: data.message || 'Something went wrong.' });
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.innerHTML = original;
        Swal.fire({ icon: 'error', title: 'Network error', text: 'Please try again.' });
      });
  });
});
</script>
@endsection
