const CACHE = 'clan-hub-offline-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.add(OFFLINE_URL)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys().then((keys) => Promise.all(
        keys.filter((key) => key.startsWith('clan-hub-offline-') && key !== CACHE)
            .map((key) => caches.delete(key)),
    )));
});

// Authenticated pages, Inertia responses and API data always use the network.
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET' || event.request.mode !== 'navigate') return;
    if (new URL(event.request.url).origin !== self.location.origin) return;

    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
