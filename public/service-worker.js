const CACHE_NAME = 'teckfo-technician-shell-v3';
const OFFLINE_PAGE = '/offline-teknisi';

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll([OFFLINE_PAGE, '/teckfo-logo.png']))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key.startsWith('teckfo-technician-shell-') && key !== CACHE_NAME)
                .map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const requestUrl = new URL(event.request.url);
    if (event.request.method !== 'GET' || requestUrl.origin !== self.location.origin || requestUrl.pathname.startsWith('/api/')) {
        return;
    }

    if (event.request.mode === 'navigate' && requestUrl.pathname === '/teknisi') {
        event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_PAGE)));
    }
});
