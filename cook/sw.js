const SW_VERSION = 1;
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', () => {});
self.addEventListener('fetch', e => e.respondWith(fetch(e.request)));
