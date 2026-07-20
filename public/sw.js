/*
 * Blue Star Travel & Tours — service worker (agent + customer portals).
 *
 * Deliberately conservative about what it stores. These portals show passport
 * numbers, bookings and commission, often on shared phones, so HTML responses are
 * NEVER written to the cache — only the static shell (logo, icons, built CSS/JS)
 * and a branded offline page. Anything under /pay/ is left entirely alone so a
 * payment always talks to the network.
 */

const VERSION = 'bluestar-v1';
const SHELL = 'shell-' + VERSION;

const PRECACHE = [
  '/offline.html',
  '/images/logo.png',
  '/images/logo-icon.png',
  '/images/icon-192.png',
  '/images/icon-512.png',
  '/favicon.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(SHELL)
      .then((cache) => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== SHELL).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

/** Static assets we are happy to serve from cache. */
function isShellAsset(url) {
  return url.pathname.startsWith('/images/')
    || url.pathname.startsWith('/build/')
    || url.pathname === '/favicon.png'
    || /\.(css|js|woff2?|ttf|svg|png|jpg|jpeg|webp)$/.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  const url = new URL(request.url);

  // Only same-origin GETs. Never touch payments, webhooks or file downloads.
  if (request.method !== 'GET'
    || url.origin !== self.location.origin
    || url.pathname.startsWith('/pay/')
    || url.pathname.includes('/download')
    || url.pathname.includes('/export')) {
    return;
  }

  if (isShellAsset(url)) {
    // Cache-first — these are versioned/static and carry no personal data.
    event.respondWith(
      caches.match(request).then((hit) => hit || fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(SHELL).then((cache) => cache.put(request, copy));
        }
        return response;
      }))
    );
    return;
  }

  if (request.mode === 'navigate') {
    // Always live. On a network failure show the offline page — the response
    // itself is never cached, so no private page is left on the device.
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
  }
});
