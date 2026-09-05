{{-- My Tasks widget — shared across every dashboard (employee, manager, hr,
     admin, finance). Self-contained: computes its own data from
     MyTasksController::assignedTaskBuckets(), the exact same buckets the My
     Tasks page itself uses, so the two never disagree about what's due
     today, overdue or upcoming. Sits near the top of the page (inserted as
     a new section, nothing existing removed) so it's visible without
     scrolling, per how it was asked for. --}}
@php
  $twBuckets = \App\Http\Controllers\tenant\MyTasksController::assignedTaskBuckets(auth()->user());
  $twPriorityBadge = ['high' => 'badge-hitech-danger', 'medium' => 'badge-brand-soft', 'low' => 'badge-muted'];
@endphp
<style>
  #dashTaskWidget .dash-task-pane{max-height:280px;overflow-y:auto;}
  #dashTaskWidget .dash-task-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 4px;border-bottom:1px solid rgba(0,0,0,.05);}
  #dashTaskWidget .dash-task-row:last-child{border-bottom:none;}
  #dashTaskWidget .dash-task-title{font-weight:600;font-size:.85rem;color:#1e293b;}
  #dashTaskWidget .dash-task-meta{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:3px;}
  #dashTaskWidget .dash-task-empty{text-align:center;color:#94a3b8;font-size:.85rem;padding:28px 0;}
</style>
<div class="adc-card mt-1" id="dashTaskWidget">
  <div class="adc-card-head">
    <div><h6 class="adc-card-title"><i class="bx bx-task"></i> My Tasks</h6></div>
    <a href="{{ url('/my-tasks') }}" class="adc-link">View All</a>
  </div>
  <div class="hitech-segmented-control mb-3" id="dashTaskTabs">
    <button class="btn btn-sm status-toggle-btn active js-dash-task-tab" data-target="#dtDueToday" type="button">
      Due Today <span class="badge-hitech badge-brand-soft ms-1">{{ $twBuckets['pending']->count() }}</span>
    </button>
    <button class="btn btn-sm status-toggle-btn js-dash-task-tab" data-target="#dtOverdue" type="button">
      Overdue <span class="badge-hitech badge-hitech-danger ms-1">{{ $twBuckets['overdue']->count() }}</span>
    </button>
    <button class="btn btn-sm status-toggle-btn js-dash-task-tab" data-target="#dtUpcoming" type="button">
      Upcoming <span class="badge-hitech badge-muted ms-1">{{ $twBuckets['upcoming']->count() }}</span>
    </button>
  </div>

  <div id="dtDueToday" class="dash-task-pane">
    @forelse($twBuckets['pending'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <div class="flex-grow-1">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-meta">
            <span class="badge-hitech {{ $twPriorityBadge[$t->priority] ?? 'badge-muted' }}">{{ ucfirst($t->priority) }}</span>
            <small class="text-muted">Assigned by {{ optional($t->assignedBy)->getFullName() ?? '—' }}</small>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-hitech js-dash-task-complete" data-id="{{ $t->id }}">Mark Complete</button>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-check-circle fs-3 d-block mb-1 text-success"></i>Nothing due today.</div>
    @endforelse
  </div>

  <div id="dtOverdue" class="dash-task-pane d-none">
    @forelse($twBuckets['overdue'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <div class="flex-grow-1">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-meta">
            <span class="badge-hitech {{ $twPriorityBadge[$t->priority] ?? 'badge-muted' }}">{{ ucfirst($t->priority) }}</span>
            <span class="badge-hitech badge-hitech-danger">{{ $t->due_date->diffForHumans(now(), true) }} overdue</span>
            <small class="text-muted">Assigned by {{ optional($t->assignedBy)->getFullName() ?? '—' }}</small>
          </div>
        </div>
        <button type="button" class="btn btn-sm btn-hitech js-dash-task-complete" data-id="{{ $t->id }}">Mark Complete</button>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-check-circle fs-3 d-block mb-1 text-success"></i>No overdue tasks.</div>
    @endforelse
  </div>

  <div id="dtUpcoming" class="dash-task-pane d-none">
    @forelse($twBuckets['upcoming'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <div class="flex-grow-1">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-meta">
            <span class="badge-hitech {{ $twPriorityBadge[$t->priority] ?? 'badge-muted' }}">{{ ucfirst($t->priority) }}</span>
            <small class="text-muted">{{ $t->due_date ? 'Due ' . $t->due_date->format('d M Y') : 'No due date' }} · Assigned by {{ optional($t->assignedBy)->getFullName() ?? '—' }}</small>
          </div>
        </div>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-calendar fs-3 d-block mb-1"></i>Nothing upcoming.</div>
    @endforelse
  </div>
</div>

<script>
(function () {
  if (window.__dashTaskWidgetBound) return;
  window.__dashTaskWidgetBound = true;

  function initDashTaskWidget() {
    document.querySelectorAll('.js-dash-task-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.js-dash-task-tab').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.querySelectorAll('#dashTaskWidget .dash-task-pane').forEach(function (p) { p.classList.add('d-none'); });
        var target = document.querySelector(btn.dataset.target);
        if (target) target.classList.remove('d-none');
      });
    });

    document.querySelectorAll('.js-dash-task-complete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.dataset.id;
        var row = btn.closest('.dash-task-row');
        btn.disabled = true;
        btn.textContent = 'Saving...';
        fetch('/my-tasks/' + id + '/complete', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}",
            'Accept': 'application/json',
          },
          body: JSON.stringify({}),
        })
          .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
          .then(function (res) {
            if (res.ok && res.data.success) {
              row.style.transition = 'opacity .2s';
              row.style.opacity = '0';
              setTimeout(function () { row.remove(); }, 200);
            } else {
              btn.disabled = false;
              btn.textContent = 'Mark Complete';
              alert(res.data.message || 'Could not mark this task complete.');
            }
          })
          .catch(function () {
            btn.disabled = false;
            btn.textContent = 'Mark Complete';
            alert('Network error — please try again.');
          });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashTaskWidget);
  } else {
    initDashTaskWidget();
  }
})();
</script>
