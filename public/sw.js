/*
 * Service worker: makes the app installable and keeps working assets
 * (scripts, styles, fonts, icons) cached. Signed-in pages and API answers
 * are never cached, so no personal data stays on a shared phone; when the
 * network is down, page loads get a small offline notice instead.
 */
const VERSION = 'madrasa-v1';
const PRECACHE = ['/offline.html', '/icons/icon-192.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
        .then(() => self.clients.claim()));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Built assets have content hashes in their names: cache first, forever.
    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.match(request).then((hit) => hit || fetch(request).then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(VERSION).then((cache) => cache.put(request, copy));
            }
            return response;
        })));
        return;
    }

    // Page loads: always from the network; offline notice if it fails.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    }
});
