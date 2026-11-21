/* Gesond service worker: makes the site installable and quick to open.
   Pages are always fetched fresh when online (so events stay current); a cached copy is shown offline.
   Member, staff and API pages are never cached. */
const VERSION = 'gesond-20261122b';
const CORE = ['/', '/events.html', '/updates.html', '/offline.html', '/assets/css/style.css?v=20261122b',
  '/assets/fonts/anton.woff2', '/assets/fonts/archivo-wdth.woff2', '/assets/brand/gesond-logo-600.webp', '/assets/brand/icon-192.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(CORE)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/members/')) return;
  if (req.mode === 'navigate' || url.pathname.endsWith('.html') || url.pathname.endsWith('/') || url.pathname.endsWith('.js')) {
    // Network first: always current when online
    e.respondWith(fetch(req).then((res) => {
      const copy = res.clone();
      if (res.ok) caches.open(VERSION).then((c) => c.put(req, copy));
      return res;
    }).catch(() => caches.match(req).then((hit) => hit || (req.mode === 'navigate' ? caches.match('/offline.html') : undefined))));
    return;
  }
  // Images, fonts, CSS: cache first
  e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
    const copy = res.clone();
    if (res.ok) caches.open(VERSION).then((c) => c.put(req, copy));
    return res;
  })));
});
