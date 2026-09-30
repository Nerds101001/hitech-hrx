{{-- Shared dashboard runtime: animated count-up on load + live auto-refresh of
     volatile stats. Include once inside @section('content') after the markup. --}}
<script>
(function () {
  // ---------- Count-up animation ----------
  function countUp(el, dur) {
    dur = dur || 950;
    var raw = el.getAttribute('data-adc-raw');
    if (raw === null) { raw = (el.textContent || '').trim(); el.setAttribute('data-adc-raw', raw); }
    var m = raw.match(/-?\d[\d,]*\.?\d*/);
    if (!m) return;
    var numStr = m[0];
    var target = parseFloat(numStr.replace(/,/g, ''));
    if (!isFinite(target)) return;
    var decimals = (numStr.split('.')[1] || '').length;
    var hasComma = numStr.indexOf(',') >= 0;
    var prefix = raw.slice(0, m.index), suffix = raw.slice(m.index + numStr.length);
    var t0 = null;
    function fmt(v) {
      var s = decimals > 0 ? v.toFixed(decimals) : Math.round(v).toString();
      if (hasComma) s = Number(v).toLocaleString('en-IN', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
      return prefix + s + suffix;
    }
    function step(ts) { if (!t0) t0 = ts; var t = Math.min(1, (ts - t0) / dur); var e = 1 - Math.pow(1 - t, 3); el.textContent = fmt(target * e); if (t < 1) requestAnimationFrame(step); else el.textContent = raw; }
    requestAnimationFrame(step);
  }
  function animateAll() {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    document.querySelectorAll('.adc-kpi-value, .adc-asset-num, .adc-ev-count').forEach(function (el) { countUp(el); });
  }

  // ---------- Live auto-refresh ----------
  var pill = null;
  function setSync(on) { if (pill) pill.classList.toggle('syncing', !!on); }
  function updateVal(el, val) {
    val = String(val);
    if (el.getAttribute('data-adc-raw') === val || (el.textContent || '').trim() === val) return;
    el.setAttribute('data-adc-raw', val);
    countUp(el, 650);
    var card = el.closest('.adc-kpi') || el;
    card.classList.remove('adc-live-flash'); void card.offsetWidth; card.classList.add('adc-live-flash');
  }
  function poll() {
    setSync(true);
    fetch('{{ url("/dashboard/live-stats") }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { setSync(false); if (!d) return; Object.keys(d).forEach(function (k) { document.querySelectorAll('[data-live="' + k + '"]').forEach(function (el) { updateVal(el, d[k]); }); }); })
      .catch(function () { setSync(false); });
  }
  // ---------- Inline approve / reject ----------
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var APPROVAL_MAP = {
    Leave:    { url: 'leaveRequests/actionAjax',      ok: 'approved', no: 'rejected', reason: 'adminNotes' },
    'Outdoor Duty': { url: 'leaveRequests/outdoorDuty/actionAjax', ok: 'approved', no: 'rejected', reason: 'adminNotes' },
    Expense:  { url: 'expenseRequests/actionAjax',    ok: 'approved', no: 'rejected', reason: 'adminRemarks' },
    Document: { url: 'documentmanagement/actionAjax', ok: 'Approved', no: 'Rejected', reason: 'admin_remarks' },
    Loan:     { url: 'loan/actionAjax',               ok: 'Approved', no: 'Rejected', reason: 'admin_remarks' }
  };
  function decApprovals() {
    document.querySelectorAll('[data-approval-count]').forEach(function (el) {
      var n = parseInt((el.textContent || '').replace(/[^\d]/g, ''), 10);
      if (isFinite(n) && n > 0) el.textContent = (el.textContent || '').replace(/\d+/, String(n - 1));
    });
  }
  function doApproval(li, type, id, decide) {
    var cfg = APPROVAL_MAP[type]; if (!cfg) return;
    var btns = li.querySelectorAll('.adc-act button'); btns.forEach(function (b) { b.disabled = true; });
    var body = new URLSearchParams();
    body.set('_token', CSRF); body.set('id', id);
    body.set('status', decide === 'ok' ? cfg.ok : cfg.no);
    body.set(cfg.reason, decide === 'ok' ? 'Approved from dashboard' : 'Rejected from dashboard');
    fetch('{{ url("/") }}/' + cfg.url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin', body: body.toString()
    }).then(function (r) { return r.ok; }).then(function (ok) {
      if (ok) { li.classList.add('adc-removing'); setTimeout(function () { li.remove(); decApprovals(); }, 350); }
      else { btns.forEach(function (b) { b.disabled = false; }); li.style.background = 'rgba(220,38,38,.08)'; setTimeout(function () { li.style.background = ''; }, 900); }
    }).catch(function () { btns.forEach(function (b) { b.disabled = false; }); });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.adc-act button'); if (!btn) return;
    var li = btn.closest('[data-approve]'); if (!li) return;
    e.preventDefault();
    doApproval(li, li.getAttribute('data-type'), li.getAttribute('data-id'), btn.getAttribute('data-act') === 'approve' ? 'ok' : 'no');
  });

  document.addEventListener('DOMContentLoaded', function () {
    animateAll();
    pill = document.querySelector('.adc-live');
    if (pill && document.querySelector('[data-live]')) { setInterval(poll, 45000); }
  });
})();
</script>
