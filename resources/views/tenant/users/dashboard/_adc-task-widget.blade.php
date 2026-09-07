{{-- My Tasks widget — shared across every dashboard (employee, manager, hr,
     admin, finance). Self-contained: computes its own data from
     MyTasksController::assignedTaskBuckets(), the exact same buckets the My
     Tasks page itself uses, so the two never disagree about what's due
     today, overdue or upcoming. Sits near the top of the page (inserted as
     a new section, nothing existing removed) so it's visible without
     scrolling. Fully custom, self-scoped styling below rather than reusing
     the shared .hitech-segmented-control/.status-toggle-btn classes - this
     tab treatment (solid dark active pill) is a different look from that
     shared component, and reusing it led to a real !important fight last
     time (that shared rule has padding !important baked in). Building it
     bespoke here avoids that entirely.

     Width is NOT set here - the including dashboard wraps this in its own
     Bootstrap column (col-xl-6, paired with a real neighboring card) so the
     grid handles half/full width. A hardcoded max-width here previously
     left the paired column empty because nothing else was placed beside
     it - don't reintroduce that. --}}
@php
  $twBuckets = \App\Http\Controllers\tenant\MyTasksController::assignedTaskBuckets(auth()->user());

  // Recurring schedules never generate a Task row until the day they're
  // actually due (see TaskScheduleService::fireIfDue), so a schedule due
  // next Tuesday is otherwise invisible here until Tuesday itself. Preview
  // it in the Upcoming tab instead - these have no id/checkbox/complete
  // button since nothing exists yet to mark complete. Same source the My
  // Tasks page itself uses, so the two never disagree here either.
  $twUpcomingSchedules = \App\Http\Controllers\tenant\MyTasksController::upcomingSchedulePreviews(auth()->user());
@endphp
<style>
  #dashTaskWidget{width:100%;height:100%;box-sizing:border-box;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px 22px;display:flex;flex-direction:column;}
  #dashTaskWidget .dtw-head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:16px;flex-wrap:nowrap;}
  #dashTaskWidget .dtw-head-left{display:flex;align-items:center;gap:8px;min-width:0;}
  #dashTaskWidget .dtw-icon{width:28px;height:28px;border-radius:8px;background:rgba(0,128,128,0.1);color:#008080;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
  #dashTaskWidget .dtw-title{font-weight:700;font-size:.9rem;color:#1e293b;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex-shrink:1;min-width:0;}
  #dashTaskWidget .dtw-tabs{display:inline-flex;background:#f1f5f9;border-radius:50px;padding:2px;gap:1px;flex-shrink:0;}
  #dashTaskWidget .dtw-tab{border:none;background:transparent;color:#64748b;font-size:.64rem;font-weight:600;padding:.32rem .5rem;border-radius:50px;cursor:pointer;white-space:nowrap;transition:all .15s;display:inline-flex;align-items:center;gap:4px;}
  #dashTaskWidget .dtw-tab.active{background:#0d5c56;color:#fff;}
  #dashTaskWidget .dtw-tab:hover:not(.active){color:#0d5c56;}
  #dashTaskWidget .dtw-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:15px;height:15px;padding:0 3px;border-radius:999px;font-size:.6rem;font-weight:700;background:rgba(100,116,139,.15);color:#475569;}
  #dashTaskWidget .dtw-tab.active .dtw-tab-count{background:rgba(255,255,255,.28);color:#fff;}
  #dashTaskWidget .dash-task-pane{flex:1 1 auto;max-height:280px;overflow-y:auto;}
  #dashTaskWidget .dash-task-row{display:flex;align-items:center;gap:12px;padding:12px 2px;border-bottom:1px solid #f1f5f9;}
  #dashTaskWidget .dash-task-row:last-child{border-bottom:none;}
  #dashTaskWidget .dtw-check{width:18px;height:18px;border:1.5px solid #cbd5e1;border-radius:5px;flex-shrink:0;cursor:pointer;accent-color:#0d5c56;}
  #dashTaskWidget .dash-task-title{font-weight:600;font-size:.82rem;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  #dashTaskWidget .dtw-recurring-tag{display:inline-flex;align-items:center;gap:2px;font-size:.62rem;font-weight:600;color:#7c3aed;background:rgba(124,58,237,0.1);padding:.05rem .4rem;border-radius:6px;margin-left:4px;vertical-align:middle;}
  #dashTaskWidget .dash-task-sub{font-size:.72rem;color:#94a3b8;margin-top:1px;}
  #dashTaskWidget .dtw-due{font-size:.75rem;font-weight:600;color:#dc2626;white-space:nowrap;flex-shrink:0;}
  #dashTaskWidget .dtw-due.is-upcoming{color:#64748b;font-weight:500;}
  #dashTaskWidget .dtw-complete-btn{border:1px solid #0d5c56;background:#fff;color:#0d5c56;font-size:.72rem;font-weight:600;padding:.3rem .8rem;border-radius:8px;flex-shrink:0;white-space:nowrap;transition:all .15s;}
  #dashTaskWidget .dtw-complete-btn:hover{background:#0d5c56;color:#fff;}
  #dashTaskWidget .dtw-complete-btn:disabled{opacity:.6;}
  #dashTaskWidget .dash-task-empty{height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#94a3b8;font-size:.8rem;}
  #dashTaskWidget .dtw-viewall{display:inline-flex;align-items:center;gap:4px;font-size:.8rem;font-weight:600;color:#0d5c56;text-decoration:none;margin-top:auto;padding-top:12px;}
  #dashTaskWidget .dtw-viewall:hover{text-decoration:underline;}
</style>
<div id="dashTaskWidget">
  <div class="dtw-head">
    <div class="dtw-head-left">
      <div class="dtw-icon"><i class="bx bx-task"></i></div>
      <p class="dtw-title">My Tasks</p>
    </div>
    <div class="dtw-tabs" id="dashTaskTabs">
      <button type="button" class="dtw-tab active js-dash-task-tab" data-target="#dtDueToday">Due Today<span class="dtw-tab-count">{{ $twBuckets['pending']->count() }}</span></button>
      <button type="button" class="dtw-tab js-dash-task-tab" data-target="#dtOverdue">Overdue<span class="dtw-tab-count">{{ $twBuckets['overdue']->count() }}</span></button>
      <button type="button" class="dtw-tab js-dash-task-tab" data-target="#dtUpcoming">Upcoming<span class="dtw-tab-count">{{ $twBuckets['upcoming']->count() + $twUpcomingSchedules->count() }}</span></button>
    </div>
  </div>

  <div id="dtDueToday" class="dash-task-pane">
    @forelse($twBuckets['pending'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <input type="checkbox" class="dtw-check js-dash-task-complete" data-id="{{ $t->id }}">
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-sub">{{ optional($t->assignedBy)->getFullName() ? 'Assigned by ' . $t->assignedBy->getFullName() : '—' }}</div>
        </div>
        <button type="button" class="dtw-complete-btn js-dash-task-complete" data-id="{{ $t->id }}">Complete</button>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-check-circle fs-3 d-block mb-1 text-success"></i>Nothing due today.</div>
    @endforelse
  </div>

  <div id="dtOverdue" class="dash-task-pane d-none">
    @forelse($twBuckets['overdue'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <input type="checkbox" class="dtw-check js-dash-task-complete" data-id="{{ $t->id }}">
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-sub">{{ optional($t->assignedBy)->getFullName() ? 'Assigned by ' . $t->assignedBy->getFullName() : '—' }}</div>
        </div>
        <div class="dtw-due">{{ $t->due_deadline->diffForHumans(now(), true) }} overdue</div>
        <button type="button" class="dtw-complete-btn js-dash-task-complete" data-id="{{ $t->id }}">Complete</button>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-check-circle fs-3 d-block mb-1 text-success"></i>No overdue tasks.</div>
    @endforelse
  </div>

  <div id="dtUpcoming" class="dash-task-pane d-none">
    @forelse($twBuckets['upcoming'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <span class="dtw-check" style="visibility:hidden;"></span>
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-sub">{{ optional($t->assignedBy)->getFullName() ? 'Assigned by ' . $t->assignedBy->getFullName() : '—' }}</div>
        </div>
        <div class="dtw-due is-upcoming">{{ $t->due_date ? $t->due_date->format('d M Y') : 'No due date' }}</div>
      </div>
    @empty
      @if($twUpcomingSchedules->isEmpty())
        <div class="dash-task-empty"><i class="bx bx-calendar fs-3 d-block mb-1"></i>Nothing upcoming.</div>
      @endif
    @endforelse
    @foreach($twUpcomingSchedules as $s)
      <div class="dash-task-row">
        <span class="dtw-check" style="visibility:hidden;"></span>
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $s->title }} <span class="dtw-recurring-tag"><i class="bx bx-repeat"></i> Recurring</span></div>
          <div class="dash-task-sub">{{ $s->recurrence_label }} · not yet assigned</div>
        </div>
        <div class="dtw-due is-upcoming">{{ $s->due_date->format('d M Y') }}</div>
      </div>
    @endforeach
  </div>

  <a href="{{ url('/my-tasks') }}" class="dtw-viewall">View All Tasks <i class="bx bx-right-arrow-alt"></i></a>
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

    // Checking the box or clicking Complete both mark the task done -
    // whichever the person reaches for first.
    document.querySelectorAll('.js-dash-task-complete').forEach(function (el) {
      var handler = function () {
        var id = el.dataset.id;
        var row = el.closest('.dash-task-row');
        row.querySelectorAll('.js-dash-task-complete').forEach(function (x) { x.disabled = true; });
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
              row.querySelectorAll('.js-dash-task-complete').forEach(function (x) { x.disabled = false; if (x.tagName === 'INPUT') x.checked = false; });
              alert(res.data.message || 'Could not mark this task complete.');
            }
          })
          .catch(function () {
            row.querySelectorAll('.js-dash-task-complete').forEach(function (x) { x.disabled = false; if (x.tagName === 'INPUT') x.checked = false; });
            alert('Network error — please try again.');
          });
      };
      if (el.tagName === 'INPUT') {
        el.addEventListener('change', handler);
      } else {
        el.addEventListener('click', handler);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashTaskWidget);
  } else {
    initDashTaskWidget();
  }
})();
</script>
