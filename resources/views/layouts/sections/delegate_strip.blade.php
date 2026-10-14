{{--
    Delegate-session strip — lives in the top navbar, in the gap between the
    page title and the right-hand icons.

    Renders nothing unless the request is inside a delegate session: the same
    $delegateHub['mode'] === 'acting' state the navbar hub uses, set by the
    global view composer in AppServiceProvider.

    It is deliberately loud. While it is on screen every action is recorded
    against someone else's account. Hiding collapses it to an amber pill in the
    same spot — never to nothing — and the choice is per-tab (sessionStorage),
    so it survives navigation inside this session without leaking into a later
    one.

    Vanilla JS on purpose: @vite defers jQuery, so a jQuery call at parse time
    here would throw before the page's own handlers ever bind.
--}}
@if(isset($delegateHub) && $delegateHub['mode'] === 'acting')
  <div class="hrx-delegate-wrap d-none d-md-flex ms-3">
    <div id="hrxDelegateStrip" class="hrx-delegate-strip" role="status" aria-live="polite">
      <span class="hrx-delegate-strip__badge"><i class="bx bx-transfer-alt"></i></span>

      <span class="hrx-delegate-strip__text">
        <b>Delegate access active</b>
        <span title="Signed in as {{ $delegateHub['actingAs'] }} · your account: {{ $delegateHub['originalName'] }}">
          Working as {{ $delegateHub['actingAs'] }} · you are {{ $delegateHub['originalName'] }}
        </span>
      </span>

      <form method="POST" action="{{ route('user.delegation.exit') }}" class="mb-0">
        @csrf
        <button type="submit" class="hrx-delegate-strip__exit" title="Return to your own account">
          <i class="bx bx-log-out-circle"></i> Exit
        </button>
      </form>

      <button type="button" id="hrxDelegateStripHide" class="hrx-delegate-strip__toggle"
              title="Hide this bar" aria-label="Hide the delegate access bar">
        <i class="bx bx-x"></i>
      </button>
    </div>

    {{-- What is left behind once the strip is hidden — same spot, one click back. --}}
    <button type="button" id="hrxDelegateChip" class="hrx-delegate-chip"
            title="Delegate access is active — click to show the bar"
            aria-label="Show the delegate access bar">
      <i class="bx bx-transfer-alt"></i>
      <span>Delegate</span>
    </button>
  </div>

  <style>
    /* Amber, matching the navbar Delegate Access hub — the one colour in the
       app that means "you are not yourself right now". */
    /* position:relative + a z-index above the mandatory-policy modal (Bootstrap's
       default 1055/1050 for the modal/backdrop) so Exit stays reachable even while
       that modal is blocking the page -- without this, a statically-positioned
       element has no stacking order of its own and a fixed-position modal simply
       paints over it regardless of z-index (z-index does nothing on position:static). */
    .hrx-delegate-wrap { align-items: center; min-width: 0; position: relative; z-index: 1100; }

    .hrx-delegate-strip {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      max-width: 100%;
      padding: 0.3rem 0.4rem 0.3rem 0.45rem;
      border-radius: 999px;
      background: linear-gradient(135deg, #78350f, #b45309);
      color: #fff;
      box-shadow: 0 4px 12px rgba(120, 53, 15, 0.28);
    }
    .hrx-delegate-strip__badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 26px;
      height: 26px;
      flex-shrink: 0;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.2);
      font-size: 0.95rem;
    }
    .hrx-delegate-strip__text {
      display: flex;
      flex-direction: column;
      line-height: 1.15;
      min-width: 0;
    }
    .hrx-delegate-strip__text b {
      font-size: 0.62rem;
      font-weight: 800;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      white-space: nowrap;
    }
    .hrx-delegate-strip__text > span {
      font-size: 0.72rem;
      color: rgba(255, 255, 255, 0.88);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 34ch;
    }
    .hrx-delegate-strip__exit,
    .hrx-delegate-strip__toggle {
      display: inline-flex;
      align-items: center;
      gap: 0.25rem;
      flex-shrink: 0;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 700;
      line-height: 1;
      transition: all 0.18s ease;
    }
    .hrx-delegate-strip__exit {
      padding: 0.35rem 0.7rem;
      background: #fff;
      color: #b45309;
      border: 1px solid #fff;
    }
    .hrx-delegate-strip__exit:hover { background: #fff7ed; }
    .hrx-delegate-strip__toggle {
      width: 26px;
      height: 26px;
      justify-content: center;
      padding: 0;
      background: transparent;
      color: #fff;
      border: 1px solid rgba(255, 255, 255, 0.45);
      font-size: 0.95rem;
    }
    .hrx-delegate-strip__toggle:hover { background: rgba(255, 255, 255, 0.18); }

    .hrx-delegate-chip {
      display: none;
      align-items: center;
      gap: 0.35rem;
      padding: 0.35rem 0.75rem;
      border: none;
      border-radius: 999px;
      background: linear-gradient(135deg, #78350f, #b45309);
      color: #fff;
      font-size: 0.72rem;
      font-weight: 700;
      line-height: 1;
      box-shadow: 0 4px 12px rgba(120, 53, 15, 0.28);
    }
    .hrx-delegate-chip:hover { filter: brightness(1.08); }

    body.hrx-delegate-hidden .hrx-delegate-strip { display: none; }
    body.hrx-delegate-hidden .hrx-delegate-chip { display: inline-flex; }

    /* Give the long line up first when the navbar gets tight. */
    @media (max-width: 1199.98px) {
      .hrx-delegate-strip__text > span { display: none; }
    }
  </style>

  <script>
    (function () {
      var KEY  = 'hrx_delegate_strip_hidden';
      var hide = document.getElementById('hrxDelegateStripHide');
      var chip = document.getElementById('hrxDelegateChip');

      function apply(hidden) {
        document.body.classList.toggle('hrx-delegate-hidden', hidden);
        try { sessionStorage.setItem(KEY, hidden ? '1' : '0'); } catch (e) {}
      }

      // The navbar renders before </body>, so defer the class until the body
      // element actually exists.
      function restore() {
        try { if (sessionStorage.getItem(KEY) === '1') { document.body.classList.add('hrx-delegate-hidden'); } } catch (e) {}
      }
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restore);
      } else {
        restore();
      }

      if (hide) { hide.addEventListener('click', function () { apply(true); }); }
      if (chip) { chip.addEventListener('click', function () { apply(false); }); }
    })();
  </script>
@endif
