{{--
    Shared "record the IDs" dialog.

    Any element with class .js-setup-task opens it, carrying the task on data
    attributes, so the same dialog serves My Tasks, the setup queue page and
    anywhere else the queue is surfaced. Clicks are delegated, so rows added
    after load still work.
--}}
<div class="modal fade" id="setupTaskModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border:0;border-radius:18px;overflow:hidden">

      <div style="background:linear-gradient(135deg,#003d3d,#0d9488);color:#fff;padding:20px 24px">
        <h5 class="mb-0 fw-bold" style="font-size:1.05rem">
          <i class="bx bx-id-card me-2"></i>Attendance Setup
        </h5>
        <p class="mb-0" style="font-size:.8rem;opacity:.85">
          Record the IDs so payroll and attendance can begin.
        </p>
      </div>

      <div class="d-flex align-items-center gap-2 px-4 py-3"
           style="border-bottom:1px solid #eef2f6;background:#fbfdfe">
        <span class="avatar avatar-sm">
          <span class="avatar-initial rounded-circle bg-label-primary" id="stmInit">?</span>
        </span>
        <span>
          <span class="fw-bold d-block" id="stmName">—</span>
          <small class="text-muted" id="stmMeta">—</small>
        </span>
      </div>

      <div class="p-4">
        <div class="alert alert-danger py-2 px-3 d-none" id="stmErr" style="font-size:.82rem"></div>

        <div class="mb-3" id="stmEmpWrap">
          <label class="form-label fw-semibold" style="font-size:.78rem">
            Employee ID <span class="text-danger">*</span>
          </label>
          <input type="text" class="form-control" id="stmEmp" placeholder="e.g. 6321">
          <small class="text-muted">Confirm or replace the provisional ID. Must be unique.</small>
        </div>

        <div class="mb-3" id="stmBioWrap">
          <label class="form-label fw-semibold" style="font-size:.78rem">
            Biometric ID <span class="text-danger">*</span>
          </label>
          <input type="text" class="form-control" id="stmBio" placeholder="Device enrolment number">
        </div>

        <div class="alert alert-success py-2 px-3 d-none" id="stmExempt" style="font-size:.8rem">
          <i class="bx bx-check-shield me-1"></i>
          Biometric enrolment is not required for this employee's department.
        </div>
      </div>

      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="stmSave">Save &amp; mark done</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var el = document.getElementById('setupTaskModal');
  if (!el) return;

  // This partial sits inside the page content block, which the layout renders
  // BEFORE it includes the script bundle — so Bootstrap does not exist yet.
  // Resolve the modal lazily on first click instead of bailing out here, which
  // is what silently killed the button.
  // (Never write a Blade directive literally in a comment here: Blade compiles
  // it anyway and it would open a stray section.)
  var _m = null;
  function modalInstance() {
    if (_m) return _m;
    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return null;
    _m = bootstrap.Modal.getOrCreateInstance(el);
    return _m;
  }

  var empWrap = document.getElementById('stmEmpWrap'),
      bioWrap = document.getElementById('stmBioWrap'),
      exempt  = document.getElementById('stmExempt'),
      emp     = document.getElementById('stmEmp'),
      bio     = document.getElementById('stmBio'),
      err     = document.getElementById('stmErr'),
      save    = document.getElementById('stmSave'),
      current = null;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-setup-task');
    if (!btn) return;
    e.preventDefault();

    current = btn.dataset.id;

    document.getElementById('stmName').textContent = btn.dataset.name || 'Employee';
    document.getElementById('stmMeta').textContent = btn.dataset.meta || '';
    document.getElementById('stmInit').textContent = (btn.dataset.name || '?')
      .split(' ').filter(Boolean).slice(0, 2).map(function (w) { return w[0]; }).join('').toUpperCase();

    var needEmp = btn.dataset.needsEmp === '1',
        needBio = btn.dataset.needsBio === '1';

    empWrap.classList.toggle('d-none', !needEmp);
    bioWrap.classList.toggle('d-none', !needBio);
    exempt.classList.toggle('d-none', needBio);

    emp.value = btn.dataset.emp || '';
    bio.value = '';
    err.classList.add('d-none');
    save.disabled = false;
    save.textContent = 'Save & mark done';

    var m = modalInstance();
    if (m) {
      m.show();
    } else {
      // Bootstrap genuinely unavailable — show it anyway rather than
      // swallowing the click.
      el.classList.add('show');
      el.style.display = 'block';
      document.body.classList.add('modal-open');
    }
  });

  // Works with or without Bootstrap's own dismiss handling.
  el.addEventListener('click', function (e) {
    if (e.target.closest('[data-bs-dismiss="modal"]') && !_m) {
      el.classList.remove('show');
      el.style.display = 'none';
      document.body.classList.remove('modal-open');
    }
  });

  save.addEventListener('click', function () {
    if (!current) return;

    err.classList.add('d-none');
    save.disabled = true;
    save.textContent = 'Saving…';

    var payload = {};
    if (!empWrap.classList.contains('d-none')) payload.emp_id = emp.value.trim();
    if (!bioWrap.classList.contains('d-none')) payload.biometric_id = bio.value.trim();

    fetch("{{ url('/employee-setup') }}/" + current + "/complete", {
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
        if (res.d.errors) msg = Object.values(res.d.errors).flat().join(' ');
        err.textContent = msg;
        err.classList.remove('d-none');
        save.disabled = false;
        save.textContent = 'Save & mark done';
        return;
      }
      window.location.reload();
    })
    .catch(function () {
      err.textContent = 'Network error. Please try again.';
      err.classList.remove('d-none');
      save.disabled = false;
      save.textContent = 'Save & mark done';
    });
  });
})();
</script>
