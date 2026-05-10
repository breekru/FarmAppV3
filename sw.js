// sw.js — FarmApp Service Worker
// Bump CACHE_VERSION whenever you deploy updated static assets.
const CACHE_VERSION  = 'v2';
const SHELL_CACHE    = `farmapp-shell-${CACHE_VERSION}`;

// Files to precache on install (app shell).
// These must all return 200 for the install to succeed.
const PRECACHE_URLS = [
  '/css/main.css',
  '/js/db.js',
  '/js/sync.js',
  '/js/app.js',
  '/js/pwa.js',
  '/change_password.php',
  '/offline.html',
  '/manifest.json',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
  '/icons/apple-touch-icon.png',
];

// ─────────────────────────────────────────────
// INSTALL — cache the app shell
// ─────────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then(cache => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())   // activate immediately, don't wait for tabs to close
      .catch(err => console.error('[SW] Precache failed:', err))
  );
});

// ─────────────────────────────────────────────
// ACTIVATE — purge old caches
// ─────────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(key => key.startsWith('farmapp-') && key !== SHELL_CACHE)
          .map(key => {
            console.log('[SW] Deleting old cache:', key);
            return caches.delete(key);
          })
      ))
      .then(() => self.clients.claim())  // take control of all open tabs immediately
  );
});

// ─────────────────────────────────────────────
// FETCH — routing strategies
// ─────────────────────────────────────────────
self.addEventListener('fetch', event => {
  const req = event.request;
  const url = new URL(req.url);

  // Only handle GET from our own origin
  if (req.method !== 'GET' || url.origin !== self.location.origin) {
    return;
  }

  // ── API calls: network-only ──────────────────
  // The JS layer (Phase 4) handles offline via IndexedDB.
  // The SW must not interfere or cache API responses.
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // ── Navigation requests (HTML pages) ─────────
  // Strategy: network-first → cached page → offline.html
  if (req.mode === 'navigate') {
    event.respondWith(networkFirstWithOfflineFallback(req));
    return;
  }

  // ── Static assets (CSS, JS, images, fonts) ───
  // Strategy: cache-first → network (stale-while-revalidate)
  event.respondWith(cacheFirstWithNetworkUpdate(req));
});

// ─────────────────────────────────────────────
// Strategy: network-first, cache on success, offline.html on failure
// ─────────────────────────────────────────────
async function networkFirstWithOfflineFallback(req) {
  try {
    const networkResponse = await fetch(req);

    // Cache successful page responses for offline use
    if (networkResponse.ok) {
      const cache = await caches.open(SHELL_CACHE);
      cache.put(req, networkResponse.clone());
    }

    return networkResponse;
  } catch {
    // Network failed — try cache
    const cached = await caches.match(req);
    if (cached) return cached;

    // Nothing in cache — serve offline page
    return caches.match('/offline.html');
  }
}

// ─────────────────────────────────────────────
// Strategy: cache-first, revalidate in background (stale-while-revalidate)
// ─────────────────────────────────────────────
async function cacheFirstWithNetworkUpdate(req) {
  const cached = await caches.match(req);

  // Kick off a background fetch to keep the cache fresh
  const networkFetch = fetch(req).then(response => {
    if (response.ok) {
      caches.open(SHELL_CACHE).then(cache => cache.put(req, response.clone()));
    }
    return response;
  }).catch(() => null);

  // Return the cached version immediately if we have it
  if (cached) return cached;

  // No cache — wait for network
  const networkResponse = await networkFetch;
  return networkResponse || new Response('Asset unavailable offline.', { status: 503 });
}

// ─────────────────────────────────────────────
// MESSAGE — allow pages to trigger SW update check
// ─────────────────────────────────────────────
self.addEventListener('message', event => {
  if (event.data?.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
