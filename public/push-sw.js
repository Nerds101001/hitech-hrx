// HRX Web Push service worker (standard W3C Push API + VAPID). Must live at
// the site root so its default scope covers the whole app -- see
// resources/assets/js/webpush-subscribe.js, which registers it.

self.addEventListener('install', function () {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'HRX', body: event.data ? event.data.text() : '' };
  }

  const title = data.title || 'HRX';
  const options = {
    body: data.body || '',
    icon: data.icon || '/assets/img/logo-mark.png',
    badge: data.badge || '/assets/img/logo-mark.png',
    data: { url: data.url || '/' },
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
      for (const client of clientList) {
        if (client.url.includes(url) && 'focus' in client) {
          return client.focus();
        }
      }
      if (self.clients.openWindow) {
        return self.clients.openWindow(url);
      }
    })
  );
});
