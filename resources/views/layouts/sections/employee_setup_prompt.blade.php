{{--
    Attendance-setup prompt for the accounts team.

    Lives in the LAYOUT, not in any dashboard blade, on purpose: the live
    server runs the older dashboard while local runs the redesigned one, and
    this has to behave identically on both. Being layout-level also satisfies
    "show it on every login until it is done" — it appears on any page, not
    just the dashboard.

    Renders nothing at all for anyone who is not accounts/admin/HR, and
    nothing when the queue is clear.
--}}
@php
    $__setupUser = auth()->user();
    $__setupShow = $__setupUser
        && $__setupUser->hasRole(['accounts', 'admin', 'Admin', 'hr', 'HR'])
        && \Illuminate\Support\Facades\Schema::hasTable('employee_setup_tasks');

    $__setupTasks = collect();
    if ($__setupShow) {
        try {
            $__setupTasks = \App\Models\EmployeeSetupTask::pending()
                ->with(['user.department', 'user.designation'])
                ->orderBy('created_at')
                ->get();
        } catch (\Throwable $e) {
            $__setupTasks = collect();
        }
    }

    // Show once per LOGIN. The previous sessionStorage flag survived logout,
    // so signing in again in the same browser silently suppressed the popup.
    // A server session key is cleared when the session is regenerated at
    // login, so it reappears exactly once for every sign-in.
    $__setupAutoOpen = false;
    if ($__setupShow && $__setupTasks->isNotEmpty() && !session('esp_prompt_shown')) {
        session(['esp_prompt_shown' => true]);
        $__setupAutoOpen = true;
    }
@endphp

@if($__setupShow && $__setupTasks->isNotEmpty())
@php $__first = $__setupTasks->first(); @endphp

<style>
  .esp-fab{position:fixed;right:22px;bottom:22px;z-index:1045;background:#005a5a;color:#fff;
    border:none;border-radius:999px;padding:12px 20px;font-size:.84rem;font-weight:700;
    box-shadow:0 10px 26px rgba(0,90,90,.32);cursor:pointer;display:flex;align-items:center;gap:9px}
  .esp-fab:hover{background:#00403f}
  .esp-fab .esp-count{background:#f59e0b;color:#111;border-radius:999px;padding:1px 9px;font-size:.74rem;font-weight:800}
  .esp-modal .modal-content{border:0;border-radius:18px;overflow:hidden}
  .esp-head{background:linear-gradient(135deg,#003d3d,#0d9488);color:#fff;padding:20px 24px}
  .esp-head h5{margin:0;font-weight:800;font-size:1.05rem}
  .esp-head p{margin:4px 0 0;font-size:.8rem;opacity:.85}
  .esp-person{display:flex;align-items:center;gap:12px;padding:15px 24px;border-bottom:1px solid #eef2f6;background:#fbfdfe}
  .esp-av{width:44px;height:44px;border-radius:50%;background:#0d9488;color:#fff;display:grid;
    place-items:center;font-weight:800;font-size:.82rem;flex:0 0 44px;object-fit:cover}
  .esp-nm{font-weight:800;color:#0f172a;font-size:.95rem}
  .esp-mt{font-size:.75rem;color:#64748b}
  .esp-body{padding:20px 24px}
  .esp-lbl{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#475569;margin-bottom:5px}
  .esp-inp{width:100%;padding:10px 13px;border:1px solid #dbe3ea;border-radius:9px;font-size:.9rem}
  .esp-inp:focus{outline:none;border-color:#0d9488}
  .esp-hint{font-size:.72rem;color:#94a3b8;margin-top:4px}
  .esp-exempt{font-size:.74rem;color:#0f766e;background:#f0fdfa;border:1px solid #ccfbf1;
    border-radius:8px;padding:9px 12px;margin-top:6px}
  .esp-err{display:none;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;
    border-radius:9px;padding:10px 13px;font-size:.8rem;margin-bottom:14px}
  .esp-queue{font-size:.74rem;color:#64748b;padding:0 24px 16px}
</style>

{{-- No floating button: the queue lives in My Tasks and on the dashboard.
     This partial now only raises the once-per-login prompt. --}}
<div class="modal fade esp-modal" id="espModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="esp-head">
        <h5><i class="bx bx-id-card me-2"></i>Attendance Setup Pending</h5>
        <p>New joiners need their IDs recorded before payroll and attendance can start.</p>
      </div>

      <div class="esp-person">
        @if($__first->user && $__first->user->getProfilePicture())
          <img src="{{ $__first->user->getProfilePicture() }}" class="esp-av" alt="">
        @else
          <span class="esp-av">{{ optional($__first->user)->getInitials() ?? '?' }}</span>
        @endif
        <span>
          <span class="esp-nm">{{ optional($__first->user)->getFullName() ?? 'Employee' }}</span>
          <span class="esp-mt d-block">
            {{ optional(optional($__first->user)->designation)->name ?? '—' }}
            @if(optional(optional($__first->user)->department)->name)
              &middot; {{ $__first->user->department->name }}
            @endif
          </span>
        </span>
      </div>

      <div class="esp-body">
        <div class="esp-err" id="espErr"></div>

        @if($__first->needs_emp_id)
          <div class="mb-3">
            <div class="esp-lbl">Employee ID <span class="text-danger">*</span></div>
            <input type="text" class="esp-inp" id="espEmpId"
                   value="{{ optional($__first->user)->code }}" placeholder="e.g. 6321">
            <div class="esp-hint">Confirm or replace the provisional ID. Must be unique.</div>
          </div>
        @endif

        @if($__first->needs_biometric)
          <div class="mb-2">
            <div class="esp-lbl">Biometric ID <span class="text-danger">*</span></div>
            <input type="text" class="esp-inp" id="espBioId" placeholder="Device enrolment number">
          </div>
        @else
          <div class="esp-exempt">
            <i class="bx bx-check-shield me-1"></i>
            Biometric enrolment is not required for this employee's department.
          </div>
        @endif
      </div>

      <div class="esp-queue">
        {{ $__setupTasks->count() }} employee{{ $__setupTasks->count() === 1 ? '' : 's' }} awaiting setup.
        <a href="{{ url('/employee-setup') }}">View the full list</a>
      </div>

      <div class="modal-footer border-0 pt-0">
        <a href="{{ url('/my-tasks') }}" class="btn btn-label-secondary me-auto">All my tasks</a>
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Later</button>
        <button type="button" class="btn btn-primary" id="espSave"
                data-id="{{ $__first->id }}">Save &amp; mark done</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var modalEl = document.getElementById('espModal');
  if (!modalEl || typeof bootstrap === 'undefined') return;

  var modal = new bootstrap.Modal(modalEl);

  // Whether this is the first page of a fresh login is decided server-side,
  // so the prompt appears exactly once per sign-in. Delayed slightly so it
  // does not fight the celebrations/policy modals for focus.
  @if($__setupAutoOpen)
    setTimeout(function () {
      if (!document.querySelector('.modal.show')) { modal.show(); }
    }, 900);
  @endif

  document.getElementById('espSave').addEventListener('click', function () {
    var btn = this, err = document.getElementById('espErr');
    var emp = document.getElementById('espEmpId');
    var bio = document.getElementById('espBioId');

    err.style.display = 'none';
    btn.disabled = true;
    btn.textContent = 'Saving…';

    var payload = {};
    if (emp) payload.emp_id = emp.value.trim();
    if (bio) payload.biometric_id = bio.value.trim();

    fetch("{{ url('/employee-setup') }}/" + btn.dataset.id + "/complete", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
      },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
    .then(function (res) {
      if (!res.ok || res.d.success === false) {
        var msg = res.d.message || 'Could not save.';
        if (res.d.errors) { msg = Object.values(res.d.errors).flat().join(' '); }
        err.textContent = msg;
        err.style.display = 'block';
        btn.disabled = false;
        btn.textContent = 'Save & mark done';
        return;
      }
      // Reload so the next pending employee is presented.
      window.location.reload();
    })
    .catch(function () {
      err.textContent = 'Network error. Please try again.';
      err.style.display = 'block';
      btn.disabled = false;
      btn.textContent = 'Save & mark done';
    });
  });
})();
</script>
@endif
