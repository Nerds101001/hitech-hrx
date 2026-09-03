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
    const registration = await navigator.serviceWorker.register('/push-sw.js');
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
    if (!res.ok) throw new Error('Server rejected the subscription (' + res.status + ')');
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

  window.HrxWebPush = { isSupported, subscribe, unsubscribe, permissionState };
})();
