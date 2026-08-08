{{--
    Click-to-call button and dialpad.

    Renders nothing unless the signed-in user is mapped to a dialer agent in
    config/access.php. The button being hidden is presentation only — the route
    checks the same mapping before it will dial.

    The dialer speaks plain HTTP and HRX is served over HTTPS, so the browser
    must not call it directly: mixed content is blocked. This posts to HRX and
    HRX relays the call.

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
            <div class="ctc-sub">Agent {{ $__ctcAgent['agent'] }} · campaign {{ $__ctcAgent['campaign'] }}</div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-3">
          <label class="form-label fw-bold small text-uppercase text-muted mb-1">Phone number</label>
          <input type="tel" id="ctcPhone" class="form-control ctc-input" inputmode="numeric"
                 placeholder="e.g. 7889041267" autocomplete="off">
          <div class="ctc-hint">Your agent phone rings first, then the customer.</div>

          <div id="ctcResult" class="ctc-result d-none"></div>

          <button type="button" id="ctcDial" class="ctc-dial">
            <i class="bx bx-phone-call me-1"></i> Call
          </button>
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

    .ctc-input {
      border: 1px solid #e2e8f0; border-radius: 10px; height: 46px;
      font-size: 1.05rem; font-weight: 700; letter-spacing: .04em;
    }
    .ctc-input:focus { border-color: #0d7377; box-shadow: 0 0 0 .18rem rgba(13,115,119,.15); }
    .ctc-hint { font-size: .68rem; color: #94a3b8; margin-top: .35rem; }

    .ctc-dial {
      width: 100%; margin-top: .9rem; padding: .6rem; border: none; border-radius: 10px;
      background: #0d7377; color: #fff; font-weight: 800; font-size: .85rem;
      transition: filter .18s ease;
    }
    .ctc-dial:hover:not(:disabled) { filter: brightness(1.1); }
    .ctc-dial:disabled { opacity: .65; }

    .ctc-result {
      margin-top: .8rem; padding: .55rem .7rem; border-radius: 10px;
      font-size: .74rem; font-weight: 600; line-height: 1.4;
    }
    .ctc-result.ok    { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .ctc-result.warn  { background: #fff7ed; color: #b45309; border: 1px solid #fed7aa; }
    .ctc-result.bad   { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
  </style>

  <script>
    (function () {
      var btn    = document.getElementById('ctcDial');
      var phone  = document.getElementById('ctcPhone');
      var result = document.getElementById('ctcResult');
      if (!btn || !phone || !result) return;

      var token = document.querySelector('meta[name="csrf-token"]');

      function show(cls, text) {
        result.className = 'ctc-result ' + cls;
        result.textContent = text;
      }

      // 'success' is the only outcome that actually placed a call; the dialer's
      // other replies are things the agent can fix themselves.
      var TONE = { success: 'ok', busy: 'warn', not_logged_in: 'warn', wrong_mode: 'warn' };

      async function dial() {
        var number = (phone.value || '').trim();
        if (number.replace(/\D+/g, '').length < 10) {
          show('bad', 'Enter a valid phone number (at least 10 digits).');
          return;
        }

        btn.disabled = true;
        var original = btn.innerHTML;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Dialling...';
        show('warn', 'Placing the call...');

        try {
          var response = await fetch('{{ route('user.click-to-call') }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
            },
            body: JSON.stringify({ phone: number })
          });

          // A session timeout returns an HTML login page; parsing that blindly
          // would surface as "Unexpected token '<'".
          var raw = await response.text();
          var data = null;
          try { data = raw ? JSON.parse(raw) : null; } catch (e) { data = null; }

          if (!data) {
            show('bad', response.status === 429
              ? 'Too many attempts. Wait a moment and try again.'
              : 'Your session may have expired. Refresh the page and sign in again.');
            return;
          }

          show(TONE[data.result] || 'bad', data.message || 'The dialer did not respond as expected.');
        } catch (e) {
          show('bad', 'Could not reach the server. Check your connection and try again.');
        } finally {
          btn.disabled = false;
          btn.innerHTML = original;
        }
      }

      btn.addEventListener('click', dial);
      phone.addEventListener('keydown', function (e) { if (e.key === 'Enter') dial(); });
    })();
  </script>
@endif
