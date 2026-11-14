const CACHE_NAME = 'barcode-app-v5';

// IMPORTANT: do NOT list any session-dependent .php page here (barcode.php,
// generator.php, settings.php, users.php, print.php, qrcode.php, index.php).
// Their HTML is rendered per-session (username, admin sidebar, permissions)
// - precaching or cache-first-serving them would show one user's page to
// the next user who logs in on the same device until a hard refresh.
// See ROOT CAUSE note below.
const ASSETS = [
  './image/logo.png'
];

// Extensions that are safe to cache: pure static files whose content never
// depends on who is logged in. Everything else (all dynamic .php pages,
// including navigations) is always fetched fresh from the network.
const STATIC_EXT = /\.(css|js|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf)$/i;

// manifest.php is a special case: its JSON output is static/session-free,
// so it's safe to cache even though it's a .php file.
const isCacheableStatic = (url) =>
  STATIC_EXT.test(url.pathname) || url.pathname.endsWith('/manifest.php');

const isApiRequest = (url) => url.pathname.includes('/api/');

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(ASSETS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  if (e.request.method !== 'GET') return;
  const url = new URL(e.request.url);

  // ROOT CAUSE FIX (stale admin/user after account switch): navigation
  // requests (typing a URL, clicking a link, redirect after login/logout)
  // and every dynamic .php page/API must always go to the network so the
  // HTML always reflects the CURRENT session - never served from cache.
  if (e.request.mode === 'navigate' || isApiRequest(url) || !isCacheableStatic(url)) {
    e.respondWith(fetch(e.request, { cache: 'no-store' }).catch(() => caches.match(e.request)));
    return;
  }

  // Genuinely static assets only: cache-first, refresh in the background.
  e.respondWith(
    caches.open(CACHE_NAME).then(cache =>
      cache.match(e.request).then(cached => {
        const network = fetch(e.request).then(res => {
          if (res && res.ok) cache.put(e.request, res.clone());
          return res;
        }).catch(() => cached);
        return cached || network;
      })
    )
  );
});
