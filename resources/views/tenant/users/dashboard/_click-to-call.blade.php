{{--
    Click-to-call dialpad.

    Renders nothing unless the signed-in user is mapped to a dialer agent in
    config/access.php. The button being hidden is presentation only — every
    route re-checks the same mapping.

    The dialer speaks plain HTTP and HRX is served over HTTPS, so the browser
    must not call it directly: mixed content is blocked. This posts to HRX and
    HRX relays each request.

    Vanilla JS — @vite defers jQuery, so a jQuery call at parse time here would
    throw before anything binds.
--}}
@php
    $__ctcAgent = app(\App\Services\ClickToCallService::class)->agentFor(auth()->user());
@endphp

@if($__ctcAgent)
  <button type="button" class="ctc-launch" data-bs-toggle="modal" data-bs-target="#clickToCallModal"
          title="Place a call through Smart Agent">
    <i class="bx bx-phone-call"></i>
    <span>Click to Call</span>
  </button>

  <div class="modal fade" id="clickToCallModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content ctc-modal">
        <div class="ctc-head">
          <div>
            <div class="ctc-title"><i class="bx bx-phone-call me-1"></i> Click to Call</div>
            <div class="ctc-sub">
              @if(($__ctcAgent['vendor'] ?? null) === 'convoque')
                Extension {{ $__ctcAgent['extension'] }}
              @else
                Agent {{ $__ctcAgent['agent'] }} · campaign {{ $__ctcAgent['campaign'] }}
              @endif
            </div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-3">
          <label class="form-label fw-bold small text-uppercase text-muted mb-1">Phone number</label>
          <input type="tel" id="ctcPhone" class="form-control ctc-input" inputmode="numeric"
                 placeholder="e.g. 7889041267" autocomplete="off">
          <div class="ctc-hint">Your agent phone rings first, then the customer.</div>

          <div id="ctcResult" class="ctc-result d-none"></div>

          <button type="button" id="ctcDial" class="ctc-btn ctc-dial">
            <i class="bx bx-phone-call me-1"></i> Call
          </button>

          {{-- Revealed once a call is actually placed. --}}
          <div id="ctcLive" class="d-none">
            <button type="button" id="ctcHangup" class="ctc-btn ctc-hangup">
              <i class="bx bx-phone-off me-1"></i> Hang Up
            </button>

            {{--
              Convoque has no disposition concept at all — confirmed against
              AKOM's own API doc, every endpoint checked, nothing like
              CentroVision's DISPOSE action exists. Not "not wired up yet"
              the way Hang Up currently is: this is permanent for this
              vendor, so the section is hidden rather than shown empty with
              a "Loading..." that never resolves to anything.
            --}}
            @if(($__ctcAgent['vendor'] ?? null) !== 'convoque')
              <hr class="my-3">

              <label class="form-label fw-bold small text-uppercase text-muted mb-1">Disposition</label>
              <select id="ctcDisposition" class="form-select ctc-select">
                <option value="">Loading...</option>
              </select>

              <div id="ctcSubWrap" class="mt-2 d-none">
                <label class="form-label fw-bold small text-uppercase text-muted mb-1">Sub-disposition</label>
                <select id="ctcSubDisposition" class="form-select ctc-select"></select>
              </div>

              <button type="button" id="ctcDispose" class="ctc-btn ctc-dispose">
                <i class="bx bx-check-circle me-1"></i> Save Disposition
              </button>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  <style>
    .ctc-launch {
      display: inline-flex; align-items: center; gap: .4rem;
      padding: .5rem .95rem; border: none; border-radius: 999px;
      background: linear-gradient(135deg, #065f46, #0d7377);
      color: #fff; font-size: .78rem; font-weight: 700; line-height: 1;
      box-shadow: 0 4px 12px rgba(13, 115, 119, .28);
      transition: filter .18s ease, transform .12s ease;
    }
    .ctc-launch:hover  { filter: brightness(1.08); }
    .ctc-launch:active { transform: scale(.97); }
    .ctc-launch i      { font-size: 1rem; }

    .ctc-modal { border: none; border-radius: 16px; overflow: hidden; }
    .ctc-head {
      display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem;
      padding: .85rem 1rem; background: linear-gradient(135deg, #065f46, #0d7377); color: #fff;
    }
    .ctc-title { font-size: .9rem; font-weight: 800; }
    .ctc-sub   { font-size: .68rem; color: rgba(255,255,255,.8); margin-top: 2px; }

    .ctc-input, .ctc-select {
      border: 1px solid #e2e8f0; border-radius: 10px;
      font-size: .85rem; font-weight: 600;
    }
    .ctc-input { height: 46px; font-size: 1.05rem; font-weight: 700; letter-spacing: .04em; }
    .ctc-input:focus, .ctc-select:focus {
      border-color: #0d7377; box-shadow: 0 0 0 .18rem rgba(13,115,119,.15);
    }
    .ctc-hint { font-size: .68rem; color: #94a3b8; margin-top: .35rem; }

    .ctc-btn {
      width: 100%; margin-top: .9rem; padding: .6rem; border: none; border-radius: 10px;
      color: #fff; font-weight: 800; font-size: .85rem; transition: filter .18s ease;
    }
    .ctc-btn:hover:not(:disabled) { filter: brightness(1.1); }
    .ctc-btn:disabled { opacity: .65; }
    .ctc-dial    { background: #0d7377; }
    .ctc-hangup  { background: #b91c1c; }
    .ctc-dispose { background: #065f46; }

    .ctc-result {
      margin-top: .8rem; padding: .55rem .7rem; border-radius: 10px;
      font-size: .74rem; font-weight: 600; line-height: 1.4;
    }
    .ctc-detail { margin-top: .25rem; font-weight: 600; opacity: .75; font-size: .68rem; }
    .ctc-result.ok   { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .ctc-result.warn { background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; }
    .ctc-result.bad  { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
  </style>

  <script>
    (function () {
      var el = function (id) { return document.getElementById(id); };
      var phone   = el('ctcPhone'),   dialBtn = el('ctcDial'),    result  = el('ctcResult');
      var live    = el('ctcLive'),    hangBtn = el('ctcHangup'),  dispBtn = el('ctcDispose');
      var dispSel = el('ctcDisposition'), subSel = el('ctcSubDisposition'), subWrap = el('ctcSubWrap');
      if (!phone || !dialBtn) return;

      /* The button sits inside .emp-hero, which is position:relative with
         overflow:hidden — nesting the dialog there let the card clip it, so it
         appeared squashed into the hero instead of centred over the page.
         A Bootstrap modal has to be a direct child of <body>; move it there
         once, before anything opens it. */
      var modalEl = el('clickToCallModal');
      if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
      }

      var meta  = document.querySelector('meta[name="csrf-token"]');
      var token = meta ? meta.getAttribute('content') : '';

      var URLS = {
        dial:  '{{ route('user.click-to-call.dial') }}',
        hang:  '{{ route('user.click-to-call.hangup') }}',
        disp:  '{{ route('user.click-to-call.dispose') }}',
        list:  '{{ route('user.click-to-call.dispositions') }}',
        subs:  '{{ route('user.click-to-call.sub-dispositions') }}'
      };

      // 'success' is the only outcome that changed anything; the dialer's other
      // replies are things the agent can act on themselves.
      var TONE = {
        success: 'ok', busy: 'warn', not_logged_in: 'warn', wrong_mode: 'warn',
        same_mode: 'warn', not_idle: 'warn', not_on_call: 'warn'
      };

      // textContent throughout, never innerHTML: 'detail' is the dialer's own
      // words and the dialer is a third-party box.
      function show(cls, text, detail) {
        result.classList.remove('d-none');
        result.className = 'ctc-result ' + cls;
        result.textContent = '';

        var line = document.createElement('div');
        line.textContent = text;
        result.appendChild(line);

        if (detail) {
          var small = document.createElement('div');
          small.className = 'ctc-detail';
          small.textContent = 'Dialer: ' + detail;
          result.appendChild(small);
        }
      }

      async function call(url, options) {
        var response = await fetch(url, Object.assign({
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token
          }
        }, options || {}));

        // A session timeout returns an HTML login page; parsing that blindly
        // would surface as "Unexpected token '<'".
        var raw = await response.text();
        try { return { ok: response.ok, status: response.status, data: raw ? JSON.parse(raw) : null }; }
        catch (e) { return { ok: response.ok, status: response.status, data: null }; }
      }

      function busy(btn, on, label) {
        btn.disabled = on;
        if (on) { btn.dataset.original = btn.innerHTML; btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> ' + label; }
        else if (btn.dataset.original) { btn.innerHTML = btn.dataset.original; }
      }

      async function loadDispositions() {
        dispSel.innerHTML = '<option value="">Loading...</option>';
        var res = await call(URLS.list, { method: 'GET' });
        var items = (res.data && res.data.dispositions) || [];

        dispSel.innerHTML = '<option value="">Select a disposition</option>';
        items.forEach(function (name) {
          var opt = document.createElement('option');
          opt.value = name; opt.textContent = name;
          dispSel.appendChild(opt);
        });
      }

      async function loadSubDispositions(disposition) {
        subWrap.classList.add('d-none');
        if (!disposition) return;

        var res = await call(URLS.subs + '?disposition=' + encodeURIComponent(disposition), { method: 'GET' });
        var items = (res.data && res.data.sub_dispositions) || [];
        if (!items.length) return;   // this campaign may have none configured

        subSel.innerHTML = '<option value="">None</option>';
        items.forEach(function (name) {
          var opt = document.createElement('option');
          opt.value = name; opt.textContent = name;
          subSel.appendChild(opt);
        });
        subWrap.classList.remove('d-none');
      }

      async function dial() {
        var number = (phone.value || '').trim();
        if (number.replace(/\D+/g, '').length < 10) {
          show('bad', 'Enter a valid phone number (at least 10 digits).');
          return;
        }

        busy(dialBtn, true, 'Dialling...');
        show('warn', 'Placing the call...');

        var res = await call(URLS.dial, { method: 'POST', body: JSON.stringify({ phone: number }) });
        busy(dialBtn, false);

        if (!res.data) {
          show('bad', res.status === 429
            ? 'Too many attempts. Wait a moment and try again.'
            : 'Your session may have expired. Refresh the page and sign in again.');
          return;
        }

        show(TONE[res.data.result] || 'bad', res.data.message, res.data.detail);

        if (res.data.result === 'success') {
          live.classList.remove('d-none');
          if (dispSel) loadDispositions();
        }
      }

      async function hangup() {
        busy(hangBtn, true, 'Ending...');
        var res = await call(URLS.hang, { method: 'POST', body: '{}' });
        busy(hangBtn, false);
        show(res.data ? (TONE[res.data.result] || 'bad') : 'bad',
             res.data ? res.data.message : 'Could not reach the server.',
             res.data ? res.data.detail : null);
      }

      async function dispose() {
        if (!dispSel.value) { show('bad', 'Choose a disposition first.'); return; }

        busy(dispBtn, true, 'Saving...');
        var res = await call(URLS.disp, {
          method: 'POST',
          body: JSON.stringify({
            disposition: dispSel.value,
            sub_disposition: subWrap.classList.contains('d-none') ? null : subSel.value
          })
        });
        busy(dispBtn, false);

        if (!res.data) { show('bad', 'Could not reach the server.'); return; }
        show(TONE[res.data.result] || 'bad', res.data.message, res.data.detail);

        if (res.data.result === 'success') {
          live.classList.add('d-none');
          phone.value = '';
        }
      }

      dialBtn.addEventListener('click', dial);
      hangBtn.addEventListener('click', hangup);
      // Absent entirely for a dialer with no disposition concept (Convoque) —
      // the markup above is conditional on that. Everything past here must
      // still run for that agent, so these two are null-checked rather than
      // assumed present like the rest of the panel.
      if (dispBtn) dispBtn.addEventListener('click', dispose);
      if (dispSel) dispSel.addEventListener('change', function () { loadSubDispositions(this.value); });
      phone.addEventListener('keydown', function (e) { if (e.key === 'Enter') dial(); });
    })();
  </script>
@endif
