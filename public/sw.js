/*
 * Service worker of the TUA Research Repository (CLAUDE.md §16 "PWA").
 *
 * It caches ONLY the app shell: the built CSS/JS/fonts (/build/), the icons, the manifest and
 * the "You are offline" page. It never caches pages, research files, downloads or any response
 * that can hold personal data: every other request goes straight to the network, and a page that
 * cannot load shows the offline page instead.
 */
const VERSION = 'tua-v1';
const SHELL = `${VERSION}-shell`;
const STATIC = `${VERSION}-static`;
const PRECACHE = ['/offline', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL).then((c) => c.addAll(PRECACHE.map((u) => new Request(u, { credentials: 'omit' })))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    // Remove caches of older versions.
    event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => !k.startsWith(VERSION)).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Built assets have content hashes in their names: cache first, then keep them.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.open(STATIC).then(async (cache) => {
            const hit = await cache.match(req);
            if (hit) return hit;
            const res = await fetch(req);
            if (res.ok && res.type === 'basic') cache.put(req, res.clone());
            return res;
        }));
        return;
    }

    // Pages: always from the network (they can hold personal data); offline page when it fails.
    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match('/offline')));
    }
    // Everything else (files, downloads, JSON, Livewire) is left to the browser and never cached.
});
