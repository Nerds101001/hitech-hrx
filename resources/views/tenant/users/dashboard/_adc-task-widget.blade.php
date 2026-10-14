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

  /* Measured contributing to the dashboard's 393/444px overflow: 4
     non-shrinking, non-wrapping tab pills (.dtw-tabs flex-shrink:0, each
     .dtw-tab white-space:nowrap) forced wider than the card at phone width.
     Scrolls within the card instead of pushing the card/page wider. */
  @media (max-width: 480px) {
    #dashTaskWidget .dtw-tabs{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  }
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
      <button type="button" class="dtw-tab js-dash-task-tab" data-target="#dtWatching" title="Tasks you assigned to someone else, or are CC'd on">Assigned<span class="dtw-tab-count">{{ $twBuckets['watching']->count() }}</span></button>
    </div>
  </div>

  <div id="dtDueToday" class="dash-task-pane">
    @forelse($twBuckets['pending'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <span class="dtw-check" style="visibility:hidden;"></span>
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-sub">{{ optional($t->assignedBy)->getFullName() ? 'Assigned by ' . $t->assignedBy->getFullName() : '—' }}</div>
        </div>
        <button type="button" class="dtw-complete-btn js-dash-task-status" data-id="{{ $t->id }}" data-title="{{ $t->title }}">Update Status</button>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-check-circle fs-3 d-block mb-1 text-success"></i>Nothing due today.</div>
    @endforelse
  </div>

  <div id="dtOverdue" class="dash-task-pane d-none">
    @forelse($twBuckets['overdue'] as $t)
      <div class="dash-task-row" data-task-row="{{ $t->id }}">
        <span class="dtw-check" style="visibility:hidden;"></span>
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-sub">{{ optional($t->assignedBy)->getFullName() ? 'Assigned by ' . $t->assignedBy->getFullName() : '—' }}</div>
        </div>
        <div class="dtw-due">{{ $t->due_deadline->diffForHumans(now(), true) }} overdue</div>
        <button type="button" class="dtw-complete-btn js-dash-task-status" data-id="{{ $t->id }}" data-title="{{ $t->title }}">Update Status</button>
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
        <div class="dtw-due is-upcoming">{{ $t->dueLabel() ?? 'No due date' }}</div>
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

  <div id="dtWatching" class="dash-task-pane d-none">
    @forelse($twBuckets['watching'] as $t)
      @php $iAssignedThis = (int) $t->assigned_by_id === (int) auth()->id(); @endphp
      <div class="dash-task-row">
        <span class="dtw-check" style="visibility:hidden;"></span>
        <div class="flex-grow-1" style="min-width:0;">
          <div class="dash-task-title">{{ $t->title }}</div>
          <div class="dash-task-sub">
            @if($iAssignedThis)
              Assigned to {{ optional($t->user)->getFullName() ?? '—' }}
            @else
              Assigned to {{ optional($t->user)->getFullName() ?? '—' }} by {{ optional($t->assignedBy)->getFullName() ?? '—' }}
            @endif
          </div>
        </div>
        <div class="dtw-due {{ $t->is_completed ? 'is-upcoming' : '' }}">{{ \App\Models\Task::PROGRESS_STATUSES[$t->status] ?? ucfirst($t->status) }}</div>
      </div>
    @empty
      <div class="dash-task-empty"><i class="bx bx-show fs-3 d-block mb-1"></i>Nothing assigned by you, and nothing you're CC'd on.</div>
    @endforelse
  </div>

  <a href="{{ url('/my-tasks') }}" class="dtw-viewall">View All Tasks <i class="bx bx-right-arrow-alt"></i></a>
</div>

{{-- Update Status modal — same Pending/Partially Done/Completed choice,
     expected date and remarks as the full My Tasks page's own status modal.
     A self-contained copy (own ids, prefixed dashTaskStatus) rather than a
     shared include, since this widget is dropped into whichever dashboard
     includes it and can't assume that page also carries my-tasks/index's
     modal. Replaces the old one-click "Complete" button, which saved with
     no remarks and no real status choice at all. --}}
<div class="modal fade" id="dashTaskStatusModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="dashTaskStatusForm">
        <div class="modal-header">
          <h5 class="modal-title">Update Status</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="fw-medium mb-3" id="dashTaskStatusTitle"></p>

          <label class="form-label">Status</label>
          <div class="btn-group w-100 mb-3" role="group">
            @foreach(\App\Models\Task::PROGRESS_STATUSES as $key => $label)
              <input type="radio" class="btn-check" name="dashTaskStatusValue" id="dashTaskStatus_{{ $key }}" value="{{ $key }}" @if($key === 'new') checked @endif>
              <label class="btn btn-outline-hitech" for="dashTaskStatus_{{ $key }}">{{ $label }}</label>
            @endforeach
          </div>

          <div id="dashTaskStatusExpectedWrap">
            <label class="form-label">Expected completion date (optional)</label>
            <input type="date" id="dashTaskStatusExpected" class="form-control mb-3">
            <div class="form-text mb-3">You'll get a reminder that day if this isn't marked complete yet.</div>
          </div>

          <label class="form-label">Remarks (optional)</label>
          <textarea id="dashTaskStatusRemarks" class="form-control mb-3" rows="3" placeholder="Anything worth noting..."></textarea>
          <div id="dashTaskStatusAttachmentWrap">
            <label class="form-label">Attach a photo (optional)</label>
            <input type="file" id="dashTaskStatusAttachment" class="form-control" accept="image/png,image/jpeg,image/webp">
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

    // A stale session (token expired) surfaced as a raw "CSRF token
    // mismatch" error dialog rather than what it actually is - reload
    // bounces through auth middleware straight to login instead, same fix
    // already applied on the My Tasks / Task Management pages themselves.
    function dashFetchJson(url, opts) {
      return fetch(url, opts).then(function (r) {
        if (r.status === 419) {
          window.location.reload();
          return new Promise(function () {}); // page is navigating away
        }
        return r.json().then(function (data) { return { ok: r.ok, data: data }; });
      });
    }

    var dashStatusModalEl = document.getElementById('dashTaskStatusModal');
    var dashStatusModal = dashStatusModalEl && window.bootstrap ? new bootstrap.Modal(dashStatusModalEl) : null;
    var dashCurrentTaskId = null;

    function toggleDashStatusExtras() {
      var checked = document.querySelector('input[name="dashTaskStatusValue"]:checked');
      var status = checked ? checked.value : 'new';
      document.getElementById('dashTaskStatusExpectedWrap').classList.toggle('d-none', status === 'completed');
      document.getElementById('dashTaskStatusAttachmentWrap').classList.toggle('d-none', status !== 'completed');
    }
    document.querySelectorAll('input[name="dashTaskStatusValue"]').forEach(function (r) {
      r.addEventListener('change', toggleDashStatusExtras);
    });

    // Opens the same Pending/Partially Done/Completed popup the full My
    // Tasks page uses, instead of marking the task done in one click with
    // no remarks and no real status choice.
    document.querySelectorAll('.js-dash-task-status').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!dashStatusModal) return;
        dashCurrentTaskId = btn.dataset.id;
        document.getElementById('dashTaskStatusTitle').textContent = btn.dataset.title;
        document.getElementById('dashTaskStatus_new').checked = true;
        document.getElementById('dashTaskStatusExpected').value = '';
        document.getElementById('dashTaskStatusRemarks').value = '';
        document.getElementById('dashTaskStatusAttachment').value = '';
        toggleDashStatusExtras();
        dashStatusModal.show();
      });
    });

    var dashStatusForm = document.getElementById('dashTaskStatusForm');
    if (dashStatusForm) {
      dashStatusForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!dashCurrentTaskId) return;
        var btn = e.target.querySelector('button[type="submit"]');
        var original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Saving...';

        var status = document.querySelector('input[name="dashTaskStatusValue"]:checked').value;

        function done(ok, data) {
          btn.disabled = false;
          btn.innerHTML = original;
          if (ok && data.success) {
            // Status (and possibly the task's tab) changed underneath this
            // widget - reload so counts/tabs/rows all stay in sync, same as
            // the full My Tasks page does after either endpoint.
            window.location.reload();
          } else {
            alert((data && data.message) || 'Could not save.');
          }
        }

        if (status === 'completed') {
          var fd = new FormData();
          fd.append('remarks', document.getElementById('dashTaskStatusRemarks').value);
          var file = document.getElementById('dashTaskStatusAttachment').files[0];
          if (file) fd.append('attachment', file);

          dashFetchJson('/my-tasks/' + dashCurrentTaskId + '/complete', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
            body: fd,
          })
            .then(function (res) { done(res.ok, res.data); })
            .catch(function () { done(false, { message: 'Network error — please try again.' }); });
          return;
        }

        dashFetchJson('/my-tasks/' + dashCurrentTaskId + '/status', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': "{{ csrf_token() }}",
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            status: status,
            completion_expected_date: document.getElementById('dashTaskStatusExpected').value || null,
            remarks: document.getElementById('dashTaskStatusRemarks').value || null,
          }),
        })
          .then(function (res) { done(res.ok, res.data); })
          .catch(function () { done(false, { message: 'Network error — please try again.' }); });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashTaskWidget);
  } else {
    initDashTaskWidget();
  }
})();
</script>
