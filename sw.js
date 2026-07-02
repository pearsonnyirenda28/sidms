const CACHE_NAME = 'sidms-v2';
const urlsToCache = [
  '/sidms/',
  '/sidms/index.php',
  '/sidms/dashboard.php',
  '/sidms/assets/css/style.css',
  '/sidms/assets/js/notifications.js',
  '/sidms/assets/js/main.js',
  '/sidms/assets/js/admin.js',
  '/sidms/manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache)));
});

self.addEventListener('fetch', event => {
  event.respondWith(caches.match(event.request).then(response => response || fetch(event.request)));
});

// -------------------------------------------------------------------
// Push Notification Handling
// -------------------------------------------------------------------
self.addEventListener('push', event => {
  if (!(self.Notification && self.Notification.permission === 'granted')) {
    return;
  }

  let data = { title: 'SIDMS', body: 'You have a new notification.', icon: '/sidms/assets/icons/icon-192.png' };
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || '/sidms/assets/icons/icon-192.png',
    badge: '/sidms/assets/icons/icon-192.png',
    vibrate: [200, 100, 200],
    data: { url: data.url || '/sidms/' }
  };

  event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const url = event.notification.data.url || '/sidms/';
  event.waitUntil(
    clients.matchAll({ type: 'window' }).then(windowClients => {
      for (let client of windowClients) {
        if (client.url === url && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});