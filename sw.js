// Service Worker — LUXE CARD
// يستقبل إشعارات المتصفح ويعرضها

self.addEventListener('install', function (e) {
  self.skipWaiting();
});

self.addEventListener('activate', function (e) {
  e.waitUntil(self.clients.claim());
});

// استقبال رسالة من الصفحة لعرض إشعار
self.addEventListener('message', function (e) {
  if (e.data && e.data.type === 'notify') {
    var title = e.data.title || 'LUXE CARD';
    var body = e.data.body || '';
    self.registration.showNotification(title, {
      body: body,
      icon: '/logo.svg',
      badge: '/logo.svg',
      dir: 'rtl',
      lang: 'ar',
      tag: 'luxe-' + Date.now(),
      data: { url: e.data.url || '/' },
    });
  }
});

// عند الضغط على الإشعار: فتح الموقع
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if ('focus' in list[i]) return list[i].focus();
      }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});
