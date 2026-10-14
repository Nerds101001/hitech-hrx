/**
 * Browser Web Push opt-in (standard VAPID push, not Firebase). Wired from
 * the "Enable notifications" link in the notification-bell dropdown
 * (layouts/sections/navbar/notifications.blade.php) via window.HrxWebPush.
 */
(function () {
  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  }

  async function subscribe(vapidPublicKey, csrfToken) {
    // updateViaCache: 'none' -- without it, browsers are allowed to serve
    // the service worker script itself from HTTP cache, so a change here
    // can silently never reach an already-cached browser at all.
    const registration = await navigator.serviceWorker.register('/push-sw.js', { updateViaCache: 'none' });
    await navigator.serviceWorker.ready;

    let subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
      });
    }

    const res = await fetch('/push/subscribe', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify(subscription.toJSON()),
    });
    if (!res.ok) {
      // Surface the real reason (validation error, CSRF mismatch, etc.)
      // instead of just a status code -- a silently-swallowed failure here
      // is exactly what left permission "granted" with nothing ever saved.
      let detail = '';
      try {
        const body = await res.text();
        const parsed = JSON.parse(body);
        detail = parsed.message || body;
      } catch (e) {}
      throw new Error('Server rejected the subscription (' + res.status + ')' + (detail ? ': ' + detail : ''));
    }
    return subscription;
  }

  async function unsubscribe(csrfToken) {
    if (!('serviceWorker' in navigator)) return;
    const registration = await navigator.serviceWorker.getRegistration('/push-sw.js');
    if (!registration) return;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return;

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();
    await fetch('/push/unsubscribe', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      credentials: 'same-origin',
      body: JSON.stringify({ endpoint }),
    });
  }

  async function permissionState() {
    if (!isSupported()) return 'unsupported';
    return Notification.permission; // 'default' | 'granted' | 'denied'
  }

  var DISMISS_KEY = 'hrx_push_banner_dismissed_until';
  var DISMISS_DAYS = 30;
  var BODY_PAD_CLASS = 'hrx-push-banner-open';

  /** Was "Not now" clicked within the last DISMISS_DAYS? Fails open (shows the
   *  banner) if localStorage is unavailable (private browsing, blocked, etc.) --
   *  the banner itself is not essential, so "ask again" is the safe default. */
  function recentlyDismissed() {
    try {
      var until = parseInt(localStorage.getItem(DISMISS_KEY) || '0', 10);
      return until > Date.now();
    } catch (e) {
      return false;
    }
  }
  function rememberDismissal() {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now() + DISMISS_DAYS * 24 * 60 * 60 * 1000));
    } catch (e) { /* storage unavailable: just doesn't persist this time */ }
  }

  function showBanner(vapidPublicKey, csrfToken) {
    // Remembered for 30 days once dismissed, not asked again on every single
    // page load -- still short enough that someone who changes their mind
    // gets asked again on their own, without needing to go find the bell icon.
    if (document.getElementById('hrxPushBanner') || recentlyDismissed()) return;

    // Never on top of (or even behind, fighting for the same screen space as) ANY
    // open modal, not just the mandatory policy one -- any Bootstrap modal in this
    // app carries the 'show' class while visible.
    if (document.querySelector('.modal.show')) return;

    var isPhone = window.matchMedia('(max-width: 480px)').matches;

    var banner = document.createElement('div');
    banner.id = 'hrxPushBanner';
    // z-index kept BELOW every modal (1050/1055 in this app's Bootstrap) as a second
    // safety even though the check above already keeps them from appearing
    // together: still comfortably above ordinary page content (navbar dropdowns
    // ~1000, the sidebar 1035).
    var baseStyle = 'position:fixed;z-index:1040;background:#fff;border:1px solid #dee2e6;' +
      'box-shadow:0 8px 28px rgba(0,0,0,.16);font-family:inherit;box-sizing:border-box;';
    banner.style.cssText = isPhone
      // A slim, <=64px strip pinned to the bottom edge on a phone, not the same
      // floating card shrunk down -- the card's own padding/icon/two-line copy
      // made it taller and narrower-feeling than a banner should be on a phone.
      ? baseStyle + 'left:0;right:0;bottom:0;border-radius:0;padding:8px 12px;' +
        'padding-bottom:calc(8px + env(safe-area-inset-bottom, 0px));'
      : baseStyle + 'bottom:24px;right:24px;border-radius:16px;padding:18px 20px;max-width:320px;';

    banner.innerHTML = isPhone
      ? '<div style="display:flex;align-items:center;gap:10px;">' +
          '<i class="bx bx-bell-plus" style="font-size:22px;color:#005a5a;flex-shrink:0;"></i>' +
          '<div style="flex:1;min-width:0;font-size:12.5px;font-weight:700;color:#1e293b;">Stay notified</div>' +
          '<button id="hrxPushBannerEnable" style="min-height:48px;min-width:48px;background:linear-gradient(135deg,#005a5a,#008080);' +
            'color:#fff;border:none;border-radius:999px;padding:0 16px;font-size:12.5px;font-weight:700;flex-shrink:0;">Enable</button>' +
          '<button id="hrxPushBannerDismiss" aria-label="Dismiss" style="min-height:48px;min-width:48px;background:transparent;' +
            'color:#94a3b8;border:none;font-size:20px;flex-shrink:0;">&times;</button>' +
        '</div>'
      : '<div style="display:flex;align-items:flex-start;gap:12px;">' +
          '<div style="flex-shrink:0;width:38px;height:38px;border-radius:10px;background:#f0fdfb;' +
               'display:flex;align-items:center;justify-content:center;">' +
            '<i class="bx bx-bell-plus" style="font-size:20px;color:#005a5a;"></i>' +
          '</div>' +
          '<div style="flex:1;min-width:0;">' +
            '<div style="font-weight:700;font-size:14px;color:#1e293b;">Stay notified</div>' +
            '<div style="font-size:12.5px;color:#64748b;margin:4px 0 12px;line-height:1.4;">' +
              'Get instant browser alerts for new tasks and approvals -- even when HRX isn\'t open.' +
            '</div>' +
            '<div style="display:flex;gap:8px;">' +
              '<button id="hrxPushBannerEnable" style="min-height:44px;background:linear-gradient(135deg,#005a5a,#008080);' +
                'color:#fff;border:none;border-radius:50px;padding:7px 18px;font-size:12.5px;font-weight:700;cursor:pointer;">' +
                'Enable</button>' +
              '<button id="hrxPushBannerDismiss" style="min-height:44px;background:transparent;color:#94a3b8;border:none;' +
                'font-size:12.5px;cursor:pointer;">Not now</button>' +
            '</div>' +
          '</div>' +
        '</div>';
    document.body.appendChild(banner);
    // Reserve space at the bottom of the page so this fixed banner never sits on
    // top of (and hides) whatever's already at the bottom of the viewport.
    document.body.classList.add(BODY_PAD_CLASS);

    function close() {
      banner.remove();
      document.body.classList.remove(BODY_PAD_CLASS);
    }

    document.getElementById('hrxPushBannerDismiss').addEventListener('click', function () {
      rememberDismissal();
      close();
    });
    document.getElementById('hrxPushBannerEnable').addEventListener('click', function () {
      var btn = document.getElementById('hrxPushBannerEnable');
      btn.textContent = 'Enabling...';
      subscribe(vapidPublicKey, csrfToken).then(function () {
        close();
      }).catch(function (err) {
        alert('Could not enable notifications: ' + err.message);
        btn.textContent = 'Enable';
      });
    });
  }

  /**
   * Call on every page load. If permission was already granted (from an
   * earlier visit) but nothing was ever saved server-side -- e.g. the save
   * request failed silently -- this quietly retries with no new dialog,
   * since the browser never re-prompts once permission is settled. If
   * permission hasn't been decided yet, it shows the banner instead of
   * requiring someone to go find the bell icon.
   */
  async function autoInit(vapidPublicKey, csrfToken) {
    if (!isSupported() || !vapidPublicKey) return;
    const state = Notification.permission;
    if (state === 'granted') {
      try {
        await subscribe(vapidPublicKey, csrfToken);
      } catch (err) {
        console.warn('HrxWebPush auto re-subscribe failed:', err);
      }
    } else if (state === 'default') {
      showBanner(vapidPublicKey, csrfToken);
    }
  }

  window.HrxWebPush = { isSupported, subscribe, unsubscribe, permissionState, autoInit };
})();
